<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\FetchedImage;
use App\Exceptions\ImageFetchException;
use App\Exceptions\LeagueException;
use App\Models\Castaway;
use App\Models\Season;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Pulls cast photos in from URLs and stores them locally.
 *
 * A failed download is not a failed call. The source URL is saved either way, so
 * the board falls back to hotlinking it and a CDN that blocks us degrades to a
 * working image rather than a broken page.
 */
final class PhotoIngestor
{
    private const MAX_EDGE_PIXELS = 600;

    public function __construct(private readonly SafeImageFetcher $fetcher) {}

    /**
     * The public disk locally; the object-storage bucket on Laravel Cloud,
     * whose container filesystem does not survive a redeploy.
     */
    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.cast_photos'));
    }

    /**
     * Fetch and store one photo.
     *
     * @return array{castaway: string, stored: bool, path: string|null, warning: string|null}
     */
    public function ingest(Castaway $castaway, string $url): array
    {
        try {
            $image = $this->fetcher->fetch($url);
        } catch (ImageFetchException $exception) {
            // Keep the URL: a blocked download still leaves us something to show.
            $castaway->update([
                'photo_source_url' => $url,
                'photo_fetched_at' => null,
            ]);

            return [
                'castaway' => $castaway->name,
                'stored' => false,
                'path' => null,
                'warning' => "Could not download {$castaway->name}'s photo because {$exception->getMessage()}. "
                    .'The board will hotlink the URL instead.',
            ];
        }

        $image = $this->normalise($image);
        $path = $this->pathFor($castaway, $image->extension);

        // Re-running replaces the file rather than accumulating variants.
        $this->forgetPreviousFile($castaway, $path);

        $this->disk()->put($path, $image->bytes);

        $castaway->update([
            'photo_path' => $path,
            'photo_source_url' => $url,
            'photo_fetched_at' => now(),
        ]);

        return [
            'castaway' => $castaway->name,
            'stored' => true,
            'path' => $path,
            'warning' => null,
        ];
    }

    /**
     * Store an image we already hold the bytes for, such as a Filament upload.
     */
    public function storeBytes(Castaway $castaway, string $bytes, string $extension): string
    {
        $path = $this->pathFor($castaway, $extension);

        $this->forgetPreviousFile($castaway, $path);

        $this->disk()->put($path, $bytes);

        $castaway->update(['photo_path' => $path, 'photo_fetched_at' => now()]);

        return $path;
    }

    /**
     * Downscale to a 600px max edge and re-encode to WebP.
     *
     * This normalises the board layout and strips whatever metadata was riding
     * along in the original file. If the image cannot be decoded by GD it is
     * stored as it arrived — it has already been validated as an image.
     */
    private function normalise(FetchedImage $image): FetchedImage
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromstring')) {
            return $image;
        }

        $source = @imagecreatefromstring($image->bytes);

        if ($source === false) {
            return $image;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $longestEdge = max($width, $height);

        if ($longestEdge > self::MAX_EDGE_PIXELS) {
            $scale = self::MAX_EDGE_PIXELS / $longestEdge;
            $resized = imagescale($source, (int) round($width * $scale), (int) round($height * $scale));

            if ($resized !== false) {
                imagedestroy($source);
                $source = $resized;
            }
        }

        ob_start();
        $encoded = imagewebp($source, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($source);

        if (! $encoded || $bytes === '') {
            return $image;
        }

        return new FetchedImage(bytes: $bytes, mimeType: 'image/webp', extension: 'webp');
    }

    private function pathFor(Castaway $castaway, string $extension): string
    {
        return "cast/{$castaway->season->number}/{$castaway->id}.{$extension}";
    }

    /**
     * Drop the file the castaway had before, unless the new one lands on the
     * same path and is about to overwrite it anyway.
     */
    private function forgetPreviousFile(Castaway $castaway, string $newPath): void
    {
        $previous = $castaway->photo_path;

        if ($previous !== null && $previous !== $newPath && $this->disk()->exists($previous)) {
            $this->disk()->delete($previous);
        }
    }

    /**
     * Fetch a whole cast in one go.
     *
     * Returns a row per castaway rather than a pass/fail, because the caller
     * needs to know which three of twenty-one did not land.
     *
     * @param  array<int, array{castaway: string, url: string}>  $photos
     * @return array<int, array{castaway: string, stored: bool, path: string|null, warning: string|null}>
     */
    public function ingestMany(Season $season, array $photos, CastawayResolver $resolver): array
    {
        $cast = Castaway::query()->where('season_id', $season->id)->orderBy('name')->get();
        $results = [];

        foreach ($photos as $photo) {
            $name = trim($photo['castaway']);
            $url = trim($photo['url']);

            try {
                $castaway = $resolver->resolveIn($cast, $name);
            } catch (LeagueException $exception) {
                $results[] = [
                    'castaway' => $name,
                    'stored' => false,
                    'path' => null,
                    'warning' => $exception->getMessage(),
                ];

                continue;
            }

            $results[] = $this->ingest($castaway, $url);
        }

        return $results;
    }
}
