<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computeCanvasLayout } from '../../layout';
import { createBrowserMeasurer } from '../../layout/browserMeasurer';
import type { CanvasLayoutInput, DrawnCanvas } from '../../layout';
import { modeSwitchPlacement } from '../../shell/modes';
import ModeSwitch from '../../shell/ModeSwitch.vue';
import type { ShellCanvas } from '../../shell/TopBar.vue';
import type { DrawingEngine } from '../../edit-engine';
import { storedPayload } from '../../edit-engine/automaticPayload';
import { t } from '../../i18n';
import { commandForKey } from '../../edit-engine/keyboard';
import type { BoxValues, DrawingCommand } from '../../edit-engine';
import Announcer from './Announcer.vue';
import DirectionBand from './DirectionBand.vue';
import Header from './Header.vue';
import LayersPanel from './LayersPanel.vue';
import PropertiesPanel from './PropertiesPanel.vue';
import Toolbar from './Toolbar.vue';

const props = defineProps<{
    canvas: { id: number; name: string; archived: boolean };
    canWrite?: boolean;
    layouts: NonNullable<CanvasLayoutInput['layouts']>;
    versions: { structure: number; layout: number };
    newElements: string[];
    layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>;
    elements: CanvasLayoutInput['elements'];
    statements: CanvasLayoutInput['statements'];
    modalities?: unknown[];
}>();

const page = usePage<{ auth: { role: string } }>();
const shellCanvas = computed(() => page.props.canvas as unknown as ShellCanvas);
modeSwitchPlacement.inPage = true;
const host = ref<HTMLElement>();
const state = ref<'loading' | 'ready' | 'failed'>('loading');
const engine = shallowRef<DrawingEngine | null>(null);
const drawn = shallowRef<DrawnCanvas | null>(null);
const peeked = ref<string | null>(null);
const hint = ref('');
const notice = ref('');
const box = ref<BoxValues | null>(null);
const grid = ref(false);
const seen = ref<string[]>([]);
const fresh = computed(() => props.newElements.filter((id) => !seen.value.includes(id)));
const canUndo = ref(false);
const canRedo = ref(false);
const tool = ref<'select' | 'pan'>('select');
const zoom = ref(1);
const selected = ref<string[]>([]);
let created: DrawingEngine | null = null;
let unmounted = false;
let timer = 0;
let hintTimer = 0;
let stopSelection = (): void => undefined;
let stopChange = (): void => undefined;

const showHint = (): void => {
    hint.value = t('drawing.text_hint');
    window.clearTimeout(hintTimer);
    hintTimer = window.setTimeout(() => (hint.value = ''), 6000);
};
const setTool = (next: 'select' | 'pan'): void => {
    tool.value = next;
    engine.value?.setTool(next);
};
const pick = (id: string, toggle: boolean): void =>
    engine.value?.select(toggle ? (selected.value.includes(id) ? selected.value.filter((entry) => entry !== id) : [...selected.value, id]) : [id]);

const names = computed(() => new Map<string, string>([...props.layout.areas.map((area) => [area.key, area.label] as [string, string]), ...props.elements.map((element) => [element.id, element.name] as [string, string])]));
const single = computed(() => (selected.value.length === 1 ? selected.value[0] : null));
const refreshBox = (): void => {
    box.value = single.value ? (engine.value?.getBox(single.value) ?? null) : null;
};
const setBox = (id: string, values: Partial<BoxValues>): void => {
    engine.value?.apply({ kind: 'setBox', id, box: values });
    refreshBox();
};
const step = (which: 'undo' | 'redo'): void => {
    if (which === 'undo') engine.value?.undo();
    else engine.value?.redo();
    refreshBox();
};
const command = (next: DrawingCommand): void => {
    engine.value?.apply(next);
    refreshBox();
};
watch(grid, (on) => engine.value?.apply({ kind: 'setGrid', on }));
const onKey = (event: KeyboardEvent): void => {
    const target = event.target as HTMLElement;
    if (!engine.value || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) return;
    const command = commandForKey(event);
    if (!command) return;
    event.preventDefault();
    if (command.kind === 'undo' || command.kind === 'redo') step(command.kind);
    else engine.value.apply(command);
    refreshBox();
};

