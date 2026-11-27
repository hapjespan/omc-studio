import type { Editor } from '@open-pencil/core/editor';
import type { SceneNode } from '@open-pencil/scene-graph';
import type { DrawnCanvas, LayoutElement, TextMeasurer } from '../layout';
import { AMOUNT_ROOM, COUNTS_STRIP } from '../layout/flowDomains';
import { isListArea } from '../layout/arrangeAreas';
import { blockPadding, MODALITY_BADGE } from '../layout/padding';
import { resolveStyle } from '../layout/resolveStyle';
import { wrapText } from '../layout/wrapText';
import { constrain, MIN_HEIGHT, MIN_WIDTH, type Box } from './constraints';
import { storedStyle } from './commands/style';
import { snapChanges } from './commands/grid';
import { TEXT_SUFFIX, textBox } from './scene';
import type { Restrictions } from './restrictions';

type Shape = Box & { text?: string };
type Shapes = Map<string, Shape>;

function box(node: Box): Box {
    return { x: node.x, y: node.y, width: node.width, height: node.height };
}

export function installDomainResize(editor: Editor, canvas: DrawnCanvas, elements: LayoutElement[], measure: TextMeasurer, guard: Restrictions, sized: Set<string>, grid: () => boolean): () => void {
    const graph = editor.graph;
    const blocks = new Map(canvas.blocks.map((block) => [block.id, block]));
    const names = new Map(elements.map((element) => [element.id, element.name]));
    const children = (id: string) => graph.getNode(id)!.childIds.filter((child) => blocks.has(child));
    const capture = (root: string): Shapes => {
        const shapes: Shapes = new Map();
        const visit = (id: string): void => {
            shapes.set(id, box(graph.getNode(id)!));
            const label = graph.getNode(id + TEXT_SUFFIX);
            if (label) shapes.set(label.id, { ...box(label), text: label.text });
            children(id).forEach(visit);
        };
        visit(root);
        return shapes;
    };

    function fit(root: string, before: Shapes, requested: Partial<Box>): Shapes {
        const parent = graph.getNode(graph.getNode(root)!.parentId!)!;
        const original = before.get(root)!;
        const minimum = [...before.keys()].some((id) => blocks.get(id)?.modality) ? MIN_WIDTH + MODALITY_BADGE : MIN_WIDTH;
        const wanted = requested.width === undefined ? requested : { ...requested, width: Math.max(minimum, requested.width) };
        const rootBox = { ...original, ...constrain(original, wanted, { kind: 'parent', width: parent.width, height: parent.height }, 0).changes };
        const compactHeight = requested.height !== undefined && requested.height < original.height;
        const after: Shapes = new Map();
        function place(id: string, frame: Box): void {
            const block = blocks.get(id)!;
            const style = resolveStyle(block.areaKey, block.kind, storedStyle(graph, id));
            const pad = blockPadding(block);
            const labelWidth = textBox(block, frame.width, frame.height).width;
            const lines = wrapText(names.get(id) ?? block.textLines.join(' '), labelWidth, style.font, measure);
            const heading = pad.y + lines.length * style.lineHeight;
            const placed: Box[] = [];
            for (const child of children(id).sort((a, b) => before.get(a)!.y - before.get(b)!.y)) {
                const old = before.get(child)!;
                const inset = Math.min(pad.x, Math.max(0, Math.floor((frame.width - minimum) / 2)));
                const width = Math.min(old.width, frame.width - 2 * inset);
                const x = Math.max(0, Math.min(old.x, frame.width - inset - width));
                let y = Math.max(heading + 4, old.y);
                if (compactHeight) y = heading + 4;
                for (const other of placed) {
                    if (x < other.x + other.width && x + width > other.x && y < other.y + other.height + 4 && y + old.height > other.y) y = other.y + other.height + 4;
                }
                place(child, { x, y, width, height: compactHeight ? MIN_HEIGHT : old.height });
                placed.push(after.get(child)!);
            }
            const bottom = Math.max(heading, ...placed.map((child) => child.y + child.height));
            const room = block.kind === 'domain' && !isListArea(block.areaKey) ? Math.max(heading + pad.y + AMOUNT_ROOM, bottom + pad.y + COUNTS_STRIP) : bottom + pad.y;
            frame.height = Math.max(frame.height, MIN_HEIGHT, room);
            after.set(id, frame);
            if (before.has(id + TEXT_SUFFIX)) {
                after.set(id + TEXT_SUFFIX, { ...textBox({ ...block, style, textLines: lines }, frame.width, frame.height), text: lines.join('\n') });
            }
        }
        place(root, rootBox);
        return after;
    }

    const paint = (shapes: Shapes, preview = false): void => guard.privileged(() => {
        for (const [id, shape] of shapes) {
            if (preview) graph.updateNodePreview(id, shape);
            else graph.updateNode(id, shape);
        }
    });
    function commit(before: Shapes, after: Shapes, label: string): void {
        const resized = [...after.keys()].filter((id) => blocks.has(id) && after.get(id)!.height !== before.get(id)!.height);
        const previouslySized = resized.filter((id) => sized.has(id));
        const forward = () => { resized.forEach((id) => sized.add(id)); paint(after); };
        forward();
        editor.undo.push({ label, forward, inverse: () => {
            resized.forEach((id) => sized.delete(id));
            previouslySized.forEach((id) => sized.add(id));
            paint(before);
        } });
    }

    const shrinking = (id: string, changes: Partial<Box>) => {
        const node = graph.getNode(id);
        return blocks.get(id)?.kind === 'domain' && node && ((changes.width ?? node.width) < node.width || (changes.height ?? node.height) < node.height);
    };
    const update = editor.updateNodeWithUndo.bind(editor);
    editor.updateNodeWithUndo = (id, changes, label) => {
        if (!shrinking(id, changes)) return update(id, changes, label);
        const before = capture(id);
        commit(before, fit(id, before, changes), label ?? 'Resize');
    };

    const preview = graph.updateNodePreview.bind(graph);
    const finish = editor.commitGroupResize.bind(editor);
    let drag: { id: string; before: Shapes; after?: Shapes } | undefined;
    let skipChildren: Shapes | undefined;
    graph.updateNodePreview = (id, changes: Partial<SceneNode>) => {
        if (skipChildren?.has(id) && id !== drag?.id) return;
        const node = graph.getNode(id);
        if (!drag && blocks.get(id)?.kind === 'domain' && node && changes.width !== undefined && changes.height !== undefined
            && (changes.width !== node.width || changes.height !== node.height)) {
            drag = { id, before: capture(id) };
        }
        if (drag?.id !== id || changes.width === undefined) return preview(id, changes);
        const initial = drag.before.get(id)!;
        const restored = changes.width === initial.width && changes.height === initial.height && changes.x === initial.x && changes.y === initial.y;
        const smaller = changes.width < initial.width || (changes.height ?? initial.height) < initial.height;
        if (!smaller && !restored) {
            if (drag.after) paint(drag.before, true);
            drag.after = undefined;
            return preview(id, changes);
        }
        const next = restored ? drag.before : fit(id, drag.before, grid() ? snapChanges(changes) : changes);
        if (!restored) drag.after = next;
        paint(next, true);
        skipChildren = drag.before;
        queueMicrotask(() => { skipChildren = undefined; if (restored) drag = undefined; });
    };
    editor.commitGroupResize = (id, original, descendants) => {
        if (!drag || drag.id !== id) return finish(id, original, descendants);
        const { before, after } = drag;
        drag = undefined;
        skipChildren = undefined;
        if (after) commit(before, after, 'Resize');
        else finish(id, original, descendants);
    };
    return () => {
        editor.updateNodeWithUndo = update;
        graph.updateNodePreview = preview;
        editor.commitGroupResize = finish;
    };
}
