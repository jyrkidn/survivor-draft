<?php

namespace App\Models;

use App\Enums\EventType;
use Carbon\CarbonImmutable;
use Database\Factories\ScoringRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scoring values are data, not code. Changing one re-scores the whole season,
 * because no total is ever stored.
 *
 * @property int $id
 * @property int $season_id
 * @property EventType $type
 * @property int $points
 * @property string|null $label
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Season $season
 */
#[Fillable(['season_id', 'type', 'points', 'label'])]
class ScoringRule extends Model
{
    /** @use HasFactory<ScoringRuleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'points' => 'integer',
        ];
    }

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
