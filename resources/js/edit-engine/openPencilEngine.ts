import { getCanvasKit } from '@open-pencil/core/canvaskit';
import { createDefaultEditorState, createEditor, type Editor } from '@open-pencil/core/editor';
import type { SceneGraph } from '@open-pencil/scene-graph';
import wasmUrl from 'canvaskit-wasm/bin/canvaskit.wasm?url';
import { createApp, h, reactive, type App } from 'vue';
import type { DrawnCanvas } from '../layout';
import { bundledIcons, closeNetworkBoundary } from './boundary';
import DrawingSurface from './DrawingSurface.vue';
import { loadFace, registerFonts } from './fonts';
import type { DrawingCommand, DrawingEngine, FinishedAction, LayoutPayload, MountOptions } from './DrawingEngine';
import { toPayload } from './payload';
import { restrict } from './restrictions';
import { buildScene, readPlaces } from './scene';
import { keepSelectable, watchSelection } from './selection';

const DIRECTION_ID = 'direction';

export class OpenPencilEngine implements DrawingEngine {
    private editor: Editor | null = null;
    private graph: SceneGraph | null = null;
    private canvas: DrawnCanvas | null = null;
    private base: LayoutPayload | null = null;
    private app: App | null = null;
    private readonly listeners = new Set<(change: FinishedAction) => void>();
    private readonly stops: (() => void)[] = [];

    async mount(element: HTMLElement, scene: DrawnCanvas, options: MountOptions): Promise<void> {
        closeNetworkBoundary();
        await getCanvasKit({ locateFile: () => wasmUrl });
        await registerFonts();
        const graph = buildScene(scene);
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
        this.editor = editor;
        const locked = [DIRECTION_ID];
        this.stops.push(restrict(editor, graph, locked, options.onTextAttempt).release, keepSelectable(editor, locked));
        this.watch(editor, graph, scene);
        await new Promise<void>((resolve) => {
            this.app = createApp({ render: () => h(DrawingSurface, { editor, onReady: resolve }) });
            this.app.mount(element);
        });
    }

    getLayout(): LayoutPayload {
        const { base, canvas, graph } = this.mounted();
        return toPayload(base, canvas, readPlaces(graph, canvas));
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

    select(ids: string[]): void {
        this.mounted().editor.select(ids);
    }

    apply(command: DrawingCommand): void {
        throw new Error(`The drawing command ${command.kind} is not built yet.`);
    }

    undo(): void {
        this.mounted().editor.undoAction();
    }

    redo(): void {
        this.mounted().editor.redoAction();
    }

    zoomToFit(): void {
        this.mounted().editor.zoomToFit();
    }

    getZoom(): number {
        return this.mounted().editor.state.zoom;
    }

    setTool(tool: 'select' | 'pan'): void {
        this.mounted().editor.setTool(tool === 'pan' ? 'HAND' : 'SELECT');
    }

    destroy(): void {
        for (const stop of this.stops.splice(0)) stop();
        this.listeners.clear();
        this.app?.unmount();
        this.app = null;
        this.editor?.dispose();
        this.editor = null;
        this.graph = null;
        this.canvas = null;
        this.base = null;
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
                if (touched.size === 0) return;
                const ids = [...touched];
                touched.clear();
                for (const listener of this.listeners) listener({ kind: 'layout', ids });
            }),
        );
    }
}
