<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CastTile from '@/components/league/CastTile.vue';
import DraftBoard from '@/components/league/DraftBoard.vue';
import LeagueHeader from '@/components/league/LeagueHeader.vue';
import LeagueNav from '@/components/league/LeagueNav.vue';
import { managerColour, pad } from '@/lib/managers';
import { pick as pickRoute, undo as undoRoute } from '@/routes/draft';
import type {
    AvailableCastaway,
    DraftPickRecord,
    ManagerRef,
} from '@/types/league';

const props = defineProps<{
    season: {
        number: number;
        title: string | null;
        premiere_date: string | null;
        premiere_label: string | null;
        roster_size: number;
    };
    managers: (ManagerRef & { picks_made: number })[];
    picks: DraftPickRecord[];
    onTheClock: ManagerRef | null;
    nextPickNumber: number | null;
    totalPicks: number;
    rounds: number;
    isComplete: boolean;
    isLocked: boolean;
    episodesAired: number;
    available: AvailableCastaway[];
    castLocked: number;
    daysToPremiere: number | null;
}>();

const page = usePage();

// Pre-draft is simply "no picks yet". Opening the draft is a local reveal, not
// a stored state — reload with nothing picked and you are back here.
const opened = ref(false);
const preDraft = computed(
    () => props.picks.length === 0 && !opened.value && !props.isLocked,
);

/** Picks can only be made while the draft is neither locked nor finished. */
const isOpen = computed(() => !props.isLocked && !props.isComplete);

const submitting = ref<number | null>(null);

const flashError = computed(
    () => (page.props.flash as { error?: string } | undefined)?.error ?? null,
);

const firstPickManager = computed(
    () => props.managers.find((manager) => manager.slot === 1) ?? null,
);

const clockColour = computed(() => managerColour(props.onTheClock?.slot));

const currentRound = computed(() =>
    props.nextPickNumber === null
        ? props.rounds
        : Math.ceil(props.nextPickNumber / 2),
);

// Both managers draft from this one screen, so the button is always live and
// the pick lands on whoever the snake order has on the clock.
const pick = (castawayId: number): void => {
    submitting.value = castawayId;

    router.post(
        pickRoute().url,
        { castaway_id: castawayId },
        {
            preserveScroll: true,
            onFinish: () => (submitting.value = null),
        },
    );
};

