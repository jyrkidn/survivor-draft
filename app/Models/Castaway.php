<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CastawayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $season_id
 * @property string $name
 * @property string|null $nickname
 * @property int|null $age
 * @property string|null $occupation
 * @property string|null $hometown
 * @property string|null $tribe
 * @property string|null $photo_path
 * @property string|null $photo_source_url
 * @property CarbonImmutable|null $photo_fetched_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Season $season
 * @property-read Collection<int, Event> $events
 * @property-read DraftPick|null $draftPick
 */
#[Fillable([
    'season_id', 'name', 'nickname', 'age', 'occupation', 'hometown', 'tribe',
    'photo_path', 'photo_source_url', 'photo_fetched_at',
])]
class Castaway extends Model
{
    /** @use HasFactory<CastawayFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'photo_fetched_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('episode_number');
    }

    /** @return HasOne<DraftPick, $this> */
    public function draftPick(): HasOne
    {
        return $this->hasOne(DraftPick::class);
    }

    /**
     * A displayable image: the stored file if the download landed, otherwise
     * the source URL we were given, so a blocked fetch degrades to a hotlink
     * rather than a broken image.
     *
     * Deliberately does not ask the disk whether the file is there. A stored
     * `photo_path` only ever gets written after PhotoIngestor has put the
     * bytes, so the check bought nothing — and on a remote disk it cost a
     * network round trip for every castaway on every page render, and made an
     * unconfigured disk take the whole page down rather than just the image.
     */
    public function photoUrl(): ?string
    {
        if ($this->photo_path === null) {
            return $this->photo_source_url;
        }

        return Storage::disk(config('filesystems.cast_photos'))->url($this->photo_path);
    }
}
