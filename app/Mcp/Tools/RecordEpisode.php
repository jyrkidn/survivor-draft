<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\EpisodeRecording;
use App\Enums\EventType;
use App\Services\EpisodeRecorder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class RecordEpisode extends LeagueTool
{
    protected string $name = 'record_episode';

    protected string $description = <<<'TEXT'
    Record everything that happened in one episode, in one call. This is the main tool.

    Calling it twice for the same episode REPLACES that episode rather than adding to it, so
    the way to fix a mistake is to say the whole episode again with the correction. That also
    makes it safe to call while the episode is still airing and top it up afterwards.

    Names resolve loosely, so "Jelly" or "Kilby" is enough. Tribe immunity entries are matched
    against tribe names first, so "Lagi" awards every living member of Lagi.

    Do not record survival — survival points are derived from the eliminations and the number
    of aired episodes automatically.
    TEXT;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $nameList = fn (string $description) => $schema->array()
            ->items($schema->string())
            ->description($description);

        return [
            'episode' => $schema->integer()->description('Episode number, starting at 1.')->required(),
            'title' => $schema->string()->description('Episode title, if known.'),
            'air_date' => $schema->string()->description('Air date, YYYY-MM-DD.'),
            'notes' => $schema->string()->description('Anything worth remembering about the episode.'),
            'merge' => $schema->boolean()->description(
                'True if the merge happened this episode. Awards the merge bonus to everyone who started it.'
            ),
            'voted_out' => $nameList('Castaways voted out at tribal council.'),
            'quit' => $nameList('Castaways who quit.'),
            'medevac' => $nameList('Castaways medically evacuated.'),
            'lost_firemaking' => $nameList('Castaways eliminated by losing a fire-making challenge.'),
            'individual_immunity' => $nameList('Castaways who won individual immunity.'),
            'tribe_immunity' => $nameList('Tribes that won immunity. A tribe name awards every living member; '
                .'a castaway name works too.'),
            'idol_found' => $nameList('Castaways who found a hidden immunity idol.'),
            'idol_played' => $nameList('Castaways who played an idol that actually voided votes.'),
            'advantage_found' => $nameList('Castaways who found an advantage.'),
            'fire_win' => $nameList('Castaways who won a fire-making challenge.'),
            'season' => $this->seasonProperty($schema),
        ];
    }

    protected function run(Request $request): ResponseFactory
    {
        $request->validate(['episode' => ['required', 'integer', 'min:1']]);

        $season = $this->season($request);

        $input = ['episode' => $request->get('episode')];

        foreach (['title', 'air_date', 'notes', 'merge'] as $key) {
            if ($request->get($key) !== null) {
                $input[$key] = $request->get($key);
            }
        }

        foreach (EventType::recordableInBulk() as $type) {
            if ($request->get($type->value) !== null) {
                $input[$type->value] = $request->get($type->value);
            }
        }

        $result = app(EpisodeRecorder::class)->record($season, EpisodeRecording::fromArray($input));

        return $this->respond($result->toArray());
    }
}
