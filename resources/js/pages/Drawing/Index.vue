<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computeCanvasLayout } from '../../layout';
import { createBrowserMeasurer } from '../../layout/browserMeasurer';
import type { CanvasLayoutInput, DrawnCanvas, TextMeasurer } from '../../layout';
import { modeSwitchPlacement } from '../../shell/modes';
import ModeSwitch from '../../shell/ModeSwitch.vue';
import type { ShellCanvas } from '../../shell/TopBar.vue';
import type { DrawingEngine, ShiftVerdictEvent } from '../../edit-engine';
import { mergeLayout, payloadLayouts, storedPayload } from '../../edit-engine/automaticPayload';
import type { LayoutPayload } from '../../edit-engine';
import { t } from '../../i18n';
import { commandForKey } from '../../edit-engine/keyboard';
import type { BoxValues, DrawingCommand } from '../../edit-engine';
import Announcer from './Announcer.vue';
import DragLabel from './DragLabel.vue';
import MoveDialog from './MoveDialog.vue';
import { createMoveFlow } from './moveFlow';
import { moveTargets, pathOf } from './moveTargets';
import ConflictDialog from './ConflictDialog.vue';
import { SaveQueue, type SaveFailure, type SaveStatus } from './SaveQueue';
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
const saveStatus = ref<SaveStatus>('idle');
const seen = ref<string[]>([]);
const fresh = computed(() => props.newElements.filter((id) => !seen.value.includes(id)));
const canUndo = ref(false);
const canRedo = ref(false);
const tool = ref<'select' | 'pan'>('select');
const zoom = ref(1);
const selected = ref<string[]>([]);
const verdict = ref<ShiftVerdictEvent | null>(null);
const view = ref('');
const restoreTo = ref<HTMLElement | null>(null);
let created: DrawingEngine | null = null;
let queue: SaveQueue | null = null;
let loaded: LayoutPayload | null = null;
let refreshing = false;
let measurer: TextMeasurer | null = null;
let unmounted = false;
let timer = 0;
let hintTimer = 0;
let stopSelection = (): void => undefined;
let stopChange = (): void => undefined;
let stopVerdict = (): void => undefined;

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
const moveFlow = createMoveFlow({
    canvas: props.canvas.id,
    apply: (command) => engine.value?.apply(command),
    enqueue: (task) => queue?.enqueueMove(task),
    nameOf: (id) => names.value.get(id) ?? id,
    exists: (id) => props.elements.some((element) => element.id === id),
    valid: (elementId, targetId) => moveTargets(elementId, props.layout.areas, props.elements).some((target) => target.id === targetId),
    reloadStructure: () => new Promise<void>((resolve) => router.reload({ only: ['layout', 'elements', 'layouts', 'newElements'], onFinish: () => resolve() })),
    say: (text) => say(text),
    text: (key, values) => t(key, values),
    refresh: (keepOwn, message) => refresh(keepOwn, message),
});
const targetPath = computed(() => (moveFlow.pending.value ? pathOf(moveFlow.pending.value.targetId, props.layout.areas, props.elements) : ''));
const restore = (): HTMLElement | null => {
    const intent = moveFlow.pending.value;
    const opener = restoreTo.value;
    restoreTo.value = null;

    return opener ?? (intent ? document.querySelector<HTMLElement>(`[data-layer="${intent.elementId}"]`) : null) ?? host.value ?? null;
};
const targets = computed(() => (single.value ? moveTargets(single.value, props.layout.areas, props.elements) : []));
const moveTo = (targetId: string, opener: HTMLElement | null): void => {
    restoreTo.value = opener;
    if (single.value) engine.value?.apply({ kind: 'previewMove', elementId: single.value, targetId });
};
const refreshBox = (): void => {
    box.value = single.value ? (engine.value?.getBox(single.value) ?? null) : null;
};
const setBox = (id: string, values: Partial<BoxValues>): void => {
    engine.value?.apply({ kind: 'setBox', id, box: values });
    refreshBox();
};
const step = (which: 'undo' | 'redo'): void => {
    restoreTo.value = document.activeElement instanceof HTMLElement ? document.activeElement : null;
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
    stopVerdict();
    created?.destroy();
    created = null;
    engine.value = null;
}

function say(text: string): void {
    notice.value = '';
    void nextTick(() => (notice.value = text));
}

const onFailure = (failure: SaveFailure): void => {
    say(failure.kind === 'refused' && failure.messages.length > 0 ? failure.messages.join(' ') : t('drawing.save_failed'));
};

