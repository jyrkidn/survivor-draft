<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;

final class SurvivorDraftServer extends Server
{
    protected string $name = 'Survivor Fantasy Draft';

    protected string $version = '1.0.0';

    /**
     * Every tool fits in one page. A client that does not follow the cursor
     * would otherwise simply not see the last four.
     */
    public int $defaultPaginationLength = 50;

    protected string $instructions = <<<'MARKDOWN'
    A two-person fantasy Survivor league: an alternating draft before the season,
    then episode results entered conversationally while watching.

    How to use this well:

    - Every tool defaults to the active season. Only pass `season` if asked about
      a different one.
    - Castaway names resolve loosely. "Jelly", "Kilby", "Thien An" and "Rob" all
      work. If a name is ambiguous the tool says so and names the candidates —
      ask which one rather than guessing.
    - `record_episode` is the workhorse. Give it everything that happened in one
      call. Calling it a second time for the same episode *replaces* that
      episode, so correcting a mistake means saying the whole episode again, not
      deleting events one by one.
    - Nothing stores a score. Survival points are derived from the eliminations
      and the number of aired episodes, so never try to record "survived" — it is
      automatic. Fixing a wrong event with `delete_event` re-scores everything
      downstream on its own.
    - Warnings are not failures. An award to someone already eliminated is
      recorded and flagged, because the episode number is usually what is wrong.
    MARKDOWN;

    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        Tools\GetScoreboard::class,
        Tools\GetDraftBoard::class,
        Tools\MakeDraftPick::class,
        Tools\UndoLastPick::class,
        Tools\SetDraftOrder::class,
        Tools\RecordEpisode::class,
        Tools\RecordEvent::class,
        Tools\ListEvents::class,
        Tools\DeleteEvent::class,
        Tools\SetFinalPlacements::class,
        Tools\ListCastaways::class,
        Tools\SetTribes::class,
        Tools\SetCastawayPhoto::class,
        Tools\SetCastawayPhotos::class,
        Tools\GetScoringRules::class,
        Tools\SetScoringRule::class,
        Tools\ListSeasons::class,
        Tools\CreateSeason::class,
        Tools\SetActiveSeason::class,
    ];
}
