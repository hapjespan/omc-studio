<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computeCanvasLayout } from '../../layout';
import { applyScope } from '../../layout/styleScope';
import type { CopiedStyle } from '../../edit-engine/commands/pasteStyle';
import type { StylePatch } from '../../edit-engine/commands/style';
import type { StyleRefusal, StyleTarget } from './StylePanel.vue';
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
import ApplyStyleDialog from './ApplyStyleDialog.vue';
import Announcer from './Announcer.vue';
import DragLabel from './DragLabel.vue';
import MoveDialog from './MoveDialog.vue';
import { createMoveFlow } from './moveFlow';
import { moveTargets, pathOf } from './moveTargets';
import ConflictDialog from './ConflictDialog.vue';
import LeaveDialog from './LeaveDialog.vue';
import ResetDialogs from './ResetDialogs.vue';
import { resetIds, type ResetScope } from '../../edit-engine/commands/reset';
import { createLeaveGuard } from './leaveGuard';
import { styleTargetOf } from './styleTarget';
import type { InvalidValue } from './PropertiesPanel.vue';
import { MoveConflict, SaveQueue, sendLayout, type SaveFailure, type SaveStatus } from './SaveQueue';
import { sendReset } from './resetRequest';
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
const target = ref<StyleTarget | null>(null);
const copied = ref<CopiedStyle | null>(null);
const applying = ref(false);
const scope = computed(() => (single.value && drawn.value && target.value ? applyScope(drawn.value, single.value) : null));
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
const invalid = ref<InvalidValue | null>(null);
const resetScope = ref<ResetScope | null>(null);
const resetId = ref<string | null>(null);
let typed: Omit<InvalidValue, 'message'> | null = null;
let typedStyle: Omit<StyleRefusal, 'message'> | null = null;
const refused = ref<StyleRefusal | null>(null);
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
const cut = computed(() => drawn.value?.warnings.some((warning) => warning.kind === 'directionTextCut') ?? false);
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
    target.value = styleTargetOf(drawn.value, single.value, engine.value);
};
const copyStyle = (): void => {
    copied.value = single.value ? (engine.value?.copyStyle(single.value) ?? null) : null;
    if (copied.value && single.value) say(t('drawing_style.copied', { name: names.value.get(single.value) ?? single.value }));
};
const setStyle = (patch: StylePatch, mode: 'patch' | 'paste' | 'apply' = 'patch'): void => {
    applying.value = false;
    refused.value = null;
    typed = null;
    const colourKey = (['text_colour', 'fill', 'border_colour'] as const).find((key) => patch[key] !== undefined);
    typedStyle = mode === 'patch' && single.value && colourKey ? { id: single.value, key: colourKey, value: String(patch[colourKey]) } : null;
    const count = scope.value?.ids.length ?? 0;
    const result = mode === 'paste' && copied.value ? engine.value?.pasteStyle(copied.value) : mode === 'apply' && single.value ? engine.value?.applyStyleToSiblings(single.value) : engine.value?.setStyle(patch);
    refreshBox();
    if (!result || result.ids.length === 0) return;
    const named = (ids: string[]) => ids.map((id) => names.value.get(id) ?? id).join(', ');
    if (mode === 'apply' && single.value) return say(t('drawing_style.applied', { name: names.value.get(single.value) ?? single.value, count }) + (result.autoText.length > 0 ? `. ${t('drawing_style.auto_text', { names: named(result.autoText) })}` : ''));
    say(result.autoText.length > 0 ? t('drawing_style.auto_text', { names: named(result.autoText) }) : t('drawing_style.changed', { name: named(result.ids) }));
};
const setBox = (id: string, values: Partial<BoxValues>): void => {
    invalid.value = null;
    refused.value = null;
    typedStyle = null;
    engine.value?.apply({ kind: 'setBox', id, box: values });
    const key = Object.keys(values)[0] as InvalidValue['key'];
    typed = { id, key, value: String(values[key]) };
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

const BYPASS_CODES = ['missing_element', 'unknown_element'];

const onFailure = (failure: SaveFailure): void => {
    if (failure.kind !== 'refused') return say(t('drawing.save_failed'));
    const reason = failure.messages.join(' ') || t('drawing.save_failed');
    if (failure.codes.some((code) => BYPASS_CODES.includes(code))) {
        void refresh(false, `${reason}. ${t('drawing.rebuilt')}`);
        return;
    }
    const attempt = typed;
    const styled = typedStyle;
    void refresh(false, reason).then(() => {
        if (styled && failure.elements.includes(styled.id)) {
            engine.value?.select([styled.id]);
            refused.value = { ...styled, message: failure.messages[0] ?? t('drawing.value_invalid') };
            return;
        }
        if (!attempt || !failure.elements.includes(attempt.id)) return;
        engine.value?.select([attempt.id]);
        invalid.value = { ...attempt, message: failure.messages[0] ?? t('drawing.value_invalid') };
    });
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
        await created.mount(host.value as HTMLElement, computed, { layout: payload, measurer: measurer ?? undefined, structure: { areas: props.layout.areas, elements: props.elements }, onMoveRefused: (reason, target) => say(`${t('drawing.drag_refused', { reason: t(`drawing.refusal_${reason}`) })} (${target})`), onTextAttempt: showHint, onPushBack: (ids) => say(t('drawing.pushed_back', { names: ids.map((id) => names.value.get(id) ?? id).join(', ') })) });
        if (unmounted) return release();
        created.zoomToFit();
        created.setTool(tool.value);
        created.apply({ kind: 'setGrid', on: grid.value });
        engine.value = created;
        const live = created;
        stopSelection = live.onSelectionChange((ids) => {
            if (invalid.value && ids.join() !== invalid.value.id) invalid.value = null;
            if (refused.value && ids.join() !== refused.value.id) refused.value = null;
            selected.value = ids;
            seen.value = [...seen.value, ...ids];
            refreshBox();
        });
        stopChange = live.onChange((change) => {
            if (change.kind === 'move') {
                moveFlow.onIntent(change);
                return;
            }
            typed = null;
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
    if (next === 'layoutChanged') void refresh(false, moveFlow.hasLost() ? `${t('drawing.layout_changed')}. ${t('drawing.move_not_saved')}` : t('drawing.layout_changed'));
    if (next === 'saved' && leave.destination()) window.location.assign(leave.destination() as string);
};

async function loadStored(): Promise<LayoutPayload> {
    await new Promise<void>((resolve) => router.reload({ only: ['layout', 'elements', 'statements', 'layouts', 'versions', 'newElements'], onFinish: () => resolve() }));

    return storedPayload(props.layouts, props.elements.map(({ id }) => id), props.versions);
}

async function refresh(keepOwn: boolean, message = '', focusTo: HTMLElement | null = null): Promise<void> {
    if (refreshing) return;
    refreshing = true;
    const own = keepOwn && engine.value ? engine.value.getLayout() : null;
    const base = loaded;
    try {
        const fresh = await loadStored();
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
        (focusTo?.isConnected ? focusTo : host.value)?.focus();
    }
}

const resetName = computed(() => (resetId.value ? (names.value.get(resetId.value) ?? '') : ''));
const resetCount = computed(() => (resetScope.value ? (props.layout.areas.some((area) => area.key === resetId.value) ? props.elements.filter((element) => element.area === resetId.value).length : resetIds(props.elements, resetScope.value, resetId.value).length) : 0));
const resetOpener = ref<HTMLElement | null>(null);
const askReset = (scope: ResetScope): void => {
    resetOpener.value = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    resetScope.value = scope;
    resetId.value = scope === 'canvas' ? null : single.value;
};
const resetting = ref(false);
const performReset = (): void => {
    const scope = resetScope.value;
    const id = resetId.value;
    const opener = resetOpener.value;
    resetScope.value = null;
    if (!scope) return;
    const area = id !== null && props.layout.areas.some((candidate) => candidate.key === id);
    resetting.value = true;
    queue?.enqueueMove(async (versions) => {
        try {
            const current = engine.value?.getLayout() as LayoutPayload;
            const response = area
                ? await sendLayout(props.canvas.id, { ...current, areas: Object.fromEntries(Object.entries(current.areas).filter(([key]) => key !== id)), structure_version: versions.structure, layout_version: versions.layout })
                : await sendReset(props.canvas.id, scope, id);
            if (response.outcome === 'conflict') throw new MoveConflict(response.conflict);
            if (response.outcome === 'refused') {
                say(response.messages.join(' ') || t('drawing.save_failed'));
                await start(await loadStored());

                return { structure: props.versions.structure, layout: props.versions.layout };
            }
            if (response.outcome !== 'saved') throw new Error('unreachable');
            await start(await loadStored());
            say(t('drawing.reset_done'));
            (opener?.isConnected ? opener : host.value)?.focus();

            return { structure: props.versions.structure, layout: props.versions.layout };
        } finally {
            resetting.value = false;
        }
    });
};
const leave = createLeaveGuard(saveStatus, () => queue?.retry());

onMounted(async () => {
    leave.install();
    queue = new SaveQueue({ canvas: props.canvas.id, versions: props.versions, getLayout: () => engine.value?.getLayout() as LayoutPayload, onStatus, onFailure });
    await start(storedPayload(props.layouts, props.elements.map(({ id }) => id), props.versions));
});
onBeforeUnmount(() => {
    unmounted = true;
    leave.uninstall();
    modeSwitchPlacement.inPage = false;
    release();
});
</script>

<template>
    <Head :title="`${canvas.name} - ${t('shell.mode.drawing')}`" />
    <header class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <h1 class="font-display text-[22px] font-semibold">{{ canvas.name }}</h1>
        <ModeSwitch :role="page.props.auth.role" :canvas="shellCanvas" :url="page.url" />
        <Header :inert="moveFlow.busy.value || moveFlow.pending.value !== null || resetting" :status="saveStatus" :can-undo="canUndo" :can-redo="canRedo" @undo="step('undo')" @redo="step('redo')" @fit="engine?.zoomToFit()" @retry="queue?.retry()" @reset="askReset('canvas')" />
    </header>
    <DragLabel :event="verdict" />
    <LeaveDialog :open="leave.target.value !== null" :failed="saveStatus === 'failed' && leave.retried.value" @retry="leave.retry" @discard="leave.discard" @stay="leave.target.value = null" />
    <ResetDialogs :scope="resetScope" :name="resetName" :count="resetCount" @confirm="performReset" @cancel="resetScope = null" />
    <MoveDialog :open="moveFlow.pending.value !== null" :busy="moveFlow.busy.value" :target="targetPath" :restore="restore" @confirm="moveFlow.confirm()" @cancel="moveFlow.cancel()" />
    <ConflictDialog :open="saveStatus === 'structureChanged'" @apply="refresh(true)" @reload="refresh(false)" />
    <ApplyStyleDialog v-model:open="applying" :scope="scope?.scope ?? null" :count="scope?.ids.length ?? 0" :name="single ? (names.get(single) ?? '') : ''" @confirm="setStyle({}, 'apply')" />
    <p v-if="state === 'loading'" class="mt-2 text-[13px] text-secondary" role="status">{{ t('drawing.loading') }}</p>
    <p v-if="state === 'failed'" class="mt-2 text-[13px] text-danger" role="alert">{{ t('drawing.failed') }}</p>
    <DirectionBand v-if="drawn" :class="cut ? 'mt-3' : 'sr-only'" :drawn="drawn" :statements="statements" @peek="peeked = $event" />
    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[14rem_minmax(0,1fr)_16rem]" :inert="moveFlow.busy.value || moveFlow.pending.value !== null || resetting">
        <LayersPanel :fresh="fresh" :areas="layout.areas" @keydown="onKey" :elements="elements" :selected="selected" class="max-h-[40vh] overflow-auto lg:max-h-[calc(100vh-14rem)]" @select="pick" />
        <div class="flex min-w-0 flex-col gap-3">
            <div ref="host" role="group" tabindex="0" @keydown="onKey" :aria-label="t('drawing.artboard', { name: canvas.name })" class="h-[70vh] min-h-[480px] w-full overflow-hidden rounded-card border border-edge bg-[radial-gradient(#b4bbc2_1px,transparent_1px)] bg-[length:8px_8px] lg:h-[max(720px,calc(100vh-14rem))]" :data-state="state" :data-view="view"></div>
            <p class="min-h-[1.5rem] text-[13px] text-secondary" role="status" data-hint>{{ hint }}</p>
            <Announcer :message="notice" />
            <Toolbar :tool="tool" :zoom="zoom" :count="selected.length" @tool="setTool" @command="command" />
        </div>
        <PropertiesPanel v-model:grid="grid" :count="selected.length" :path="single ? pathOf(single, layout.areas, elements) : ''" :canvas-id="canvas.id" @command="command" :invalid="invalid" :refused="refused" :domain="single !== null && props.elements.find((candidate) => candidate.id === single)?.kind === 'domain'" :targets="targets" :id="single" :name="single ? (names.get(single) ?? '') : ''" :box="box" :target="target" :copied="copied" :scope="scope?.scope ?? null" @box="setBox" @move="moveTo" @reset="askReset" @style="setStyle($event)" @copy-style="copyStyle" @paste-style="setStyle({}, 'paste')" @apply-style="applying = true">
            <template v-if="peeked" #peek><p class="whitespace-pre-line text-[13px]" data-detail>{{ peeked }}</p></template>
        </PropertiesPanel>
    </div>
</template>
