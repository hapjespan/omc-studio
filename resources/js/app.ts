import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { t } from './i18n';
import AppShell from './shell/AppShell.vue';

createInertiaApp({
    title: (title) => (title ? `${title} - ${t('app.title')}` : t('app.title')),
    progress: { includeCSS: false },
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
        const page = pages[`./pages/${name}.vue`] as { default: { layout?: unknown } };
        if (!name.startsWith('Auth/')) page.default.layout ??= AppShell;
        return page as never;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
});
