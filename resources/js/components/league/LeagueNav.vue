<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { cast, draft, scoreboard } from '@/routes';

const page = usePage();

const links = [
    { label: 'SCOREBOARD', url: scoreboard.url() },
    { label: 'CAST', url: cast.url() },
    { label: 'DRAFT', url: draft.url() },
];

const isActive = (url: string): boolean =>
    new URL(page.url, 'http://x').pathname ===
    new URL(url, 'http://x').pathname;
</script>

<template>
    <nav
        class="flex items-center border-b border-chart-line-dim px-4 py-2.5 sm:px-6 lg:px-10"
    >
        <Link
            v-for="link in links"
            :key="link.label"
            :href="link.url"
            class="pr-4 font-mono text-[10px] tracking-[0.14em] transition-colors"
            :class="
                isActive(link.url)
                    ? 'text-sand'
                    : 'text-dead hover:text-sand-muted'
            "
        >
            {{ link.label }}
        </Link>

        <!-- Filament, not Inertia, so this has to be a real navigation. -->
        <a
            href="/admin"
            class="ml-auto pl-4 font-mono text-[10px] tracking-[0.14em] text-dead transition-colors hover:text-sand-muted"
        >
            ADMIN ↗
        </a>
    </nav>
</template>
