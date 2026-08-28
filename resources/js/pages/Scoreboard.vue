<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import GapChart from '@/components/league/GapChart.vue';
import LeagueHeader from '@/components/league/LeagueHeader.vue';
import LeagueNav from '@/components/league/LeagueNav.vue';
import ManagerTotal from '@/components/league/ManagerTotal.vue';
import RosterRow from '@/components/league/RosterRow.vue';
import { managerColour } from '@/lib/managers';
import type {
    EpisodeGap,
    ManagerTotalRecord,
    RosterRecord,
} from '@/types/league';

const props = defineProps<{
    season: {
        number: number;
        title: string | null;
        premiere_label: string | null;
    };
    episodesAired: number;
    seasonStarted: boolean;
    verdict: {
        level: boolean;
        gap: number;
        leader_name: string | null;
        leader_slot: number | null;
    };
    totals: ManagerTotalRecord[];
    gapByEpisode: EpisodeGap[];
    rosters: RosterRecord[];
}>();

const headerMeta = computed(() =>
    props.seasonStarted
        ? `AFTER EP ${String(props.episodesAired).padStart(2, '0')}`
        : 'NOT STARTED',
);
</script>

<template>
    <Head title="Scoreboard" />

    <div class="min-h-screen w-full bg-ink">
        <LeagueHeader
            short-title="SCOREBOARD"
            :title="`${(season.title ?? 'SEASON ' + season.number).toUpperCase()} — SCOREBOARD`"
            :meta="headerMeta"
        />
        <LeagueNav />

        <!-- Answers "am I winning" before anything else on the page. Wide
             enough, the verdict and the two totals sit on one line. -->
        <div
            class="border-b border-chart-line-dim px-4 pt-[22px] pb-5 sm:px-6 lg:flex lg:items-center lg:gap-12 lg:px-10 lg:py-8"
        >
            <div
                class="mb-[18px] flex items-baseline gap-2.5 lg:mb-0 lg:shrink-0"
            >
                <span
                    v-if="verdict.level"
                    class="font-display text-[52px] leading-none font-bold text-sand"
                >
                    LEVEL
                </span>
                <template v-else>
                    <span
                        class="font-display text-[52px] leading-none font-bold"
                        :style="{ color: managerColour(verdict.leader_slot) }"
                    >
                        +{{ verdict.gap }}
                    </span>
                    <span
                        class="font-display text-[22px] font-bold tracking-[0.04em] text-sand"
                    >
                        {{ verdict.leader_name?.toUpperCase() }} LEADS
                    </span>
                </template>
            </div>

            <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:gap-8">
                <ManagerTotal
                    v-for="total in totals"
                    :key="total.id"
                    :total="total"
                    class="flex-1"
                />
            </div>
        </div>

        <GapChart v-if="gapByEpisode.length > 0" :history="gapByEpisode" />

        <div
            v-else
            class="border-b border-chart-line-dim px-4 py-4 font-mono text-[10px] tracking-[0.18em] text-sand-muted sm:px-6 lg:px-10"
        >
            SEASON STARTS {{ season.premiere_label?.toUpperCase() ?? 'TBC' }}
        </div>

        <!-- The two rosters stack on a phone and sit side by side once there
             is room, rather than the page growing ever longer. -->
        <div class="grid gap-x-10 md:grid-cols-2">
            <div
                v-for="roster in rosters"
                :key="roster.id"
                class="px-4 pt-[18px] pb-6 sm:px-6 lg:px-10"
            >
                <div class="mb-2.5 flex items-baseline justify-between">
                    <span
                        class="font-mono text-[11px] tracking-[0.16em]"
                        :style="{ color: managerColour(roster.slot) }"
                    >
                        {{ roster.name.toUpperCase() }}
                    </span>
                    <span
                        class="font-mono text-[10px] tracking-[0.14em] text-sand-muted"
                    >
                        {{ roster.alive }} ALIVE
                    </span>
                </div>
                <div class="flex flex-col">
                    <RosterRow
                        v-for="entry in roster.castaways"
                        :key="entry.id"
                        :entry="entry"
                    />
                    <p
                        v-if="roster.castaways.length === 0"
                        class="border-t border-chart-line-dim py-2 font-mono text-[11px] tracking-[0.14em] text-dead"
                    >
                        NOTHING DRAFTED YET
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