function release(): void {
    window.clearInterval(timer);
    window.clearTimeout(hintTimer);
    stopSelection();
    stopChange();
    created?.destroy();
    created = null;
    engine.value = null;
}

onMounted(async () => {
    try {
        const computed = computeCanvasLayout({ ...props.layout, elements: props.elements, statements: props.statements, layouts: props.layouts }, await createBrowserMeasurer());
        drawn.value = computed;
        const { createDrawingEngine } = await import('../../edit-engine');
        created = createDrawingEngine();
        await created.mount(host.value as HTMLElement, computed, { layout: storedPayload(props.layouts, props.elements.map(({ id }) => id), props.versions), onTextAttempt: showHint, onPushBack: (ids) => (notice.value = t('drawing.pushed_back', { names: ids.map((id) => names.value.get(id) ?? id).join(', ') })) });
        if (unmounted) return release();
        created.zoomToFit();
        engine.value = created;
        stopSelection = created.onSelectionChange((ids) => {
            selected.value = ids;
            seen.value = [...seen.value, ...ids];
            refreshBox();
        });
        stopChange = created.onChange(refreshBox);
        const live = created;
        timer = window.setInterval(() => {
            zoom.value = live.getZoom();
            canUndo.value = live.canUndo();
            canRedo.value = live.canRedo();
        }, 200);
        state.value = 'ready';
    } catch {
        release();
        state.value = 'failed';
    }
});
onBeforeUnmount(() => {
    unmounted = true;
    modeSwitchPlacement.inPage = false;
    release();
});
</script>

<template>
    <Head :title="canvas.name" />
    <header class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <h1 class="font-display text-[22px] font-semibold">{{ canvas.name }}</h1>
        <ModeSwitch :role="page.props.auth.role" :canvas="shellCanvas" :url="page.url" />
    </header>
    <Header class="mt-3" :can-undo="canUndo" :can-redo="canRedo" @undo="step('undo')" @redo="step('redo')" @fit="engine?.zoomToFit()" />
    <p v-if="state === 'loading'" class="mt-2 text-[13px] text-secondary" role="status">{{ t('drawing.loading') }}</p>
    <p v-if="state === 'failed'" class="mt-2 text-[13px] text-danger" role="alert">{{ t('drawing.failed') }}</p>
    <DirectionBand v-if="drawn" class="mt-3" :drawn="drawn" :statements="statements" @peek="peeked = $event" />
    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[14rem_minmax(0,1fr)_16rem]">
        <LayersPanel :fresh="fresh" :areas="layout.areas" @keydown="onKey" :elements="elements" :selected="selected" class="max-h-[40vh] overflow-auto lg:max-h-[calc(100vh-14rem)]" @select="pick" />
        <div class="flex min-w-0 flex-col gap-3">
            <div ref="host" role="img" tabindex="0" @keydown="onKey" :aria-label="t('drawing.artboard', { name: canvas.name })" class="h-[60vh] w-full overflow-hidden rounded-card border border-edge bg-[radial-gradient(#c3c9cf_1px,transparent_1px)] bg-[length:8px_8px] lg:h-[calc(100vh-17rem)]" :data-state="state"></div>
            <p class="min-h-[1.5rem] text-[13px] text-secondary" role="status" data-hint>{{ hint }}</p>
            <Announcer :message="notice" />
            <Toolbar v-model:grid="grid" :tool="tool" :zoom="zoom" :count="selected.length" @tool="setTool" @command="command" />
        </div>
        <PropertiesPanel :id="single" :name="single ? (names.get(single) ?? '') : ''" :box="box" @box="setBox">
            <template v-if="peeked" #peek><p class="whitespace-pre-line text-[13px]" data-detail>{{ peeked }}</p></template>
        </PropertiesPanel>
    </div>
</template>
