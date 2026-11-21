import { createEditor } from '@open-pencil/core/editor';
import { computeCanvasLayout } from '../../../resources/js/layout';
import type { CanvasLayoutInput, LayoutElement } from '../../../resources/js/layout';
import { restrict, type RestrictionHooks } from '../../../resources/js/edit-engine/restrictions';
import { buildScene } from '../../../resources/js/edit-engine/scene';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { withElements } from '../layout/support';

export function open(elements: LayoutElement[], layouts: CanvasLayoutInput['layouts'] = {}, hooks: RestrictionHooks = {}) {
    const canvas = computeCanvasLayout({ ...withElements(elements), layouts }, fakeMeasurer);
    const graph = buildScene(canvas);
    const editor = createEditor({ graph, skipInitialGraphSetup: true });
    const restrictions = restrict(editor, graph, ['direction'], () => undefined, ['artboard'], hooks);
    const selection = (ids: string[]) => ({ editor, ids });
    return { graph, editor, restrictions, selection, canvas };
}

export const manual = (x: number, y: number, width: number, height: number, z = 2) => ({ mode: 'manual' as const, x, y, width, height, z });
