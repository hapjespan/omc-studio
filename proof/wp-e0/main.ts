import { createEditor } from '@open-pencil/core/editor';
import { SceneGraph } from '@open-pencil/scene-graph';

export function createProofEditor() {
    const graph = new SceneGraph();
    const editor = createEditor({ graph, skipInitialGraphSetup: true });
    return { graph, editor };
}