async function start(payload: LayoutPayload): Promise<void> {
    release();
    loaded = payload;
    selected.value = [];
    box.value = null;
    state.value = 'loading';
    try {
        const computed = computeCanvasLayout({ ...props.layout, elements: props.elements, statements: props.statements, layouts: payloadLayouts(payload) }, measurer ?? (measurer = await createBrowserMeasurer()));
        drawn.value = computed;
        const { createDrawingEngine } = await import('../../edit-engine');
        created = createDrawingEngine();
        await created.mount(host.value as HTMLElement, computed, { layout: payload, structure: { areas: props.layout.areas, elements: props.elements }, onMoveRefused: (reason, target) => say(`${t('drawing.drag_refused', { reason: t(`drawing.refusal_${reason}`) })} (${target})`), onTextAttempt: showHint, onPushBack: (ids) => say(t('drawing.pushed_back', { names: ids.map((id) => names.value.get(id) ?? id).join(', ') })) });
        if (unmounted) return release();
        created.zoomToFit();
        created.setTool(tool.value);
        created.apply({ kind: 'setGrid', on: grid.value });
        engine.value = created;
        const live = created;
        stopSelection = live.onSelectionChange((ids) => {
            selected.value = ids;
            seen.value = [...seen.value, ...ids];
            refreshBox();
        });
        stopChange = live.onChange((change) => {
            if (change.kind === 'move') {
                moveFlow.onIntent(change);
                return;
            }
            refreshBox();
            queue?.changed();
        });
        stopVerdict = live.onShiftVerdict((event) => (verdict.value = event));
        timer = window.setInterval(() => {
            zoom.value = live.getZoom();
            view.value = JSON.stringify(live.getView());
            canUndo.value = live.canUndo();
            canRedo.value = live.canRedo();
        }, 200);
        state.value = 'ready';
    } catch {
        release();
        state.value = 'failed';
    }
}

const onStatus = (next: SaveStatus): void => {
    saveStatus.value = next;
    if (next === 'layoutChanged') void refresh(false, `${t('drawing.layout_changed')}. ${t('drawing.move_not_saved')}`);
};

async function refresh(keepOwn: boolean, message = ''): Promise<void> {
    if (refreshing) return;
    refreshing = true;
    const own = keepOwn && engine.value ? engine.value.getLayout() : null;
    const base = loaded;
    try {
        await new Promise<void>((resolve) => router.reload({ only: ['layout', 'elements', 'statements', 'layouts', 'versions', 'newElements'], onFinish: () => resolve() }));
        const fresh = storedPayload(props.layouts, props.elements.map(({ id }) => id), props.versions);
        await start(own && base ? mergeLayout(own, base, fresh) : fresh);
        if (own && base) {
            queue?.resolveStructureChange(props.versions.structure, props.versions.layout);
            queue?.changed();
        } else queue?.discard(props.versions);
        if (message !== '') say(message);
        if (keepOwn) moveFlow.afterStructureRefresh();
        else moveFlow.structureReload(message !== '');
    } finally {
        refreshing = false;
        host.value?.focus();
    }
}

onMounted(async () => {
    queue = new SaveQueue({ canvas: props.canvas.id, versions: props.versions, getLayout: () => engine.value?.getLayout() as LayoutPayload, onStatus, onFailure });
    await start(storedPayload(props.layouts, props.elements.map(({ id }) => id), props.versions));
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
    <DragLabel :event="verdict" />
    <MoveDialog :open="moveFlow.pending.value !== null" :busy="moveFlow.busy.value" :target="targetPath" :restore="restore" @confirm="moveFlow.confirm()" @cancel="moveFlow.cancel()" />
    <Header class="mt-3" :inert="moveFlow.busy.value || moveFlow.pending.value !== null" :status="saveStatus" :can-undo="canUndo" :can-redo="canRedo" @undo="step('undo')" @redo="step('redo')" @fit="engine?.zoomToFit()" @retry="queue?.retry()" />
    <ConflictDialog :open="saveStatus === 'structureChanged'" @apply="refresh(true)" @reload="refresh(false)" />
    <p v-if="state === 'loading'" class="mt-2 text-[13px] text-secondary" role="status">{{ t('drawing.loading') }}</p>
    <p v-if="state === 'failed'" class="mt-2 text-[13px] text-danger" role="alert">{{ t('drawing.failed') }}</p>
    <DirectionBand v-if="drawn" class="mt-3" :drawn="drawn" :statements="statements" @peek="peeked = $event" />
    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[14rem_minmax(0,1fr)_16rem]" :inert="moveFlow.busy.value || moveFlow.pending.value !== null">
        <LayersPanel :fresh="fresh" :areas="layout.areas" @keydown="onKey" :elements="elements" :selected="selected" class="max-h-[40vh] overflow-auto lg:max-h-[calc(100vh-14rem)]" @select="pick" />
        <div class="flex min-w-0 flex-col gap-3">
            <div ref="host" role="group" tabindex="0" @keydown="onKey" :aria-label="t('drawing.artboard', { name: canvas.name })" class="h-[60vh] w-full overflow-hidden rounded-card border border-edge bg-[radial-gradient(#c3c9cf_1px,transparent_1px)] bg-[length:8px_8px] lg:h-[calc(100vh-17rem)]" :data-state="state" :data-view="view"></div>
            <p class="min-h-[1.5rem] text-[13px] text-secondary" role="status" data-hint>{{ hint }}</p>
            <Announcer :message="notice" />
            <Toolbar v-model:grid="grid" :tool="tool" :zoom="zoom" :count="selected.length" @tool="setTool" @command="command" />
        </div>
        <PropertiesPanel :targets="targets" :id="single" :name="single ? (names.get(single) ?? '') : ''" :box="box" @box="setBox" @move="moveTo">
            <template v-if="peeked" #peek><p class="whitespace-pre-line text-[13px]" data-detail>{{ peeked }}</p></template>
        </PropertiesPanel>
    </div>
</template>
