<?php

use App\Models\Castaway;
use Illuminate\Support\Facades\Storage;

it('returns the stored file url when a photo has been ingested', function () {
    Storage::fake(config('filesystems.cast_photos'));

    $castaway = Castaway::factory()->create(['photo_path' => 'cast/51/9.webp']);

    expect($castaway->photoUrl())->toContain('cast/51/9.webp');
});

it('falls back to the source url when the download never landed', function () {
    $castaway = Castaway::factory()->create([
        'photo_path' => null,
        'photo_source_url' => 'https://example.com/jelly.jpg',
    ]);

    expect($castaway->photoUrl())->toBe('https://example.com/jelly.jpg');
});

it('returns nothing when there is neither a file nor a source', function () {
    expect(Castaway::factory()->create()->photoUrl())->toBeNull();
});

it('never touches the disk when there is no stored photo', function () {
    // A cast with no photos is the normal state before the press stills land.
    // Rendering it must not construct the storage client at all, or a
    // half-configured bucket takes down the whole Castaways page.
    config()->set('filesystems.cast_photos', 's3');
    config()->set('filesystems.disks.s3', ['driver' => 's3']);

    $castaway = Castaway::factory()->create([
        'photo_path' => null,
        'photo_source_url' => 'https://example.com/jelly.jpg',
    ]);

    expect($castaway->photoUrl())->toBe('https://example.com/jelly.jpg');
});
