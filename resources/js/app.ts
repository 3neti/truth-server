import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import { configureEcho } from '@laravel/echo-vue';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        // If name already starts with a module path, use it directly
        const path = name.startsWith('TruthTemplatesUi/') || name.startsWith('TruthElectionUi/') || name.startsWith('TruthQrUi/') 
            ? `./${name}.vue` 
            : `./pages/${name}.vue`
        return resolvePageComponent(path, import.meta.glob<DefineComponent>(['./pages/**/*.vue', './TruthTemplatesUi/**/*.vue', './TruthElectionUi/**/*.vue', './TruthQrUi/**/*.vue']))
    },
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();
        
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
