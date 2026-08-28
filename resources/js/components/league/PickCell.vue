<script setup lang="ts">
import { computed } from 'vue';
import type { BoardCell, BoardGeometry } from '@/lib/draftBoard';
import { managerColour, pad } from '@/lib/managers';
import type { DraftPickRecord } from '@/types/league';

const props = defineProps<{
    cell: BoardCell;
    geometry: BoardGeometry;
    pick?: DraftPickRecord | null;
    /** 'first' is the pre-draft highlight on pick 1. */
    state: 'made' | 'clock' | 'future' | 'first';
    clockSlot?: number | null;
}>();

const position = computed(() => ({
    left: `${props.cell.left}px`,
    top: `${props.cell.top}px`,
    width: `${props.geometry.cellWidth}px`,
    height: `${props.geometry.cellHeight}px`,
}));

const colour = computed(() =>
    managerColour(props.pick?.manager_slot ?? props.clockSlot),
);

const subtitle = computed(() => {
    const parts = [props.pick?.castaway.tribe, props.pick?.castaway.age];

    return parts.filter(Boolean).join(' · ').toUpperCase();
});
</script>

<template>
    <!-- Made: the owner's colour is a 3px left border and the pick number. -->
    <div
        v-if="state === 'made' && pick"
        class="absolute box-border flex items-center gap-3 border border-l-[3px] border-chart-line bg-ink-cell px-3"
        :style="{ ...position, '--mgr': colour, borderLeftColor: colour }"
    >
        <span class="font-mono text-xs text-[color:var(--mgr)]">
            {{ pad(cell.pickNumber) }}
        </span>
        <div class="flex min-w-0 flex-col gap-px">
            <span
                class="truncate font-display text-base font-semibold tracking-[0.02em] text-sand"
            >
                {{ pick.castaway.name }}
            </span>
            <!-- A narrow board drops the second line rather than truncating it. -->
            <span
                v-if="subtitle && !geometry.compact"
                class="truncate font-mono text-[10px] tracking-[0.1em] text-sand-muted"
            >
                {{ subtitle }}
            </span>
        </div>
    </div>

    <!-- On the clock: the one animation in the whole app. -->
    <div
        v-else-if="state === 'clock'"
        class="absolute box-border flex animate-clock-pulse items-center justify-between gap-2 border bg-ink-active px-3"
        :style="{ ...position, '--mgr': colour, borderColor: colour }"
    >
        <span class="font-mono text-xs text-[color:var(--mgr)]">
            {{ pad(cell.pickNumber) }}
        </span>
        <span
            class="truncate font-mono text-[11px] tracking-[0.16em] text-[color:var(--mgr)]"
        >
            {{ geometry.compact ? 'ON CLOCK' : 'ON THE CLOCK' }}
        </span>
    </div>

    <div
        v-else-if="state === 'first'"
        class="absolute box-border flex items-center justify-between gap-2 border border-sand bg-ink-active px-3"
        :style="position"
    >
        <span class="font-mono text-xs text-sand">{{
            pad(cell.pickNumber)
        }}</span>
        <span
            class="truncate font-mono text-[11px] tracking-[0.16em] text-sand"
        >
            FIRST UP
        </span>
    </div>

    <div
        v-else
        class="absolute box-border flex items-center border border-dashed border-chart-line-empty px-3"
        :style="position"
    >
        <span class="font-mono text-xs text-dead">{{
            pad(cell.pickNumber)
        }}</span>
    </div>
</template>
