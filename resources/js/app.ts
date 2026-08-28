import { createInertiaApp } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // Each screen carries its own header band, so there is no shared layout.
    layout: () => null,
    // Sand, so the bar reads as part of the palette.
    progress: {
        color: '#efe2c0',
    },
});
