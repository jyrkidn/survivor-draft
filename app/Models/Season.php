<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $number
 * @property string|null $title
 * @property CarbonImmutable|null $premiere_date
 * @property int $roster_size
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Manager> $managers
 * @property-read Collection<int, Castaway> $castaways
 * @property-read Collection<int, Episode> $episodes
 * @property-read Collection<int, Event> $events
 * @property-read Collection<int, DraftPick> $draftPicks
 * @property-read Collection<int, ScoringRule> $scoringRules
 */
#[Fillable(['number', 'title', 'premiere_date', 'roster_size', 'is_active'])]
class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'premiere_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Manager, $this> */
    public function managers(): HasMany
    {
        return $this->hasMany(Manager::class)->orderBy('draft_slot');
    }

    /** @return HasMany<Castaway, $this> */
    public function castaways(): HasMany
    {
        return $this->hasMany(Castaway::class);
    }

    /** @return HasMany<Episode, $this> */
    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class)->orderBy('number');
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return HasMany<DraftPick, $this> */
    public function draftPicks(): HasMany
    {
        return $this->hasMany(DraftPick::class)->orderBy('pick_number');
    }

    /** @return HasMany<ScoringRule, $this> */
    public function scoringRules(): HasMany
    {
        return $this->hasMany(ScoringRule::class);
    }

    /**
     * How many rounds the draft runs for: as many complete passes through the
     * managers as the roster allows. 21 castaways and 2 managers is 10 rounds.
     */
    public function draftRounds(): int
    {
        $managerCount = $this->managers()->count();

        return $managerCount === 0 ? 0 : intdiv($this->roster_size, $managerCount);
    }

    public function totalDraftPicks(): int
    {
        return $this->draftRounds() * $this->managers()->count();
    }

    public function episodesAired(): int
    {
        return $this->episodes()->count();
    }
}
