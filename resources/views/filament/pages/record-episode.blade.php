<x-filament-panels::page>
    @php($season = $this->season())
    @php($scoreboard = $this->currentScoreboard())

    @if ($season === null)
        <x-filament::section>
            <x-slot name="heading">No active season</x-slot>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Create a season and mark it active before recording episodes.
            </p>
        </x-filament::section>
    @else
        {{ $this->content }}

        @if ($scoreboard !== null)
            <x-filament::section>
                <x-slot name="heading">
                    Season {{ $season->number }} — {{ $scoreboard->episodesAired }}
                    {{ Str::plural('episode', $scoreboard->episodesAired) }} aired
                </x-slot>
                <x-slot name="description">{{ $scoreboard->leadSummary() }}</x-slot>

                <div class="grid gap-6 sm:grid-cols-2">
                    @foreach ($scoreboard->standings as $standing)
                        <div>
                            <p class="font-semibold text-gray-950 dark:text-white">
                                {{ $standing->manager->name }} — {{ $standing->totalPoints }} pts
                            </p>
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach ($standing->roster as $score)
                                    <li class="flex justify-between gap-4 {{ $score->isStillIn() ? '' : 'text-gray-400 line-through dark:text-gray-500' }}">
                                        <span>{{ $score->castaway->name }}</span>
                                        <span class="tabular-nums">{{ $score->totalPoints }}</span>
                                    </li>
                                @endforeach
                                @if ($standing->roster === [])
                                    <li class="text-gray-500 dark:text-gray-400">Nothing drafted yet.</li>
                                @endif
                            </ul>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
