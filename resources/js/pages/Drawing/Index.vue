<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import type { CanvasLayoutInput } from '../../layout';
import { modeSwitchPlacement } from '../../shell/modes';
import ModeSwitch from '../../shell/ModeSwitch.vue';
import type { ShellCanvas } from '../../shell/TopBar.vue';
import { storedPayload } from '../../edit-engine/automaticPayload';
import type { LayoutPayload } from '../../edit-engine';
import { announce } from '../../components/feedback/status';
import { t } from '../../i18n';
import { commandForKey } from '../../edit-engine/keyboard';
import type { DrawingCommand } from '../../edit-engine';
import ApplyStyleDialog from './ApplyStyleDialog.vue';
import Announcer from './Announcer.vue';
import DragLabel from './DragLabel.vue';
import MoveDialog from './MoveDialog.vue';
import { createMoveFlow } from './moveFlow';
import { moveTargets, pathOf } from './moveTargets';
import ConflictDialog from './ConflictDialog.vue';
import LeaveDialog from './LeaveDialog.vue';
import ResetDialogs from './ResetDialogs.vue';
import { proceed } from '../../shell/fullLoad';
import { createLeaveGuard } from './leaveGuard';
import { SaveQueue, type SaveFailure, type SaveStatus } from './SaveQueue';
import { useEngineLifecycle } from './useEngineLifecycle';
import { useReset } from './useReset';
import { useStyleEditing } from './useStyleEditing';
import DirectionBand from './DirectionBand.vue';
import Header from './Header.vue';
import LayersPanel from './LayersPanel.vue';
import PropertiesPanel from './PropertiesPanel.vue';
import Toolbar from './Toolbar.vue';
import { navigate, type NavigateAction } from './navigate';

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
const peeked = ref<string | null>(null);
const notice = ref('');
const grid = ref(false);
const saveStatus = ref<SaveStatus>('idle');
const tool = ref<'select' | 'pan'>('select');
const restoreTo = ref<HTMLElement | null>(null);
let queue: SaveQueue | null = null;

