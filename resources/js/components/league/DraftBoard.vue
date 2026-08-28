<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    boardCells,
    boardRows,
    geometryFor,
    rowY,
    spineProgressY,
} from '@/lib/draftBoard';
import type { DraftPickRecord } from '@/types/league';
import PickCell from './PickCell.vue';

const props = withDefaults(
    defineProps<{
        picks: DraftPickRecord[];
        totalPicks: number;
        nextPickNumber?: number | null;
        clockSlot?: number | null;
        /** Pre-draft: everything dashed except pick 1, and no progress fill. */
        preDraft?: boolean;
    }>(),
    { nextPickNumber: null, clockSlot: null, preDraft: false },
);

const board = ref<HTMLElement | null>(null);
const boardWidth = ref(560);
let observer: ResizeObserver | null = null;

// The board is laid out in real pixels rather than scaled with a transform, so
// the stroke stays 1px and the type stays the size it was drawn at.
onMounted(() => {
    if (board.value === null) {
        return;
    }

    boardWidth.value = board.value.clientWidth;

    observer = new ResizeObserver(([entry]) => {
        boardWidth.value = entry.contentRect.width;
    });
    observer.observe(board.value);
});

onBeforeUnmount(() => observer?.disconnect());

const geometry = computed(() =>
    geometryFor(boardWidth.value, props.totalPicks),
);

const cells = computed(() => boardCells(props.totalPicks, geometry.value));

const rows = computed(() => boardRows(props.totalPicks, geometry.value));

const byPickNumber = computed(
    () => new Map(props.picks.map((pick) => [pick.pick_number, pick])),
);

const currentRound = computed(() =>
    Math.ceil(Math.min(props.picks.length + 1, props.totalPicks) / 2),
);

const spineX = computed(() => geometry.value.gutterX);

const spineEnd = computed(() =>
    props.preDraft
        ? rowY(1)
        : spineProgressY(props.picks.length, props.totalPicks),
);

const roundComplete = (round: number): boolean =>
    byPickNumber.value.has(round * 2 - 1) && byPickNumber.value.has(round * 2);

const stateFor = (
    pickNumber: number,
): 'made' | 'clock' | 'future' | 'first' => {
    if (byPickNumber.value.has(pickNumber)) {
        return 'made';
    }

    if (props.preDraft) {
        return pickNumber === 1 ? 'first' : 'future';
    }

    return pickNumber === props.nextPickNumber ? 'clock' : 'future';
};
</script>

<template>
    <div
        ref="board"
        class="relative w-full"
        :style="{ height: `${geometry.height}px` }"
    >
        <svg
            :width="geometry.width"
            :height="geometry.height"
            :viewBox="`0 0 ${geometry.width} ${geometry.height}`"
            fill="none"
            class="absolute inset-0"
            aria-hidden="true"
        >
            <!-- The spine is the progress bar: it fills down the gutter as the
                 rounds complete, the way the serpentine thread used to. -->
            <line
                :x1="spineX"
                :y1="rowY(1)"
                :x2="spineX"
                :y2="rowY(geometry.rows)"
                stroke="var(--color-chart-line)"
                stroke-width="2"
            />
            <line
                v-if="!preDraft && picks.length > 0"
                :x1="spineX"
                :y1="rowY(1)"
                :x2="spineX"
                :y2="spineEnd"
                stroke="var(--color-sand)"
                stroke-width="2"
                opacity="0.55"
            />

            <!-- One connector per round, joining the pair that share it. -->
            <line
                v-for="row in rows"
                :key="`connector-${row.round}`"
                :x1="row.from"
                :y1="row.y"
                :x2="row.to"
                :y2="row.y"
                :stroke="
                    roundComplete(row.round)
                        ? 'var(--color-sand)'
                        : 'var(--color-chart-line)'
                "
                :opacity="roundComplete(row.round) ? 0.55 : 1"
                stroke-width="2"
            />

            <circle :cx="spineX" :cy="rowY(1)" r="4" fill="var(--color-sand)" />
            <circle
                v-if="!preDraft"
                :cx="spineX"
                :cy="rowY(geometry.rows)"
                r="4"
                fill="var(--color-chart-line)"
            />
        </svg>

        <!-- Round numbers sit in the gutter, just above their row. -->
        <span
            v-for="row in rows"
            :key="`round-${row.round}`"
            class="absolute text-center font-mono text-[10px] tracking-[0.1em]"
            :class="
                row.round === currentRound && !preDraft
                    ? 'text-sand'
                    : 'text-dead'
            "
            :style="{
                left: `${geometry.gutterX - geometry.gutter / 2}px`,
                width: `${geometry.gutter}px`,
                top: `${row.y - 22}px`,
            }"
        >
            R{{ row.round }}
        </span>

        <PickCell
            v-for="cell in cells"
            :key="cell.pickNumber"
            :cell="cell"
            :geometry="geometry"
            :pick="byPickNumber.get(cell.pickNumber) ?? null"
            :state="stateFor(cell.pickNumber)"
            :clock-slot="clockSlot"
        />
    </div>
</template>
