import type { SceneGraph } from '@open-pencil/scene-graph';
import type { DrawnBlock, DrawnCanvas, ElementStyle, TextMeasurer } from '../../layout';
import { resolveStyle } from '../../layout/resolveStyle';
import { wrapText } from '../../layout/wrapText';
import type { LayoutStyle } from '../DrawingEngine';
import { fillPaint, ALIGN_HORIZONTAL, strokePaint, TEXT_SUFFIX, textBox } from '../scene';
import type { Selection } from '../transform';

export type StylePatch = Record<string, string | number | boolean | null>;

export interface StyleChange {
    ids: string[];
}

export interface StyleHooks {
    measurer?: TextMeasurer;
}

const DATA = { pluginId: 'omc-studio', key: 'style' };

export function storedStyle(graph: SceneGraph, id: string): LayoutStyle {
    const entry = graph.getNode(id)?.pluginData.find((data) => data.pluginId === DATA.pluginId && data.key === DATA.key);
    return entry ? (JSON.parse(entry.value) as LayoutStyle) : {};
}

export function mergeStyle(current: LayoutStyle, patch: StylePatch): LayoutStyle {
    const merged: LayoutStyle = { ...current };
    for (const [key, value] of Object.entries(patch)) {
        if (value === null) delete merged[key];
        else merged[key] = value;
    }
    return merged;
}

export const styleData = (style: LayoutStyle) => (Object.keys(style).length === 0 ? [] : [{ ...DATA, value: JSON.stringify(style) }]);

export function paintBlock(selection: Selection, block: DrawnBlock, style: LayoutStyle, measurer?: TextMeasurer): void {
    const { editor } = selection;
    const frame = editor.graph.getNode(block.id);
    if (!frame) return;
    const resolved = resolveStyle(block.areaKey, block.kind === 'area' ? 'area' : block.kind, style as ElementStyle);
    editor.updateNodeWithUndo(
        block.id,
        {
            fills: resolved.fill ? [fillPaint(resolved.fill, resolved.fillOpacity)] : [],
            strokes: resolved.border ? [strokePaint(resolved.border, resolved.borderWidth, resolved.borderStyle === 'dashed')] : [],
            cornerRadius: resolved.radius,
            pluginData: styleData(style),
        },
        'Style',
    );
    if (!editor.graph.getNode(block.id + TEXT_SUFFIX)) return;
    const drawn = { ...block, style: resolved };
    const width = textBox(drawn, frame.width, frame.height).width;
    const lines = measurer ? wrapText(block.textLines.join(' '), width, resolved.font, measurer) : block.textLines;
    editor.updateNodeWithUndo(
        block.id + TEXT_SUFFIX,
        {
            ...textBox({ ...drawn, textLines: lines }, frame.width, frame.height),
            text: lines.join('\n'),
            fontFamily: resolved.font.family,
            fontSize: resolved.font.size,
            fontWeight: resolved.font.weight,
            italic: resolved.font.italic,
            lineHeight: resolved.lineHeight,
            fills: [fillPaint(resolved.textColour, 100)],
            textAlignHorizontal: ALIGN_HORIZONTAL[resolved.alignHorizontal],
        },
        'Style',
    );
}

export function setStyle(selection: Selection, canvas: DrawnCanvas, patch: StylePatch, hooks: StyleHooks = {}): StyleChange {
    const blocks = new Map(canvas.blocks.map((block) => [block.id, block]));
    const targets = selection.ids.flatMap((id) => (id === 'direction' || !blocks.has(id) ? [] : [blocks.get(id) as DrawnBlock]));
    if (targets.length === 0) return { ids: [] };
    selection.editor.undo.runBatch('Style', () => {
        for (const block of targets) paintBlock(selection, block, mergeStyle(storedStyle(selection.editor.graph, block.id), patch), hooks.measurer);
    });
    return { ids: targets.map((block) => block.id) };
}
