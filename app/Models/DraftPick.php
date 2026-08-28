<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DraftPickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $season_id
 * @property int $pick_number
 * @property int $round
 * @property int $manager_id
 * @property int $castaway_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Season $season
 * @property-read Manager $manager
 * @property-read Castaway $castaway
 */
#[Fillable(['season_id', 'pick_number', 'round', 'manager_id', 'castaway_id'])]
class DraftPick extends Model
{
    /** @use HasFactory<DraftPickFactory> */
    use HasFactory;

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** @return BelongsTo<Manager, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

    /** @return BelongsTo<Castaway, $this> */
    public function castaway(): BelongsTo
    {
        return $this->belongsTo(Castaway::class);
    }
}
