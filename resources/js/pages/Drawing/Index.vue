<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computeCanvasLayout } from '../../layout';
import { createBrowserMeasurer } from '../../layout/browserMeasurer';
import type { CanvasLayoutInput, DrawnCanvas } from '../../layout';
import ModeSwitch from '../../shell/ModeSwitch.vue';
import type { ShellCanvas } from '../../shell/TopBar.vue';
import type { DrawingEngine } from '../../edit-engine';
import { automaticPayload } from '../../edit-engine/payload';
import { t } from '../../i18n';
import DirectionBand from './DirectionBand.vue';
import Header from './Header.vue';
import LayersPanel from './LayersPanel.vue';
import Toolbar from './Toolbar.vue';

const props = defineProps<{
    canvas: { id: number; name: string; archived: boolean };
    layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>;
    elements: CanvasLayoutInput['elements'];
    statements: CanvasLayoutInput['statements'];
}>();

const page = usePage<{ auth: { role: string } }>();
const shellCanvas = computed(() => page.props.canvas as unknown as ShellCanvas);
const host = ref<HTMLElement>();
const state = ref<'loading' | 'ready' | 'failed'>('loading');
const engine = shallowRef<DrawingEngine | null>(null);
const tool = ref<'select' | 'pan'>('select');
const zoom = ref(1);
const selected = ref<string[]>([]);
let timer = 0;
let stopSelection = (): void => undefined;
const setTool = (next: 'select' | 'pan'): void => {
    tool.value = next;
    engine.value?.setTool(next);
};
const drawn = shallowRef<DrawnCanvas | null>(null);
const peeked = ref<string | null>(null);
const hint = ref('');
let hintTimer = 0;
const showHint = (): void => {
    hint.value = t('drawing.text_hint');
    window.clearTimeout(hintTimer);
    hintTimer = window.setTimeout(() => (hint.value = ''), 6000);
};
const pick = (id: string, toggle: boolean): void => engine.value?.select(toggle ? (selected.value.includes(id) ? selected.value.filter((entry) => entry !== id) : [...selected.value, id]) : [id]);

onMounted(async () => {
    try {
        const computed = computeCanvasLayout({ ...props.layout, elements: props.elements, statements: props.statements }, await createBrowserMeasurer());
        drawn.value = computed;
        const { createDrawingEngine } = await import('../../edit-engine');
        const created = createDrawingEngine();
        await created.mount(host.value as HTMLElement, computed, { layout: automaticPayload(props.elements.map(({ id }) => id)), onTextAttempt: showHint });
        created.zoomToFit();
        engine.value = created;
        stopSelection = created.onSelectionChange((ids) => (selected.value = ids));
        timer = window.setInterval(() => {
            zoom.value = created.getZoom();
        }, 200);
        state.value = 'ready';
    } catch {
        state.value = 'failed';
    }
});
onBeforeUnmount(() => {
    window.clearInterval(timer);
    stopSelection();
    window.clearTimeout(hintTimer);
    engine.value?.destroy();
});
</script>

<template>
    <Head :title="canvas.name" />
    <header class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <h1 class="font-display text-[22px] font-semibold">{{ canvas.name }}</h1>
        <ModeSwitch :role="page.props.auth.role" :canvas="shellCanvas" :url="page.url" />
    </header>
    <Header class="mt-3" :name="canvas.name" @undo="engine?.undo()" @redo="engine?.redo()" @fit="engine?.zoomToFit()" />
    <DirectionBand v-if="drawn" class="mt-3" :drawn="drawn" :statements="statements" @peek="peeked = $event" />
    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[14rem_minmax(0,1fr)_16rem]">
        <LayersPanel :areas="layout.areas" :elements="elements" :selected="selected" class="max-h-[40vh] overflow-auto lg:max-h-[calc(100vh-14rem)]" @select="pick" />
        <div class="flex min-w-0 flex-col gap-3">
            <div ref="host" role="img" :aria-label="t('drawing.artboard', { name: canvas.name })" class="h-[60vh] w-full overflow-hidden rounded-card border border-edge bg-[radial-gradient(#c3c9cf_1px,transparent_1px)] bg-[length:8px_8px] lg:h-[calc(100vh-17rem)]" :data-state="state"></div>
            <p class="min-h-[1.5rem] text-[13px] text-secondary" role="status" data-hint>{{ hint }}</p>
            <Toolbar :tool="tool" :zoom="zoom" @tool="setTool" />
        </div>
        <aside :aria-label="t('drawing.properties')">
            <h2 class="text-[13px] font-semibold">{{ t('drawing.properties') }}</h2>
            <p v-if="peeked" class="mt-2 whitespace-pre-line text-[13px]" data-detail>{{ peeked }}</p>
            <p v-else class="mt-2 text-[13px] text-secondary">{{ selected.length ? selected.length : t('drawing.nothing_selected') }}</p>
        </aside>
    </div>
</template>
