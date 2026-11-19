import { createApp, h, reactive } from 'vue';
import { createDefaultEditorState, createEditor } from '@open-pencil/core/editor';
import { getCanvasKit } from '@open-pencil/core/canvaskit';
import { SceneGraph } from '@open-pencil/scene-graph';
import wasmUrl from 'canvaskit-wasm/bin/canvaskit.wasm?url';
import Proof from './Proof.vue';
import fixtureJson from './fixtures/scene.json';
import { loadBrowserFonts, loadFace, lockFontSources, registerFaces } from './fonts';
import { computeBlocks, type Fixture } from './layout';
import { watchFinishedActions } from './changes';
import { renderHtmlView } from './html-view';
import { canvasMeasurer } from './measure';
import { collectLoad, scaledFixture } from './measure-load';
import { inertShortcuts, restrict } from './restrict';
import { readBlocks } from './roundtrip';
import { attachShiftMove, type MoveIntent } from './shift-move';
import { buildScene } from './scene';

const bulk = Number(new URLSearchParams(location.search).get('bulk') ?? 0);
const fixture = (bulk > 0 ? scaledFixture(fixtureJson as Fixture, bulk) : fixtureJson) as Fixture;

const remoteFontControl = new URLSearchParams(location.search).has('control-remote-font');

async function start() {
    if (!remoteFontControl) lockFontSources();
    await getCanvasKit({ locateFile: () => wasmUrl });
    await loadBrowserFonts();
    await registerFaces();
    const blocks = computeBlocks(fixture, canvasMeasurer());
    if (new URLSearchParams(location.search).get('view') === 'html') {
        renderHtmlView(document.getElementById('app')!, blocks, fixture.artboard);
        Object.assign(window, { proof: { fixture, blocks } });
        document.documentElement.dataset.ready = 'true';
        return;
    }
    const graph = new SceneGraph();
    buildScene(graph, fixture, blocks);
    const page = graph.getPages()[0];
    const editor = createEditor({
        graph,
        state: reactive(createDefaultEditorState(page.id)),
        loadFont: (family, style) => loadFace(family, style),
        getViewportSize: () => ({ width: window.innerWidth, height: window.innerHeight }),
        skipInitialGraphSetup: true,
    });
    if (remoteFontControl) graph.updateNode('act-1#text', { fontFamily: 'Roboto', fontWeight: 400 });
    const restrictions = restrict(editor as any, graph, ['area-direction', 'area-logo']);
    const ready = new Promise<void>((resolve) => {
        createApp({ render: () => h(Proof, { editor, onReady: resolve }) }).mount('#app');
    });
    await ready;
    const intents: MoveIntent[] = [];
    const label = document.createElement('div');
    label.className = 'shift-label';
    label.id = 'shift-label';
    label.hidden = true;
    document.body.append(label);
    const shiftMove = new URLSearchParams(location.search).has('no-shift-move')
        ? null
        : attachShiftMove({ editor, graph, restrictions, blocks, canvas: document.getElementById('stage') as HTMLCanvasElement, label, onIntent: (intent) => intents.push(intent) });
    Object.assign(window, { proof: { editor, graph, fixture, blocks, restrictions, inertShortcuts, intents, shiftMove, readBlocks: () => readBlocks(graph), watchFinishedActions: (listener: (action: { ids: string[]; at: number }) => void) => watchFinishedActions(editor, graph, listener) } });
    Object.assign((window as any).proof, { load: collectLoad(performance.now()) });
    document.documentElement.dataset.ready = 'true';
}

start().catch((error) => {
    document.documentElement.dataset.error = String(error?.stack ?? error);
});
