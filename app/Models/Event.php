<?php

namespace App\Models;

use App\Enums\EventType;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single thing that happened, append-only. Nothing here stores a total; every
 * score in the application is derived from these rows plus the aired episodes.
 *
 * @property int $id
 * @property int $season_id
 * @property int $episode_number
 * @property int $castaway_id
 * @property EventType $type
 * @property int|null $points
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Season $season
 * @property-read Castaway $castaway
 */
#[Fillable(['season_id', 'episode_number', 'castaway_id', 'type', 'points', 'note'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'points' => 'integer',
            'episode_number' => 'integer',
        ];
    }

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** @return BelongsTo<Castaway, $this> */
    public function castaway(): BelongsTo
    {
        return $this->belongsTo(Castaway::class);
    }
}
