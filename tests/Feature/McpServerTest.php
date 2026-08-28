<?php

use App\Models\Castaway;
use App\Models\Manager;
use App\Models\Season;
use App\Services\ScoringService;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config()->set('services.mcp.token', 'test-token');

    $this->season = Season::factory()->active()->create(['number' => 51, 'title' => 'The Open Era', 'roster_size' => 6]);
    app(ScoringService::class)->seedDefaultRules($this->season);

    Manager::factory()->create(['season_id' => $this->season->id, 'name' => 'Jyrki', 'draft_slot' => 1]);
    Manager::factory()->create(['season_id' => $this->season->id, 'name' => 'Rein', 'draft_slot' => 2]);

    foreach (['Jelly Aquino', 'Kilby Reeves', 'Thien An Do', 'Rob Mariano', 'Sara Novak', 'Tom Petit'] as $name) {
        Castaway::factory()->create(['season_id' => $this->season->id, 'name' => $name, 'tribe' => 'Lagi']);
    }
});

/**
 * @param  array<string, mixed>  $params
 */
function rpc(string $method, array $params = [], mixed $id = 1, ?string $token = 'test-token'): TestResponse
{
    $body = array_filter([
        'jsonrpc' => '2.0',
        'method' => $method,
        'params' => $params === [] ? null : $params,
        'id' => $id,
    ], fn (mixed $value): bool => $value !== null);

    $headers = ['Accept' => 'application/json, text/event-stream'];

    if ($token !== null) {
        $headers['Authorization'] = "Bearer {$token}";
    }

    return test()->postJson('/mcp', $body, $headers);
}