const undo = (): void => {
    router.post(undoRoute().url, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Draft" />

    <div class="min-h-screen w-full chart-grid">
        <LeagueHeader
            short-title="DRAFT"
            :title="`${(season.title ?? 'SEASON ' + season.number).toUpperCase()} — DRAFT`"
            :meta="
                season.premiere_label
                    ? `PREMIERE ${season.premiere_label.toUpperCase()}`
                    : null
            "
        />
        <LeagueNav />

        <!-- Locked: the season has started, so the page is a record now. -->
        <div
            v-if="isLocked"
            class="flex flex-wrap items-center justify-between gap-4 border-b border-l-[6px] border-chart-line border-l-sand px-4 py-5 sm:px-6 lg:px-10 lg:py-[26px]"
        >
            <div class="flex flex-col gap-1.5">
                <span
                    class="font-mono text-xs tracking-[0.2em] text-sand-muted"
                >
                    DRAFT LOCKED
                </span>
                <span
                    class="font-display text-[28px] leading-none font-bold tracking-[0.02em] text-sand sm:text-[36px] lg:text-[46px]"
                >
                    ROSTERS ARE FINAL
                </span>
            </div>
            <span
                class="max-w-md font-mono text-[11px] leading-relaxed tracking-[0.14em] text-dead"
            >
                THE SEASON IS UNDER WAY — {{ episodesAired }}
                {{ episodesAired === 1 ? 'EPISODE' : 'EPISODES' }} RECORDED.
                DELETE THEM IN THE ADMIN PANEL IF A PICK REALLY HAS TO CHANGE.
            </span>
        </div>

        <!-- Pre-draft banner -->
        <div
            v-else-if="preDraft"
            class="flex flex-wrap items-center justify-between gap-4 border-b border-l-[6px] border-chart-line border-l-sand px-4 py-5 sm:px-6 lg:px-10 lg:py-[26px]"
        >
            <div class="flex flex-col gap-1.5">
                <span
                    class="font-mono text-xs tracking-[0.2em] text-sand-muted"
                >
                    DRAFT NOT STARTED
                </span>
                <span
                    class="font-display text-[28px] leading-none font-bold tracking-[0.02em] text-sand sm:text-[36px] lg:text-[42px]"
                >
                    {{ (firstPickManager?.name ?? 'NOBODY').toUpperCase() }}
                    PICKS FIRST
                </span>
            </div>
            <button
                type="button"
                class="cursor-pointer bg-sand px-[26px] py-4 font-mono text-xs tracking-[0.2em] text-ink transition-colors hover:bg-sand-bright"
                @click="opened = true"
            >
                OPEN THE DRAFT
            </button>
        </div>

        <!-- Turn banner -->
        <div
            v-else-if="onTheClock"
            class="flex flex-wrap items-center justify-between gap-x-8 gap-y-4 border-b border-l-[6px] border-chart-line px-4 py-5 sm:px-6 lg:px-10 lg:py-[26px]"
            :style="{
                '--mgr': clockColour,
                borderLeftColor: clockColour,
                backgroundImage: `linear-gradient(90deg, color-mix(in srgb, ${clockColour} 14%, transparent), transparent)`,
            }"
        >
            <div class="flex flex-wrap items-baseline gap-x-5 gap-y-1">
                <span
                    class="font-mono text-xs tracking-[0.2em] text-[color:var(--mgr)]"
                >
                    ON THE CLOCK
                </span>
                <span
                    class="font-display text-[28px] leading-none font-bold tracking-[0.02em] text-sand sm:text-[36px] lg:text-[46px]"
                >
                    {{ onTheClock.name.toUpperCase() }}
                </span>
            </div>
            <div class="flex items-center gap-6 lg:gap-8">
                <div class="flex flex-col items-end gap-0.5">
                    <span
                        class="font-mono text-[10px] tracking-[0.18em] text-sand-muted"
                    >
                        PICK
                    </span>
                    <span
                        class="font-mono text-2xl leading-none font-medium text-sand lg:text-[30px]"
                    >
                        {{ pad(nextPickNumber ?? totalPicks)
                        }}<span class="text-dead">/{{ totalPicks }}</span>
                    </span>
                </div>
                <div class="flex flex-col items-end gap-0.5">
                    <span
                        class="font-mono text-[10px] tracking-[0.18em] text-sand-muted"
                    >
                        ROUND
                    </span>
                    <span
                        class="font-mono text-2xl leading-none font-medium text-sand lg:text-[30px]"
                    >
                        {{ pad(currentRound)
                        }}<span class="text-dead">/{{ rounds }}</span>
                    </span>
                </div>
                <button
                    v-if="picks.length > 0 && !isLocked"
                    type="button"
                    class="cursor-pointer border border-chart-line-empty px-4 py-2 font-mono text-[10px] tracking-[0.18em] text-sand-muted transition-colors hover:text-sand"
                    @click="undo"
                >
                    UNDO
                </button>
            </div>
        </div>

        <!-- Draft complete -->
        <div
            v-else
            class="flex flex-wrap items-center justify-between gap-4 border-b border-l-[6px] border-chart-line border-l-sand px-4 py-5 sm:px-6 lg:px-10 lg:py-[26px]"
        >
            <span
                class="font-display text-[28px] leading-none font-bold tracking-[0.02em] text-sand sm:text-[36px] lg:text-[46px]"
            >
                DRAFT COMPLETE
            </span>
            <button
                v-if="!isLocked"
                type="button"
                class="cursor-pointer border border-chart-line-empty px-4 py-2 font-mono text-[10px] tracking-[0.18em] text-sand-muted transition-colors hover:text-sand"
                @click="undo"
            >
                UNDO LAST PICK
            </button>
        </div>

        <p
            v-if="flashError"
            class="border-b border-l-[6px] border-chart-line border-l-mgr-a px-4 py-3 font-mono text-[11px] tracking-[0.14em] text-mgr-a sm:px-6 lg:px-10"
        >
            {{ flashError.toUpperCase() }}
        </p>

        <div class="flex flex-col xl:flex-row xl:items-stretch">
            <div
                class="px-4 pt-6 pb-8 sm:px-6 lg:px-10 xl:w-[42%] xl:shrink-0 xl:border-r xl:border-chart-line"
                :class="preDraft ? 'xl:w-[55%]' : ''"
            >
                <div class="mb-3.5 flex w-full">
                    <div
                        v-for="manager in managers"
                        :key="manager.id"
                        class="flex flex-1 flex-col items-center gap-1"
                    >
                        <span
                            class="font-display text-[22px] font-bold tracking-[0.04em]"
                            :style="{ color: managerColour(manager.slot) }"
                        >
                            {{ manager.name.toUpperCase() }}
                        </span>
                        <span
                            v-if="!preDraft"
                            class="font-mono text-[10px] tracking-[0.14em] text-sand-muted"
                        >
                            {{ manager.picks_made }} PICKED
                        </span>
                    </div>
                </div>

                <DraftBoard
                    :picks="picks"
                    :total-picks="totalPicks"
                    :next-pick-number="nextPickNumber"
                    :clock-slot="onTheClock?.slot ?? null"
                    :pre-draft="preDraft"
                />
            </div>

            <!-- Pre-draft: the available grid is replaced by the rules. -->
            <div
                v-if="preDraft"
                class="flex flex-col gap-4 px-4 pb-8 sm:px-6 lg:px-10 xl:flex-1 xl:pt-6"
            >
                <div class="flex flex-col gap-2">
                    <span
                        class="font-display text-xl font-bold tracking-[0.06em] text-sand"
                    >
                        HOW IT RUNS
                    </span>
                    <p
                        class="m-0 max-w-prose font-body text-sm leading-[1.65] text-sand-muted"
                        style="text-wrap: pretty"
                    >
                        {{ rounds }} rounds, straight alternating — nobody picks
                        twice in a row. One castaway goes undrafted.
                        {{ firstPickManager?.name ?? 'Nobody' }} won the coin
                        flip and picks first. Nothing is locked until both of
                        you are on this page.
                    </p>
                </div>
                <div
                    class="flex max-w-md flex-col gap-2.5 border-t border-chart-line-dim pt-4"
                >
                    <div class="flex items-baseline justify-between">
                        <span
                            class="font-mono text-[11px] tracking-[0.14em] text-sand-muted"
                        >
                            CAST LOCKED
                        </span>
                        <span class="font-mono text-base text-sand">
                            {{ castLocked }}
                        </span>
                    </div>
                    <div class="flex items-baseline justify-between">
                        <span
                            class="font-mono text-[11px] tracking-[0.14em] text-sand-muted"
                        >
                            PICKS EACH
                        </span>
                        <span class="font-mono text-base text-sand">
                            {{ rounds }}
                        </span>
                    </div>
                    <div
                        v-if="daysToPremiere !== null"
                        class="flex items-baseline justify-between"
                    >
                        <span
                            class="font-mono text-[11px] tracking-[0.14em] text-sand-muted"
                        >
                            DAYS TO PREMIERE
                        </span>
                        <span class="font-mono text-base text-sand">
                            {{ daysToPremiere }}
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="flex flex-1 flex-col border-t border-chart-line xl:border-t-0"
            >
                <div
                    class="flex items-baseline justify-between px-4 pt-5 pb-3.5 sm:px-6 lg:px-10 xl:pt-[22px]"
                >
                    <span
                        class="font-display text-[22px] font-bold tracking-[0.06em] text-sand"
                    >
                        {{ isOpen ? 'AVAILABLE' : 'UNDRAFTED' }}
                    </span>
                    <span
                        class="font-mono text-xs tracking-[0.14em] text-sand-muted"
                    >
                        {{ available.length }} OF {{ season.roster_size }}
                    </span>
                </div>

                <div
                    class="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-x-4 gap-y-[18px] px-4 pb-8 sm:px-6 lg:px-10"
                >
                    <CastTile
                        v-for="castaway in available"
                        :key="castaway.id"
                        size="draft"
                        :name="castaway.name"
                        :age="castaway.age"
                        :occupation="castaway.occupation"
                        :tribe="castaway.tribe"
                        :photo-url="castaway.photo_url"
                    >
                        <template v-if="isOpen" #action>
                            <!-- Carries the colour of whoever is on the clock,
                                 so the button says whose pick it is. -->
                            <button
                                type="button"
                                :disabled="submitting !== null"
                                class="w-full cursor-pointer border-none py-2 font-mono text-[10px] tracking-[0.18em] text-ink transition-opacity hover:opacity-85 disabled:cursor-not-allowed disabled:opacity-50"
                                :style="{ background: clockColour }"
                                @click="pick(castaway.id)"
                            >
                                DRAFT{{
                                    onTheClock
                                        ? ` FOR ${onTheClock.name.toUpperCase()}`
                                        : ''
                                }}
                            </button>
                        </template>
                    </CastTile>
                </div>
            </div>
        </div>
    </div>
</template>
