<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Castaway;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

/**
 * Adding and correcting the cast.
 *
 * Registration is idempotent on the castaway's name, the same way
 * `record_episode` is idempotent on the episode number: saying the cast twice
 * corrects it rather than duplicating it, and a second call carrying only a
 * name will not blank the details already recorded.
 */
final class CastService
{
    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array{
     *     created: int,
     *     updated: int,
     *     cast_size: int,
     *     results: array<int, array{name: string, status: string}>,
     *     warnings: array<int, string>
     * }
     */
    public function register(Season $season, array $entries): array
    {
        $results = [];
        $warnings = [];
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($season, $entries, &$results, &$warnings, &$created, &$updated): void {
            foreach ($entries as $entry) {
                $name = trim((string) ($entry['name'] ?? ''));

                if ($name === '') {
                    $warnings[] = 'Skipped an entry with no name.';

                    continue;
                }

                $castaway = Castaway::query()->firstOrNew([
                    'season_id' => $season->id,
                    'name' => $name,
                ]);

                $existed = $castaway->exists;

                // Only overwrite what this call actually supplied, so a quick
                // correcting call does not wipe the rest of the record.
                foreach (['nickname', 'occupation', 'hometown', 'tribe'] as $field) {
                    $value = $entry[$field] ?? null;

                    if ($value !== null && trim((string) $value) !== '') {
                        $castaway->{$field} = trim((string) $value);
                    }
                }

                if (isset($entry['age']) && $entry['age'] !== '') {
                    $castaway->age = (int) $entry['age'];
                }

                $castaway->save();

                $existed ? $updated++ : $created++;

                $results[] = ['name' => $castaway->name, 'status' => $existed ? 'updated' : 'created'];
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'cast_size' => $castSize = $season->castaways()->count(),
            'results' => $results,
            'warnings' => [...$warnings, ...$this->sanityWarnings($season, $castSize)],
        ];
    }

    /**
     * The draft length comes off the season's roster size, not off how many
     * castaways happen to exist, so a mismatch quietly shortens the draft.
     *
     * @return array<int, string>
     */
    private function sanityWarnings(Season $season, int $castSize): array
    {
        $warnings = [];

        if ($season->roster_size !== $castSize) {
            $warnings[] = sprintf(
                'Season %d is set to a roster of %d but now has %d castaways. The draft runs off the roster '
                .'size, so update it to %d unless the difference is deliberate.',
                $season->number,
                $season->roster_size,
                $castSize,
                $castSize,
            );
        }

        if ($season->draftPicks()->exists()) {
            $warnings[] = 'The draft is already under way, so anyone added now can only be taken '
                .'if picks remain.';
        }

        return $warnings;
    }
}
