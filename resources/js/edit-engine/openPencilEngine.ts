import { getCanvasKit } from '@open-pencil/core/canvaskit';
import { createDefaultEditorState, createEditor, type Editor } from '@open-pencil/core/editor';
import type { SceneGraph } from '@open-pencil/scene-graph';
import wasmUrl from 'canvaskit-wasm/bin/canvaskit.wasm?url';
import { createApp, h, reactive, type App } from 'vue';
import type { DrawnCanvas, TextMeasurer } from '../layout';
import { bundledIcons, closeNetworkBoundary } from './boundary';
import DrawingSurface from './DrawingSurface.vue';
import { loadFace, registerFonts } from './fonts';
import type { BoxValues, DrawingCommand, DrawingEngine, FinishedAction, LayoutPayload, LayoutRect, LayoutStyle, MountOptions, ShiftVerdictEvent } from './DrawingEngine';
import { MoveHistory } from './moveHistory';
import { attachShiftMove, type ShiftMoveHandle } from './shiftGesture';
import { buildMoveModel } from './shiftMove';
import { toPayload } from './payload';
import { align, distribute, equalSize } from './commands/align';
import { installDomainResize } from './domainResize';
import { snapChanges } from './commands/grid';
import { backward, forward, stackOrders } from './commands/order';
import { restrict, type Restrictions } from './restrictions';
import { applyStyleToSiblings } from './commands/applyStyle';
import { copyStyle, pasteStyle, type CopiedStyle } from './commands/pasteStyle';
import { setStyle, storedStyle, styleData, type StyleChange, type StylePatch } from './commands/style';
import { boxOf, nudge, resizeBy, setBox } from './transform';
import { resolveStyle } from '../layout/resolveStyle';
import { ARTBOARD_ID, buildScene, fillPaint, readPlaces, TEXT_SUFFIX } from './scene';
import { keepSelectable, watchSelection } from './selection';

const DIRECTION_ID = 'direction';
const READY_TIMEOUT = 20000;

export class OpenPencilEngine implements DrawingEngine {
    private editor: Editor | null = null;
    private graph: SceneGraph | null = null;
    private canvas: DrawnCanvas | null = null;
    private base: LayoutPayload | null = null;
    private restrictions: Restrictions | null = null;
    private measurer: TextMeasurer | undefined;
    private app: App | null = null;
    private grid = false;
    private readonly sized = new Set<string>();
    private readonly pushed = new Set<string>();
    private readonly reordered = new Set<string>();
    private readonly moved = new Set<string>();
    private onPushBack: ((ids: string[]) => void) | undefined;
    private shiftMove: ShiftMoveHandle | null = null;
    private history: MoveHistory | null = null;
    private readonly listeners = new Set<(change: FinishedAction) => void>();
    private readonly stops: (() => void)[] = [];

