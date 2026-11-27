import { onBeforeUnmount, ref, shallowRef, type ComputedRef, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { computeCanvasLayout } from '../../layout';
import { createBrowserMeasurer } from '../../layout/browserMeasurer';
import type { DrawnCanvas, TextMeasurer } from '../../layout';
import type { DrawingEngine, LayoutPayload, ShiftVerdictEvent } from '../../edit-engine';
import type { FinishedAction } from '../../edit-engine';
import { mergeLayout, payloadLayouts, storedPayload } from '../../edit-engine/automaticPayload';
import { t } from '../../i18n';
import type { MoveFlow } from './moveFlow';
import type { SaveQueue } from './SaveQueue';
import type { DrawingProps } from './drawingProps';
import { SelectionHistory } from './selectionHistory';

export interface LifecycleDeps {
    props: DrawingProps;
    host: Ref<HTMLElement | undefined>;
    tool: Ref<'select' | 'pan'>;
    grid: Ref<boolean>;
    names: ComputedRef<Map<string, string>>;
    queue: () => SaveQueue | null;
    flow: () => MoveFlow;
    say: (text: string) => void;
    onSelect: (ids: string[]) => void;
    onChange: (change: FinishedAction) => void;
    onRestart: () => void;
}

export function useEngineLifecycle(deps: LifecycleDeps) {
    const { props, host, tool, grid, names } = deps;
    const state = ref<'loading' | 'ready' | 'failed'>('loading');
    const engine = shallowRef<DrawingEngine | null>(null);
    const drawn = shallowRef<DrawnCanvas | null>(null);
    const selected = ref<string[]>([]);
    const seen = new SelectionHistory();
    const zoom = ref(1);
    const view = ref('');
    const canUndo = ref(false);
    const canRedo = ref(false);
    const verdict = ref<ShiftVerdictEvent | null>(null);
    const hint = ref('');
    let created: DrawingEngine | null = null;
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

    async function start(payload: LayoutPayload): Promise<void> {
        release();
        loaded = payload;
        selected.value = [];
        deps.onRestart();
        state.value = 'loading';
        try {
            const computed = computeCanvasLayout({ ...props.layout, elements: props.elements, statements: props.statements, layouts: payloadLayouts(payload) }, measurer ?? (measurer = await createBrowserMeasurer()));
            drawn.value = computed;
            const { createDrawingEngine } = await import('../../edit-engine');
            created = createDrawingEngine();
            await created.mount(host.value as HTMLElement, computed, {
                layout: payload,
                measurer: measurer ?? undefined,
                structure: { areas: props.layout.areas, elements: props.elements },
                onMoveRefused: (reason, target) => deps.say(`${t('drawing.drag_refused', { reason: t(`drawing.refusal_${reason}`) })} (${target})`),
                onTextAttempt: showHint,
                onPushBack: (ids) => deps.say(t('drawing.pushed_back', { names: ids.map((id) => names.value.get(id) ?? id).join(', ') })),
            });
            if (unmounted) return release();
            created.zoomToFit();
            created.setTool(tool.value);
            created.apply({ kind: 'setGrid', on: grid.value });
            engine.value = created;
            const live = created;
            stopSelection = live.onSelectionChange((ids) => {
                selected.value = ids;
                seen.record(ids);
                deps.onSelect(ids);
            });
            stopChange = live.onChange(deps.onChange);
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

    async function loadStored(): Promise<LayoutPayload> {
        await new Promise<void>((resolve) => router.reload({ only: ['layout', 'elements', 'statements', 'layouts', 'versions', 'newElements'], onFinish: () => resolve() }));

        return storedPayload(props.layouts, props.elements.map(({ id }) => id), props.versions);
    }

    const restart = async (): Promise<void> => start(await loadStored());

    async function refresh(keepOwn: boolean, message = '', focusTo: HTMLElement | null = null): Promise<void> {
        if (refreshing) return;
        refreshing = true;
        const own = keepOwn && engine.value ? engine.value.getLayout() : null;
        const base = loaded;
        const queue = deps.queue();
        try {
            const fresh = await loadStored();
            await start(own && base ? mergeLayout(own, base, fresh) : fresh);
            if (own && base) {
                queue?.resolveStructureChange(props.versions.structure, props.versions.layout);
                queue?.changed();
            } else queue?.discard(props.versions);
            if (message !== '') deps.say(message);
            if (keepOwn) deps.flow().afterStructureRefresh();
            else deps.flow().structureReload(message !== '');
        } finally {
            refreshing = false;
            (focusTo?.isConnected ? focusTo : host.value)?.focus();
        }
    }

    onBeforeUnmount(() => {
        unmounted = true;
        release();
    });

    return { state, engine, drawn, selected, seen, zoom, view, canUndo, canRedo, verdict, hint, setTool, pick, start, restart, loadStored, refresh };
}
