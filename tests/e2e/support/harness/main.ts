import './harness.css';
import { createApp } from 'vue';

const demos = import.meta.glob('./*Demo.vue', { eager: true }) as Record<string, { default: object }>;
const name = new URLSearchParams(location.search).get('demo') ?? '';
const demo = demos[`./${name}Demo.vue`];

if (demo) createApp(demo.default).mount('#app');
