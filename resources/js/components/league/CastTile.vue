<script setup lang="ts">
import { computed } from 'vue';
import { managerColour } from '@/lib/managers';

const props = withDefaults(
    defineProps<{
        name: string;
        age?: number | null;
        occupation?: string | null;
        tribe?: string | null;
        photoUrl?: string | null;
        /** Cast grid runs slightly larger than the draft's available grid. */
        size?: 'cast' | 'draft';
        status?: 'in' | 'out';
        exitEpisode?: number | null;
        ownerInitial?: string | null;
        ownerName?: string | null;
        ownerSlot?: number | null;
        showOwner?: boolean;
    }>(),
    {
        age: null,
        occupation: null,
        tribe: null,
        photoUrl: null,
        size: 'cast',
        status: 'in',
        exitEpisode: null,
        ownerInitial: null,
        ownerName: null,
        ownerSlot: null,
        showOwner: false,
    },
);

const isOut = computed(() => props.status === 'out');

const meta = computed(() =>
    [props.age, props.occupation?.toUpperCase()].filter(Boolean).join(' · '),
);
</script>

<template>
    <div class="flex flex-col" :class="size === 'cast' ? 'gap-[7px]' : 'gap-2'">
        <div
            class="relative w-full border photo-placeholder"
            :class="isOut ? 'border-chart-line-dim' : 'border-tribe-teal'"
            style="aspect-ratio: 4 / 5"
        >
            <img
                v-if="photoUrl"
                :src="photoUrl"
                :alt="name"
                class="absolute inset-0 h-full w-full object-cover"
            />
            <span
                v-else
                class="absolute bottom-[7px] left-[7px] font-mono text-[8px] tracking-[0.14em] text-dead-dim"
            >
                PRESS STILL
            </span>

            <!-- Elimination is carried by the strip and the strike-through,
                 never by the grey alone. -->
            <template v-if="isOut">
                <span class="absolute inset-0 bg-ink/66" />
                <span
                    class="absolute right-0 bottom-0 left-0 bg-ink px-[7px] py-1 font-mono text-[9px] tracking-[0.16em] text-dead"
                >
                    OUT<template v-if="exitEpisode">
                        · EP{{ exitEpisode }}</template
                    >
                </span>
            </template>

            <!-- The badge is the one place a full name will not fit, so it
                 carries the manager's initial and names them in the title. -->
            <span
                v-if="showOwner && ownerInitial"
                class="absolute top-[7px] right-[7px] flex h-5 min-w-5 items-center justify-center px-1 font-mono text-[11px] font-medium text-ink"
                :style="{ background: managerColour(ownerSlot) }"
                :title="`Drafted by ${ownerName}`"
            >
                {{ ownerInitial }}
            </span>
            <span
                v-else-if="showOwner"
                class="absolute top-[7px] right-[7px] flex h-5 w-5 items-center justify-center border border-dashed border-dead-dim font-mono text-[11px] text-dead"
                title="Undrafted"
            >
                –
            </span>
        </div>

        <div class="flex flex-col gap-0.5">
            <span
                class="font-display font-bold tracking-[0.03em]"
                :class="[
                    size === 'cast' ? 'text-[17px]' : 'text-base',
                    isOut ? 'text-dead line-through' : 'text-sand',
                ]"
            >
                {{ name }}
            </span>
            <span
                v-if="meta"
                class="font-mono text-[9px] tracking-[0.12em]"
                :class="isOut ? 'text-dead' : 'text-sand-muted'"
            >
                {{ meta }}
            </span>
            <span
                v-if="tribe"
                class="font-mono text-[9px] tracking-[0.16em]"
                :class="isOut ? 'text-dead-dim' : 'text-sea-green'"
            >
                {{ tribe.toUpperCase() }}
            </span>
        </div>

        <slot name="action" />
    </div>
</template>
