<script setup lang="ts">
import { computed } from 'vue';
import { managerColour, signed } from '@/lib/managers';
import type { EpisodeGap } from '@/types/league';

const props = defineProps<{ history: EpisodeGap[] }>();

const HEIGHT = 56;

const widest = computed(() =>
    Math.max(1, ...props.history.map((entry) => Math.abs(entry.gap))),
);

/** Bars are bottom-aligned and scaled to the widest gap of the season. */
const barHeight = (gap: number): number =>
    Math.max(3, Math.round((Math.abs(gap) / widest.value) * HEIGHT));

const range = computed(() => {
    if (props.history.length === 0) {
        return '';
    }

    const first = props.history[0].episode;
    const last = props.history[props.history.length - 1].episode;

    return first === last ? `EP ${first}` : `EP ${first}–${last}`;
});

const columns = computed(() => ({
    gridTemplateColumns: `repeat(${props.history.length}, 1fr)`,
}));
</script>

<template>
    <div
        class="border-b border-chart-line-dim px-4 pt-[18px] pb-4 sm:px-6 lg:px-10"
    >
        <div class="mb-3 flex items-baseline justify-between">
            <span
                class="font-mono text-[10px] tracking-[0.18em] text-sand-muted"
            >
                GAP BY EPISODE
            </span>
            <span class="font-mono text-[10px] tracking-[0.14em] text-dead">
                {{ range }}
            </span>
        </div>

        <div class="grid items-end gap-2" :style="columns" style="height: 56px">
            <div
                v-for="entry in history"
                :key="entry.episode"
                class="flex h-[56px] flex-col justify-end"
            >
                <div
                    :style="{
                        height: `${barHeight(entry.gap)}px`,
                        background: managerColour(entry.leader_slot),
                    }"
                />
            </div>
        </div>

        <div class="mt-1.5 grid gap-2" :style="columns">
            <span
                v-for="entry in history"
                :key="entry.episode"
                class="text-center font-mono text-[10px]"
                :style="{ color: managerColour(entry.leader_slot) }"
            >
                {{ signed(entry.gap) }}
            </span>
        </div>
    </div>
</template>
