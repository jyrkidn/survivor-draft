<?php

use App\Exceptions\AmbiguousCastawayException;
use App\Exceptions\CastawayNotFoundException;
use App\Models\Castaway;
use App\Models\Season;
use App\Services\CastawayResolver;

beforeEach(function () {
    $this->season = Season::factory()->create();
    $this->resolver = app(CastawayResolver::class);

    foreach ([
        ['name' => 'Jelly Aquino', 'nickname' => 'Jelly'],
        ['name' => 'Kilby Reeves', 'nickname' => null],
        ['name' => 'Thien An Do', 'nickname' => null],
        ['name' => 'Rob Mariano', 'nickname' => 'Boston Rob'],
    ] as $attributes) {
        Castaway::factory()->create([...$attributes, 'season_id' => $this->season->id]);
    }
});

function resolveName(string $name): Castaway
{
    return test()->resolver->resolve(test()->season, $name);
}

it('matches a full name', function () {
    expect(resolveName('Jelly Aquino')->name)->toBe('Jelly Aquino');
});

it('matches regardless of case and stray whitespace', function () {
    expect(resolveName('  kilby   REEVES ')->name)->toBe('Kilby Reeves');
});

it('matches a nickname', function () {
    expect(resolveName('Boston Rob')->name)->toBe('Rob Mariano');
});

it('matches a first name', function () {
    expect(resolveName('Rob')->name)->toBe('Rob Mariano');
});

it('matches a last name', function () {
    expect(resolveName('Mariano')->name)->toBe('Rob Mariano');
});

it('matches a partial name that no earlier tier catches', function () {
    expect(resolveName('Thien An')->name)->toBe('Thien An Do');
});

it('errors when nothing matches', function () {
    resolveName('Probst');
})->throws(CastawayNotFoundException::class, 'No castaway matches "Probst".');

it('errors on an empty name', function () {
    resolveName('   ');
})->throws(CastawayNotFoundException::class);

it('lists the candidates when a name is ambiguous', function () {
    Castaway::factory()->create(['name' => 'Sara Reeves', 'season_id' => $this->season->id]);

    expect(fn () => resolveName('Reeves'))
        ->toThrow(
            AmbiguousCastawayException::class,
            '"Reeves" matches more than one castaway: Kilby Reeves, Sara Reeves. Which one did you mean?'
        );
});

it('lets an exact nickname settle a first name two castaways share', function () {
    Castaway::factory()->create(['name' => 'Jelly Roberts', 'season_id' => $this->season->id]);

    expect(resolveName('Jelly')->name)->toBe('Jelly Aquino');
});

it('prefers an exact full name over a substring of someone else', function () {
    Castaway::factory()->create(['name' => 'Rob Mariano Jr', 'season_id' => $this->season->id]);

    expect(resolveName('Rob Mariano')->name)->toBe('Rob Mariano');
});

it('returns null instead of throwing when asked to try', function () {
    $castaways = Castaway::query()->where('season_id', $this->season->id)->get();

    expect($this->resolver->tryResolveIn($castaways, 'Probst'))->toBeNull()
        ->and($this->resolver->tryResolveIn($castaways, 'Kilby')?->name)->toBe('Kilby Reeves');
});

it('does not resolve a castaway from another season', function () {
    $other = Season::factory()->create();
    Castaway::factory()->create(['name' => 'Somebody Else', 'season_id' => $other->id]);

    $this->resolver->resolve($this->season, 'Somebody Else');
})->throws(CastawayNotFoundException::class);
