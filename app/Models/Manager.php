<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ManagerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $season_id
 * @property string $name
 * @property int $draft_slot
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Season $season
 * @property-read Collection<int, DraftPick> $draftPicks
 */
#[Fillable(['season_id', 'name', 'draft_slot'])]
class Manager extends Model
{
    /** @use HasFactory<ManagerFactory> */
    use HasFactory;

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** @return HasMany<DraftPick, $this> */
    public function draftPicks(): HasMany
    {
        return $this->hasMany(DraftPick::class)->orderBy('pick_number');
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
