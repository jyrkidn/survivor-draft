<script setup lang="ts">
import { managerColour } from '@/lib/managers';
import type { ManagerTotalRecord } from '@/types/league';

defineProps<{ total: ManagerTotalRecord }>();
</script>

<template>
    <div
        class="flex flex-col gap-[5px]"
        :style="{ '--mgr': managerColour(total.slot) }"
    >
        <div class="flex items-baseline justify-between">
            <!-- The name sits in the manager's colour, so who this is never
                 depends on telling coral from gold. -->
            <span
                class="font-mono text-[11px] tracking-[0.16em] text-[color:var(--mgr)]"
            >
                {{ total.name.toUpperCase() }}
            </span>
            <span
                class="font-mono text-[26px] leading-none font-medium text-sand"
            >
                {{ total.points }}
            </span>
        </div>
        <div class="h-2 bg-ink-bar">
            <div
                class="h-2 bg-[var(--mgr)]"
                :style="{ width: `${total.bar_percent}%` }"
            />
        </div>
    </div>
</template>
