import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import { configureEcho } from '@laravel/echo-vue';
import { createVuestic } from 'vuestic-ui';
import 'vuestic-ui/styles/essential.css';
import 'vuestic-ui/styles/typography.css';

// Configure Monaco Editor web workers
// @ts-ignore
self.MonacoEnvironment = {
    getWorker(_: any, label: string) {
        if (label === 'json') {
            return new Worker(new URL('monaco-editor/esm/vs/language/json/json.worker', import.meta.url), { type: 'module' });
        }
        if (label === 'css' || label === 'scss' || label === 'less') {
            return new Worker(new URL('monaco-editor/esm/vs/language/css/css.worker', import.meta.url), { type: 'module' });
        }
        if (label === 'html' || label === 'handlebars' || label === 'razor') {
            return new Worker(new URL('monaco-editor/esm/vs/language/html/html.worker', import.meta.url), { type: 'module' });
        }
        if (label === 'typescript' || label === 'javascript') {
            return new Worker(new URL('monaco-editor/esm/vs/language/typescript/ts.worker', import.meta.url), { type: 'module' });
        }
        return new Worker(new URL('monaco-editor/esm/vs/editor/editor.worker', import.meta.url), { type: 'module' });
    },
};

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        // If name already starts with a module path, use it directly
        const path = name.startsWith('TruthTemplatesUi/') || name.startsWith('TruthElectionUi/') || name.startsWith('TruthQrUi/') || name.startsWith('Admin/')
            ? `./${name}.vue` 
            : `./pages/${name}.vue`
        return resolvePageComponent(path, import.meta.glob<DefineComponent>(['./pages/**/*.vue', './TruthTemplatesUi/**/*.vue', './TruthElectionUi/**/*.vue', './TruthQrUi/**/*.vue', './Admin/**/*.vue']))
    },
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();
        
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .use(createVuestic({
                config: {
                    colors: {
                        variables: {
                            // Match Tailwind-ish tokens
                            primary: '#3b82f6',
                            secondary: '#6366f1',
                            success: '#10b981',
                            info: '#06b6d4',
                            danger: '#ef4444',
                            warning: '#f59e0b',
                            dark: '#1f2937',
                        },
                    },
                },
            }))
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
