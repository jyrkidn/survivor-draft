<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CastTile from '@/components/league/CastTile.vue';
import LeagueHeader from '@/components/league/LeagueHeader.vue';
import LeagueNav from '@/components/league/LeagueNav.vue';
import type { CastawayCard } from '@/types/league';

const props = defineProps<{
    season: { number: number; title: string | null };
    castaways: CastawayCard[];
    tribes: string[];
    aliveCount: number;
    outCount: number;
}>();

const activeTribe = ref<string | null>(null);

/** Filters subset the tribe-then-draft-position order; they never reorder it. */
const visible = computed(() =>
    activeTribe.value === null
        ? props.castaways
        : props.castaways.filter(
              (castaway) => castaway.tribe === activeTribe.value,
          ),
);
</script>

<template>
    <Head title="Cast" />

    <div class="min-h-screen w-full bg-ink">
        <LeagueHeader
            short-title="CAST"
            :title="`${(season.title ?? 'SEASON ' + season.number).toUpperCase()} — CAST`"
            :meta="`${aliveCount} ALIVE · ${outCount} OUT`"
        />
        <LeagueNav />

        <div
            class="flex flex-wrap border-b border-chart-line-dim px-4 py-2.5 sm:px-6 lg:px-10"
        >
            <button
                type="button"
                class="pr-4 font-mono text-[10px] tracking-[0.14em]"
                :class="activeTribe === null ? 'text-sand' : 'text-dead'"
                @click="activeTribe = null"
            >
                ALL
            </button>
            <button
                v-for="tribe in tribes"
                :key="tribe"
                type="button"
                class="pr-4 font-mono text-[10px] tracking-[0.14em] last:pr-0"
                :class="activeTribe === tribe ? 'text-sand' : 'text-dead'"
                @click="activeTribe = tribe"
            >
                {{ tribe.toUpperCase() }}
            </button>
        </div>

        <!-- Columns are added as the window allows, never taken away: the 160px
             floor keeps every photo at least the size it is on a phone. -->
        <div
            class="grid grid-cols-[repeat(auto-fill,minmax(160px,1fr))] gap-[14px] px-4 pt-4 pb-[22px] sm:gap-4 sm:px-6 lg:px-10"
        >
            <CastTile
                v-for="castaway in visible"
                :key="castaway.id"
                size="cast"
                show-owner
                :name="castaway.name"
                :age="castaway.age"
                :occupation="castaway.occupation"
                :tribe="castaway.tribe"
                :photo-url="castaway.photo_url"
                :status="castaway.status"
                :exit-episode="castaway.exit_episode"
                :owner-initial="castaway.owner_initial"
                :owner-name="castaway.owner_name"
                :owner-slot="castaway.owner_slot"
            />
        </div>

        <p
            v-if="visible.length === 0"
            class="px-4 pb-8 font-mono text-[11px] tracking-[0.14em] text-dead sm:px-6 lg:px-10"
        >
            NO CASTAWAYS YET
        </p>
    </div>
</template>
