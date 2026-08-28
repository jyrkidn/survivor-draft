<?php

use App\Exceptions\ImageFetchException;
use App\Models\Castaway;
use App\Models\Season;
use App\Services\CastawayResolver;
use App\Services\PhotoIngestor;
use App\Services\SafeImageFetcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('filesystems.cast_photos'));

    $this->season = Season::factory()->create(['number' => 51]);
    $this->castaway = Castaway::factory()->create([
        'season_id' => $this->season->id,
        'name' => 'Jelly Aquino',
    ]);
});

function pngBytes(int $width = 800, int $height = 800): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 20, 90, 140));

    ob_start();
    imagepng($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

describe('the SSRF guard', function () {
    $rejected = [
        'plain http' => ['http://example.com/a.png', 'only https URLs are accepted'],
        'loopback by name' => ['https://localhost/a.png', 'private or link-local'],
        'loopback by address' => ['https://127.0.0.1/a.png', 'private or link-local'],
        'private class A' => ['https://10.0.0.5/a.png', 'private or link-local'],
        'private class B' => ['https://172.16.4.4/a.png', 'private or link-local'],
        'private class C' => ['https://192.168.1.1/a.png', 'private or link-local'],
        'link-local metadata service' => ['https://169.254.169.254/latest/meta-data/', 'private or link-local'],
        'IPv6 loopback' => ['https://[::1]/a.png', 'private or link-local'],
        'IPv6 unique local' => ['https://[fc00::1]/a.png', 'private or link-local'],
        'not a URL at all' => ['nonsense', 'not a valid URL'],
    ];

    foreach ($rejected as $label => [$url, $expected]) {
        it("refuses {$label}", function () use ($url, $expected) {
            expect(fn () => app(SafeImageFetcher::class)->assertReachableSafely($url))
                ->toThrow(ImageFetchException::class, $expected);
        });
    }

    it('allows a public https URL through', function () {
        [$host, $port] = app(SafeImageFetcher::class)->assertReachableSafely('https://1.1.1.1/photo.png');

        expect($host)->toBe('1.1.1.1')->and($port)->toBe(443);
    });

    it('never sends a request to a blocked address', function () {
        Http::fake();

        app(PhotoIngestor::class)->ingest($this->castaway, 'https://169.254.169.254/latest/meta-data/');

        Http::assertNothingSent();
    });
});

describe('ingesting a photo', function () {
    it('stores the image, normalised to webp, and records where it came from', function () {
        Http::fake([
            '*' => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png']),
        ]);

        $result = app(PhotoIngestor::class)->ingest($this->castaway, 'https://1.1.1.1/jelly.png');

        $this->castaway->refresh();

        expect($result['stored'])->toBeTrue()
            ->and($result['warning'])->toBeNull()
            ->and($this->castaway->photo_path)->toBe("cast/51/{$this->castaway->id}.webp")
            ->and($this->castaway->photo_source_url)->toBe('https://1.1.1.1/jelly.png')
            ->and($this->castaway->photo_fetched_at)->not->toBeNull();

        Storage::disk(config('filesystems.cast_photos'))->assertExists("cast/51/{$this->castaway->id}.webp");
    });

    it('downscales to a 600px max edge', function () {
        Http::fake(['*' => Http::response(pngBytes(1600, 800), 200, ['Content-Type' => 'image/png'])]);

        app(PhotoIngestor::class)->ingest($this->castaway, 'https://1.1.1.1/wide.png');

        $stored = Storage::disk(config('filesystems.cast_photos'))->get("cast/51/{$this->castaway->id}.webp");
        $size = getimagesizefromstring($stored);

        expect($size[0])->toBe(600)->and($size[1])->toBe(300);
    });

    it('sends a browser user agent, because some CDNs reject anything scripted', function () {
        Http::fake(['*' => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png'])]);

        app(PhotoIngestor::class)->ingest($this->castaway, 'https://1.1.1.1/jelly.png');

        Http::assertSent(fn (Request $request): bool => str_contains($request->header('User-Agent')[0], 'Mozilla/5.0'));
    });

    it('replaces the file when run again', function () {
        Http::fake(['*' => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png'])]);

        app(PhotoIngestor::class)->ingest($this->castaway, 'https://1.1.1.1/one.png');
        app(PhotoIngestor::class)->ingest($this->castaway, 'https://1.1.1.1/two.png');

        expect($this->castaway->refresh()->photo_source_url)->toBe('https://1.1.1.1/two.png');
        Storage::disk(config('filesystems.cast_photos'))->assertExists("cast/51/{$this->castaway->id}.webp");
    });
});

describe('a failed download', function () {
    it('keeps the source URL and warns instead of failing', function () {
        Http::fake(['*' => Http::response('nope', 403)]);

        $result = app(PhotoIngestor::class)->ingest($this->castaway, 'https://1.1.1.1/blocked.png');

        $this->castaway->refresh();

        expect($result['stored'])->toBeFalse()
            ->and($result['warning'])->toContain('Jelly Aquino')
            ->and($result['warning'])->toContain('HTTP 403')
            ->and($this->castaway->photo_source_url)->toBe('https://1.1.1.1/blocked.png')
            ->and($this->castaway->photo_path)->toBeNull()
            ->and($this->castaway->photoUrl())->toBe('https://1.1.1.1/blocked.png');
    });

    it('refuses a response that is not an image', function () {
        Http::fake(['*' => Http::response('<html>hello</html>', 200, ['Content-Type' => 'text/html'])]);

        $result = app(PhotoIngestor::class)->ingest($this->castaway, 'https://1.1.1.1/page.html');

        expect($result['stored'])->toBeFalse()
            ->and($result['warning'])->toContain('rather than an image');
    });

    it('refuses bytes that only claim to be an image', function () {
        Http::fake(['*' => Http::response('not really a png', 200, ['Content-Type' => 'image/png'])]);

        $result = app(PhotoIngestor::class)->ingest($this->castaway, 'https://1.1.1.1/liar.png');

        expect($result['stored'])->toBeFalse()
            ->and($result['warning'])->toContain('not a readable image');
    });

    it('refuses anything over five megabytes', function () {
        Http::fake(['*' => Http::response(str_repeat('x', 6 * 1024 * 1024), 200, ['Content-Type' => 'image/png'])]);

        $result = app(PhotoIngestor::class)->ingest($this->castaway, 'https://1.1.1.1/huge.png');

        expect($result['stored'])->toBeFalse()
            ->and($result['warning'])->toContain('larger than 5 MB');
    });
});

describe('bulk ingestion', function () {
    it('returns a row per castaway so the caller knows which ones missed', function () {
        Castaway::factory()->create(['season_id' => $this->season->id, 'name' => 'Kilby Reeves']);

        Http::fake([
            'https://1.1.1.1/good.png' => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png']),
            'https://1.1.1.1/bad.png' => Http::response('', 500),
        ]);

        $results = app(PhotoIngestor::class)->ingestMany($this->season, [
            ['castaway' => 'Jelly', 'url' => 'https://1.1.1.1/good.png'],
            ['castaway' => 'Kilby', 'url' => 'https://1.1.1.1/bad.png'],
            ['castaway' => 'Probst', 'url' => 'https://1.1.1.1/good.png'],
        ], app(CastawayResolver::class));

        expect($results)->toHaveCount(3)
            ->and($results[0]['stored'])->toBeTrue()
            ->and($results[1]['stored'])->toBeFalse()
            ->and($results[1]['warning'])->toContain('HTTP 500')
            ->and($results[2]['stored'])->toBeFalse()
            ->and($results[2]['warning'])->toContain('No castaway matches "Probst"');
    });
});
