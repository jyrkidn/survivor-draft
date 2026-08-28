<script setup lang="ts">
/**
 * One header for every screen. It scales with the window rather than switching
 * treatment per page, so moving between Scoreboard, Cast and Draft does not
 * make the logo and title jump.
 */
withDefaults(
    defineProps<{
        title: string;
        /** Used below `lg`, where the full title would truncate. */
        shortTitle?: string | null;
        meta?: string | null;
    }>(),
    { shortTitle: null, meta: null },
);
</script>

<template>
    <div
        class="flex items-center justify-between gap-4 band-carved px-4 py-3 sm:px-6 lg:px-10 lg:py-[18px]"
    >
        <div class="flex min-w-0 items-center gap-2.5 lg:gap-5">
            <img
                src="/images/survivor-51-logo.png"
                alt="Survivor 51"
                class="block h-[34px] w-auto shrink-0 lg:h-[62px]"
            />
            <span
                class="hidden h-[46px] w-px shrink-0 bg-tribe-teal lg:block"
            />
            <div class="flex min-w-0 flex-col gap-1">
                <div
                    class="truncate font-display text-[15px] font-bold tracking-[0.14em] text-sand lg:text-[22px]"
                >
                    <span v-if="shortTitle" class="lg:hidden">
                        {{ shortTitle }}
                    </span>
                    <span :class="shortTitle ? 'hidden lg:inline' : ''">
                        {{ title }}
                    </span>
                </div>
                <!-- Decoration goes before the title is allowed to wrap. -->
                <div
                    class="hidden font-mono text-[10px] tracking-[0.28em] whitespace-nowrap text-sea-green lg:block"
                >
                    OUTWIT · OUTPLAY · OUTLAST
                </div>
            </div>
        </div>

        <span
            v-if="meta"
            class="shrink-0 font-mono text-[10px] tracking-[0.14em] whitespace-nowrap text-sand-muted lg:text-[11px]"
        >
            {{ meta }}
        </span>
    </div>
</template>