const say = (text: string): void => {
    notice.value = '';
    void nextTick(() => (notice.value = text));
};
const names = computed(() => new Map<string, string>([...props.layout.areas.map((area) => [area.key, area.label] as [string, string]), ...props.elements.map((element) => [element.id, element.name] as [string, string])]));
const lifecycle = useEngineLifecycle({
    props,
    host,
    tool,
    grid,
    names,
    queue: () => queue,
    flow: () => moveFlow,
    say,
    onSelect: (ids) => styling.onSelection(ids),
    onChange: (change) => {
        if (change.kind === 'move') {
            moveFlow.onIntent(change);
            return;
        }
        styling.clearTyped();
        styling.refreshBox();
        queue?.changed();
    },
    onRestart: () => (styling.box.value = null),
});
const { state, engine, drawn, selected, seen, zoom, view, canUndo, canRedo, verdict, hint, setTool, pick, restart, refresh } = lifecycle;
const zoomed = (action: NavigateAction): void => {
    if (!engine.value) return;
    navigate(engine.value, action);
    if (action === 'in' || action === 'out' || action === 'actual') void announce(t('drawing.zoom_set', { percent: Math.round(engine.value.getZoom() * 100) }));
};
const single = computed(() => (selected.value.length === 1 ? selected.value[0] : null));
const styling = useStyleEditing({ engine, drawn, single, names, say });
const { box, target, copied, applying, invalid, refused, scope, refreshBox, copyStyle, setStyle, setBox } = styling;
const reset = useReset({ props, engine, host, single, names, queue: () => queue, say, reload: restart });
const { scope: resetScope, name: resetName, count: resetCount, resetting, ask: askReset, perform: performReset } = reset;
const fresh = computed(() => seen.unseen(props.newElements));
const moveFlow = createMoveFlow({
    canvas: props.canvas.id,
    apply: (command) => engine.value?.apply(command),
    enqueue: (task) => queue?.enqueueMove(task),
    nameOf: (id) => names.value.get(id) ?? id,
    exists: (id) => props.elements.some((element) => element.id === id),
    valid: (elementId, targetId) => moveTargets(elementId, props.layout.areas, props.elements).some((candidate) => candidate.id === targetId),
    reloadStructure: () => new Promise<void>((resolve) => router.reload({ only: ['layout', 'elements', 'layouts', 'newElements'], onFinish: () => resolve() })),
    say,
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
const isDomain = computed(() => single.value !== null && props.elements.find((candidate) => candidate.id === single.value)?.kind === 'domain');
const targets = computed(() => (single.value ? moveTargets(single.value, props.layout.areas, props.elements) : []));
const moveTo = (targetId: string, opener: HTMLElement | null): void => {
    restoreTo.value = opener;
    if (single.value) engine.value?.apply({ kind: 'previewMove', elementId: single.value, targetId });
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
    const origin = event.target as HTMLElement;
    if (!engine.value || ['INPUT', 'TEXTAREA', 'SELECT'].includes(origin.tagName)) return;
    const next = commandForKey(event);
    if (!next) return;
    event.preventDefault();
    if (next.kind === 'undo' || next.kind === 'redo') step(next.kind);
    else engine.value.apply(next);
    refreshBox();
};
const BYPASS_CODES = ['missing_element', 'unknown_element'];

const onFailure = (failure: SaveFailure): void => {
    if (failure.kind !== 'refused') return say(t('drawing.save_failed'));
    const reason = failure.messages.join(' ') || t('drawing.save_failed');
    if (failure.codes.some((code) => BYPASS_CODES.includes(code))) {
        void refresh(false, `${reason}. ${t('drawing.rebuilt')}`);
        return;
    }
    void refresh(false, reason).then(styling.captureRefusal(failure));
};

const onStatus = (next: SaveStatus): void => {
    saveStatus.value = next;
    if (next === 'layoutChanged') void refresh(false, moveFlow.hasLost() ? `${t('drawing.layout_changed')}. ${t('drawing.move_not_saved')}` : t('drawing.layout_changed'));
    const destination = leave.settle(next);
    if (destination) proceed(destination);
};

const leave = createLeaveGuard(saveStatus, () => queue?.retry());

onMounted(async () => {
    leave.install();
    queue = new SaveQueue({ canvas: props.canvas.id, versions: props.versions, getLayout: () => engine.value?.getLayout() as LayoutPayload, onStatus, onFailure });
    await lifecycle.start(storedPayload(props.layouts, props.elements.map(({ id }) => id), props.versions));
});
onBeforeUnmount(() => {
    leave.uninstall();
    modeSwitchPlacement.inPage = false;
});
</script>

<template>
    <Head :title="`${canvas.name} - ${t('shell.mode.drawing')}`" />
    <header class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <h1 class="font-display text-[22px] font-semibold">{{ canvas.name }}</h1>
        <ModeSwitch :role="page.props.auth.role" :canvas="shellCanvas" :url="page.url" />
        <Header
            :inert="moveFlow.busy.value || moveFlow.pending.value !== null || resetting"
            :status="saveStatus"
            :can-undo="canUndo"
            :can-redo="canRedo"
            @undo="step('undo')"
            @redo="step('redo')"
            @fit="engine?.zoomToFit()"
            @retry="queue?.retry()"
            @reset="askReset('canvas')"
        />
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
            <div
                ref="host"
                role="group"
                tabindex="0"
                @keydown="onKey"
                :aria-label="t('drawing.artboard', { name: canvas.name })"
                class="h-[70vh] min-h-[480px] w-full overflow-hidden rounded-card border border-edge bg-[radial-gradient(#b4bbc2_1px,transparent_1px)] bg-[length:10px_10px] lg:h-[max(720px,calc(100vh-14rem))]"
                :data-state="state"
                :data-view="view"
            ></div>
            <p class="min-h-[1.5rem] text-[13px] text-secondary" role="status" data-hint>{{ hint }}</p>
            <Announcer :message="notice" />
            <Toolbar :tool="tool" :zoom="zoom" :count="selected.length" @tool="setTool" @command="command" @navigate="zoomed" />
        </div>
        <PropertiesPanel
            v-model:grid="grid"
            :count="selected.length"
            :path="single ? pathOf(single, layout.areas, elements) : ''"
            :canvas-id="canvas.id"
            @command="command"
            :invalid="invalid"
            :refused="refused"
            :domain="isDomain"
            :targets="targets"
            :id="single"
            :name="single ? (names.get(single) ?? '') : ''"
            :box="box"
            :target="target"
            :copied="copied"
            :scope="scope?.scope ?? null"
            @box="setBox"
            @move="moveTo"
            @reset="askReset"
            @style="setStyle($event)"
            @copy-style="copyStyle"
            @paste-style="setStyle({}, 'paste')"
            @apply-style="applying = true"
        >
            <template v-if="peeked" #peek><p class="whitespace-pre-line text-[13px]" data-detail>{{ peeked }}</p></template>
        </PropertiesPanel>
    </div>
</template>
