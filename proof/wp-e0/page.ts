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
import { canvasMeasurer } from './measure';
import { readBlocks } from './roundtrip';
import { buildScene } from './scene';

const fixture = fixtureJson as Fixture;

async function start() {
    lockFontSources();
    await getCanvasKit({ locateFile: () => wasmUrl });
    await loadBrowserFonts();
    await registerFaces();
    const blocks = computeBlocks(fixture, canvasMeasurer());
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
    const ready = new Promise<void>((resolve) => {
        createApp({ render: () => h(Proof, { editor, onReady: resolve }) }).mount('#app');
    });
    await ready;
    Object.assign(window, { proof: { editor, graph, fixture, blocks, readBlocks: () => readBlocks(graph), watchFinishedActions: (listener: (action: { ids: string[]; at: number }) => void) => watchFinishedActions(editor, graph, listener) } });
    document.documentElement.dataset.ready = 'true';
}

start().catch((error) => {
    document.documentElement.dataset.error = String(error?.stack ?? error);
});
