import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { loadDrawingWithFullPages } from './shell/fullLoad';
import { readModeDestination } from './canvas/viewport';
import AppShell from './shell/AppShell.vue';

let appName = '';

readModeDestination();
loadDrawingWithFullPages();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: { includeCSS: false },
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
        const page = pages[`./pages/${name}.vue`] as { default: { layout?: unknown } };
        if (!name.startsWith('Auth/')) page.default.layout ??= AppShell;
        return page as never;
    },
    setup({ el, App, props, plugin }) {
        appName = String(props.initialPage.props.appName);
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
});