/**
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function callTool(string $name, array $arguments = []): array
{
    $response = rpc('tools/call', ['name' => $name, 'arguments' => $arguments]);
    $response->assertOk();

    $result = $response->json('result');

    return [
        'isError' => $result['isError'] ?? false,
        'text' => $result['content'][0]['text'] ?? '',
        'structured' => $result['structuredContent'] ?? null,
    ];
}

describe('authentication', function () {
    it('rejects a request with no token', function () {
        rpc('ping', token: null)->assertStatus(401);
    });

    it('rejects a wrong token', function () {
        rpc('ping', token: 'nope')->assertStatus(401);
    });

    it('accepts the token as a bearer header', function () {
        rpc('ping')->assertOk();
    });

    it('accepts the token as a path segment, for the connector UI', function () {
        $this->postJson('/mcp/test-token', ['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 1], [
            'Accept' => 'application/json, text/event-stream',
        ])->assertOk();
    });

    it('rejects a wrong token in the path', function () {
        $this->postJson('/mcp/wrong', ['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 1], [
            'Accept' => 'application/json, text/event-stream',
        ])->assertStatus(401);
    });

    it('refuses everything when no token is configured', function () {
        config()->set('services.mcp.token', null);

        rpc('ping')->assertStatus(401);
    });
});

describe('the handshake', function () {
    it('initializes and echoes back a protocol version', function () {
        $response = rpc('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Survivor Fantasy Draft')
            ->assertJsonPath('result.protocolVersion', '2025-06-18');
    });

    it('answers resources/list and prompts/list rather than method-not-found, because clients probe them', function () {
        rpc('resources/list')->assertOk()->assertJsonPath('result.resources', []);
        rpc('prompts/list')->assertOk()->assertJsonPath('result.prompts', []);
    });

    it('gives a notification no body and a 202', function () {
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ], ['Authorization' => 'Bearer test-token', 'Accept' => 'application/json, text/event-stream']);

        $response->assertStatus(202);
        expect($response->getContent())->toBe('');
    });

    it('lists every tool', function () {
        $names = collect(rpc('tools/list')->assertOk()->json('result.tools'))->pluck('name');

        expect($names)->toHaveCount(19)
            ->and($names)->toContain('get_scoreboard', 'record_episode', 'make_draft_pick', 'set_castaway_photos');
    });
});

describe('tool failures', function () {
    it('comes back as a result with isError, not a JSON-RPC error, so the model can recover', function () {
        $result = callTool('make_draft_pick', ['manager' => 'Rein', 'castaway' => 'Jelly']);

        expect($result['isError'])->toBeTrue()
            ->and($result['text'])->toContain("It is Jyrki's pick, not Rein's.");

        rpc('tools/call', ['name' => 'make_draft_pick', 'arguments' => ['manager' => 'Rein', 'castaway' => 'Jelly']])
            ->assertOk()
            ->assertJsonMissingPath('error');
    });

    it('names the candidates when a castaway name is ambiguous', function () {
        Castaway::factory()->create(['season_id' => $this->season->id, 'name' => 'Sara Reeves']);

        $result = callTool('make_draft_pick', ['manager' => 'Jyrki', 'castaway' => 'Reeves']);

        expect($result['isError'])->toBeTrue()
            ->and($result['text'])->toContain('Kilby Reeves, Sara Reeves');
    });

    it('reports a missing active season in plain English', function () {
        $this->season->update(['is_active' => false]);

        $result = callTool('get_scoreboard');

        expect($result['isError'])->toBeTrue()
            ->and($result['text'])->toContain('There is no active season.');
    });
});

describe('the tools', function () {
    it('returns a scoreboard', function () {
        $result = callTool('get_scoreboard');

        expect($result['isError'])->toBeFalse()
            ->and($result['structured']['season']['title'])->toBe('The Open Era')
            ->and($result['structured']['episodes_aired'])->toBe(0)
            ->and($result['structured']['standings'])->toHaveCount(2);
    });

    it('runs a draft through the alternating order', function () {
        expect(callTool('get_draft_board')['structured']['on_the_clock'])->toBe('Jyrki');

        callTool('make_draft_pick', ['manager' => 'Jyrki', 'castaway' => 'Jelly']);
        $second = callTool('make_draft_pick', ['manager' => 'Rein', 'castaway' => 'Kilby']);

        expect($second['structured']['pick_number'])->toBe(2)
            ->and($second['structured']['on_the_clock'])->toBe('Jyrki');

        $undone = callTool('undo_last_pick');

        expect($undone['structured']['undone']['castaway'])->toBe('Kilby Reeves')
            ->and($undone['structured']['on_the_clock'])->toBe('Rein');
    });

    it('locks the draft once an episode is recorded, and says so on the board', function () {
        callTool('make_draft_pick', ['manager' => 'Jyrki', 'castaway' => 'Jelly']);

        expect(callTool('get_draft_board')['structured']['locked'])->toBeFalse();

        callTool('record_episode', ['episode' => 1]);

        expect(callTool('get_draft_board')['structured']['locked'])->toBeTrue();

        $refused = callTool('make_draft_pick', ['manager' => 'Rein', 'castaway' => 'Kilby']);

        expect($refused['isError'])->toBeTrue()
            ->and($refused['text'])->toContain('the draft is locked');
    });

    it('sets the draft order after the coin flip', function () {
        $result = callTool('set_draft_order', ['first_pick' => 'Rein']);

        expect($result['structured']['on_the_clock'])->toBe('Rein');
    });

    it('records an episode and hands back the standings', function () {
        callTool('make_draft_pick', ['manager' => 'Jyrki', 'castaway' => 'Rob']);

        $result = callTool('record_episode', [
            'episode' => 1,
            'title' => 'The Open Era Begins',
            'voted_out' => ['Jelly'],
            'idol_found' => ['Rob'],
        ]);

        expect($result['isError'])->toBeFalse()
            ->and($result['structured']['episode']['number'])->toBe(1)
            ->and($result['structured']['recorded'])->toHaveCount(2)
            ->and($result['structured']['still_in'])->toHaveCount(5)
            ->and($result['structured']['standings'][0]['points'])->toBe(1 + 3);
    });

    it('warns without failing when an award lands on someone already out', function () {
        callTool('record_episode', ['episode' => 1, 'voted_out' => ['Jelly']]);
        $result = callTool('record_episode', ['episode' => 2, 'individual_immunity' => ['Jelly']]);

        expect($result['isError'])->toBeFalse()
            ->and($result['structured']['warnings'][0])->toContain('already out before episode 2');
    });

    it('finds an event id and deletes it', function () {
        callTool('record_episode', ['episode' => 1, 'idol_found' => ['Rob']]);

        $events = callTool('list_events', ['castaway' => 'Rob']);
        $eventId = $events['structured']['events'][0]['event_id'];

        $deleted = callTool('delete_event', ['event_id' => $eventId]);

        expect($deleted['structured']['castaway'])->toBe('Rob Mariano')
            ->and(callTool('list_events', ['castaway' => 'Rob'])['structured']['count'])->toBe(0);
    });

    it('records a single event for a correction', function () {
        callTool('record_episode', ['episode' => 1]);

        $result = callTool('record_event', ['castaway' => 'Rob', 'type' => 'idol_found']);

        expect($result['structured']['episode'])->toBe(1)
            ->and($result['structured']['castaway_points_now'])->toBe(1 + 3);
    });

    it('refuses to record survival, which is derived', function () {
        $result = callTool('record_event', ['castaway' => 'Rob', 'type' => 'survived', 'episode' => 1]);

        expect($result['isError'])->toBeTrue()
            ->and($result['text'])->toContain('not a recordable event type');
    });

    it('sets the final placements', function () {
        callTool('record_episode', ['episode' => 1]);

        $result = callTool('set_final_placements', [
            'winner' => 'Rob', 'runner_up' => 'Sara', 'third' => 'Tom',
        ]);

        expect($result['structured']['placements'])->toHaveCount(3)
            ->and($result['structured']['placements'][0]['castaway'])->toBe('Rob Mariano');
    });

    it('lists the cast and filters by status', function () {
        callTool('record_episode', ['episode' => 1, 'voted_out' => ['Jelly']]);

        expect(callTool('list_castaways')['structured']['count'])->toBe(6)
            ->and(callTool('list_castaways', ['status' => 'out'])['structured']['count'])->toBe(1)
            ->and(callTool('list_castaways', ['status' => 'in'])['structured']['count'])->toBe(5);
    });

    it('assigns tribes so immunity can be recorded by tribe name', function () {
        callTool('set_tribes', ['assignments' => [
            ['castaway' => 'Rob', 'tribe' => 'Vula'],
            ['castaway' => 'Sara', 'tribe' => 'Vula'],
        ]]);

        $result = callTool('record_episode', ['episode' => 1, 'tribe_immunity' => ['Vula']]);

        expect($result['structured']['recorded'])->toHaveCount(2);
    });

    it('re-scores the season when a rule changes', function () {
        callTool('make_draft_pick', ['manager' => 'Jyrki', 'castaway' => 'Rob']);
        callTool('record_episode', ['episode' => 1, 'idol_found' => ['Rob']]);

        $result = callTool('set_scoring_rule', ['type' => 'idol_found', 'points' => 10]);

        expect($result['structured']['scoreboard']['standings'][0]['points'])->toBe(1 + 10);
    });

    it('reads the scoring rules', function () {
        $rules = collect(callTool('get_scoring_rules')['structured']['rules'])->keyBy('type');

        expect($rules['winner']['points'])->toBe(25)->and($rules['survived']['points'])->toBe(1);
    });

    it('creates and activates another season without touching this one', function () {
        $created = callTool('create_season', [
            'number' => 52,
            'title' => 'Next One',
            'roster_size' => 18,
            'managers' => ['Jyrki', 'Rein'],
        ]);

        expect($created['structured']['draft_rounds'])->toBe(9)
            ->and($created['structured']['total_picks'])->toBe(18)
            ->and(callTool('get_scoreboard')['structured']['season']['number'])->toBe(52);

        callTool('set_active_season', ['season' => 51]);

        expect(callTool('get_scoreboard')['structured']['season']['number'])->toBe(51)
            ->and(callTool('list_seasons')['structured']['count'])->toBe(2);
    });

    it('targets a named season instead of the active one', function () {
        callTool('create_season', ['number' => 52, 'managers' => ['A', 'B'], 'make_active' => false]);

        expect(callTool('get_scoreboard', ['season' => 52])['structured']['season']['number'])->toBe(52)
            ->and(callTool('get_scoreboard')['structured']['season']['number'])->toBe(51);
    });
});
