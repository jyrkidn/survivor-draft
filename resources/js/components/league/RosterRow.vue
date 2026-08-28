<script setup lang="ts">
import { computed } from 'vue';
import type { RosterEntry } from '@/types/league';

const props = defineProps<{ entry: RosterEntry }>();

const isOut = computed(() => props.entry.status === 'out');
</script>

<template>
    <div class="flex items-center gap-2.5 border-t border-chart-line-dim py-2">
        <!-- Filled square alive, hollow outline out. -->
        <span
            class="h-1.5 w-1.5 flex-none"
            :class="isOut ? 'border border-dead' : 'bg-sand'"
        />
        <span
            class="flex-1 font-display text-base font-semibold"
            :class="isOut ? 'text-dead line-through' : 'text-sand'"
        >
            {{ entry.name }}
        </span>
        <span
            v-if="isOut && entry.exit_episode"
            class="font-mono text-[10px] tracking-[0.14em] text-dead"
        >
            EP{{ entry.exit_episode }}
        </span>
        <!-- Points never change column. -->
        <span
            class="font-mono text-sm"
            :class="isOut ? 'text-dead' : 'text-sand'"
        >
            {{ entry.points }}
        </span>
    </div>
</template>