    async mount(element: HTMLElement, scene: DrawnCanvas, options: MountOptions): Promise<void> {
        closeNetworkBoundary();
        await getCanvasKit({ locateFile: () => wasmUrl });
        await registerFonts();
        const graph = buildScene(scene);
        for (const block of scene.blocks) {
            const style = options.layout.areas[block.id]?.style ?? (options.layout.elements[block.id]?.mode === 'manual' ? (options.layout.elements[block.id] as { style?: LayoutStyle }).style : undefined);
            if (style) graph.updateNode(block.id, { pluginData: styleData(style) });
        }
        const page = graph.getPages()[0];
        const editor = createEditor({
            graph,
            state: reactive(createDefaultEditorState(page.id)),
            icons: bundledIcons,
            loadFont: (family, style) => loadFace(family, style),
            getViewportSize: () => ({ width: element.clientWidth, height: element.clientHeight }),
            skipInitialGraphSetup: true,
        });
        this.graph = graph;
        this.canvas = scene;
        this.base = options.layout;
        this.onPushBack = options.onPushBack;
        this.editor = editor;
        const locked = [DIRECTION_ID];
        this.restrictions = restrict(editor, graph, locked, options.onTextAttempt, [ARTBOARD_ID], { onPushBack: (id) => this.pushed.add(id), onResize: (id) => this.sized.add(id), snap: () => this.grid });
        this.measurer = options.measurer;
        this.stops.push(this.restrictions.release, keepSelectable(editor, [...locked, ARTBOARD_ID]));
        const restrictions = this.restrictions;
        if (options.measurer && options.structure) this.stops.unshift(installDomainResize(editor, scene, options.structure.elements, options.measurer, restrictions, this.sized, () => this.grid));
        this.watch(editor, graph, scene);
        await new Promise<void>((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('The drawing surface did not become ready.')), READY_TIMEOUT);
            this.app = createApp({
                render: () =>
                    h(DrawingSurface, {
                        editor,
                        onReady: () => {
                            clearTimeout(timer);
                            resolve();
                        },
                    }),
            });
            this.app.mount(element);
        });
        const surface = element.querySelector<HTMLCanvasElement>('canvas[data-drawing-surface]');
        if (options.structure && surface) {
            this.shiftMove = attachShiftMove({
                editor,
                graph,
                restrictions,
                canvas: surface,
                model: buildMoveModel(scene, options.structure),
                onIntent: (move) => this.history?.dragged(move),
                onRefusal: options.onMoveRefused,
            });
            this.history = new MoveHistory(editor, this.shiftMove.placement, (id) => this.manualLayout(id), (intent) => this.tell(intent));
            this.stops.push(this.shiftMove.detach);
        }
    }

    getLayout(): LayoutPayload {
        const { base, canvas, graph } = this.mounted();
        const { editor } = this.mounted();
        return toPayload(base, canvas, readPlaces(graph, canvas, this.sized), stackOrders(editor, [...this.reordered], (id) => id.endsWith(TEXT_SUFFIX), (id) => this.moved.has(id) || base.elements[id]?.mode === 'manual' || id in base.areas), new Map(canvas.blocks.map((block) => [block.id, storedStyle(graph, block.id)])));
    }

    getSelection(): string[] {
        return [...this.mounted().editor.state.selectedIds];
    }

    onSelectionChange(listener: (ids: string[]) => void): () => void {
        return watchSelection(this.mounted().editor, listener);
    }

    onChange(listener: (change: FinishedAction) => void): () => void {
        this.listeners.add(listener);
        return () => this.listeners.delete(listener);
    }

    onShiftVerdict(listener: (event: ShiftVerdictEvent | null) => void): () => void {
        return this.shiftMove?.onVerdict(listener) ?? (() => undefined);
    }

    select(ids: string[]): void {
        this.mounted().editor.select(ids);
    }

    apply(command: DrawingCommand): void {
        const { editor } = this.mounted();
        const selection = { editor, ids: [...editor.state.selectedIds] };
        switch (command.kind) {
            case 'setBox':
                return setBox(editor, command.id, this.grid ? snapChanges(command.box) : command.box);
            case 'nudge':
                return nudge(selection, command.dx, command.dy);
            case 'resizeBy':
                return resizeBy(selection, command.dw, command.dh);
            case 'headerColour': {
                const style = resolveStyle('direction', 'area', { fill: command.fill });
                return this.guarded(() => editor.undo.runBatch('Header colour', () => {
                    editor.updateNodeWithUndo(DIRECTION_ID, { fills: [fillPaint(command.fill, 100)], pluginData: styleData({ fill: command.fill }) });
                    for (const id of editor.graph.getNode(DIRECTION_ID)!.childIds) {
                        const node = editor.graph.getNode(id);
                        if (node?.type === 'TEXT') editor.updateNodeWithUndo(id, { fills: [fillPaint(id.includes('-label') ? style.labelColour : style.textColour, 100)] });
                        if (node?.type === 'FRAME') editor.updateNodeWithUndo(id, { strokes: node.strokes.map((stroke) => ({ ...stroke, color: fillPaint(style.textColour, 100).color })) });
                    }
                }));
            }
            case 'setGrid':
                this.grid = command.on;
                return;
            case 'align':
                return align(selection, command.axis, command.to);
            case 'distribute':
                return distribute(selection, command.axis);
            case 'equalWidth':
                return equalSize(selection, 'width');
            case 'equalHeight':
                return equalSize(selection, 'height');
            case 'bringForward':
                return this.reorder(selection, forward);
            case 'sendBackward':
                return this.reorder(selection, backward);
            case 'previewMove':
                return this.moves().previewMove(command.elementId, command.targetId);
            case 'commitMove':
                return this.moves().commitMove(command.elementId, command.targetId, command.layout, command.mode);
            case 'revertMove':
                return this.moves().revertMove(command.elementId);
            case 'cancelMoveHistory':
                return this.moves().cancel(command.direction);
            case 'clearHistory':
                return this.moves().clear();
            default:
                throw new Error(`The drawing command ${command.kind} is not built yet.`);
        }
    }

    setStyle(patch: StylePatch): StyleChange {
        const { editor, canvas } = this.mounted();
        const result = this.guarded(() => setStyle({ editor, ids: [...editor.state.selectedIds] }, canvas, patch, { measurer: this.measurer }));
        return result;
    }

    pasteStyle(copied: CopiedStyle): StyleChange {
        const { editor, canvas } = this.mounted();
        const result = this.guarded(() => pasteStyle({ editor, ids: [...editor.state.selectedIds] }, canvas, copied, { measurer: this.measurer }));
        return result;
    }

    applyStyleToSiblings(id: string): StyleChange {
        const { editor, canvas } = this.mounted();
        const result = this.guarded(() => applyStyleToSiblings({ editor, ids: [] }, canvas, id, { measurer: this.measurer }));
        return result;
    }

    copyStyle(id: string): CopiedStyle | null {
        const { graph, canvas } = this.mounted();
        return copyStyle(graph, canvas, id);
    }

    getStyle(id: string): LayoutStyle {
        return storedStyle(this.mounted().graph, id);
    }

    getBox(id: string): BoxValues | null {
        return boxOf(this.mounted().editor, id);
    }

    canUndo(): boolean {
        return this.mounted().editor.undo.canUndo;
    }

    canRedo(): boolean {
        return this.mounted().editor.undo.canRedo;
    }

    private moves(): MoveHistory {
        if (!this.history) throw new Error('The drawing engine was mounted without the structure the move needs.');
        return this.history;
    }

    private manualLayout(id: string): LayoutRect | null {
        const entry = this.getLayout().elements[id];
        return entry?.mode === 'manual' ? { x: entry.x, y: entry.y, width: entry.width, height: entry.height, z: entry.z, ...(entry.style ? { style: entry.style } : {}) } : null;
    }

    private tell(change: FinishedAction): void {
        for (const listener of this.listeners) listener(change);
    }

    private reorder(selection: { editor: Editor; ids: string[] }, command: (selection: { editor: Editor; ids: string[] }) => void): void {
        for (const id of selection.ids) {
            const parent = this.mounted().graph.getNode(id)?.parentId;
            if (parent) this.reordered.add(parent);
            this.moved.add(id);
        }
        command(selection);
    }

    undo(): void {
        this.guarded(() => this.mounted().editor.undoAction());
    }

    redo(): void {
        this.guarded(() => this.mounted().editor.redoAction());
    }

    zoomToFit(): void {
        this.mounted().editor.zoomToFit();
    }

    getZoom(): number {
        return this.mounted().editor.state.zoom;
    }

    zoomTo100(): void {
        this.mounted().editor.zoomTo100();
    }

    zoomTo(level: number): void {
        this.mounted().editor.zoomToLevel(level);
    }

    panBy(dx: number, dy: number): void {
        this.mounted().editor.pan(dx, dy);
    }

    getView(): { x: number; y: number; zoom: number } {
        const { panX, panY, zoom } = this.mounted().editor.state;
        return { x: panX, y: panY, zoom };
    }

    setTool(tool: 'select' | 'pan'): void {
        this.mounted().editor.setTool(tool === 'pan' ? 'HAND' : 'SELECT');
    }

    destroy(): void {
        for (const stop of this.stops.splice(0)) stop();
        this.shiftMove = null;
        this.history = null;
        this.listeners.clear();
        this.app?.unmount();
        this.app = null;
        this.editor?.dispose();
        this.editor = null;
        this.graph = null;
        this.canvas = null;
        this.base = null;
        this.restrictions = null;
    }

    private guarded<T>(action: () => T): T {
        return this.restrictions ? this.restrictions.privileged(action) : action();
    }

    private mounted() {
        if (!this.editor || !this.graph || !this.canvas || !this.base) throw new Error('The drawing engine is not mounted.');
        return { editor: this.editor, graph: this.graph, canvas: this.canvas, base: this.base };
    }

    private watch(editor: Editor, graph: SceneGraph, scene: DrawnCanvas): void {
        const known = new Set(scene.blocks.map((block) => block.id));
        const touched = new Set<string>();
        const note = (id: string) => {
            if (known.has(id)) touched.add(id);
        };
        this.stops.push(graph.onNodeEvents({ created: (node) => note(node.id), updated: note, deleted: note, reparented: note, reordered: note }));
        this.stops.push(
            editor.onEditorEvent('history:changed', () => {
                if (this.pushed.size > 0) {
                    const pushed = [...this.pushed];
                    this.pushed.clear();
                    this.onPushBack?.(pushed);
                }
                if (touched.size === 0) return;
                const ids = [...touched];
                touched.clear();
                this.tell({ kind: 'layout', ids });
            }),
        );
    }
}
