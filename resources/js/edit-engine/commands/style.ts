import type { SceneGraph } from '@open-pencil/scene-graph';
import type { DrawnBlock, DrawnCanvas, ElementStyle, TextMeasurer } from '../../layout';
import { contrastRatio, MINIMUM_CONTRAST } from '../../colour';
import { backdropOf, behindOf } from '../../layout/backdrop';
import { AREA_LETTER_SPACING, AREA_PADDING } from '../../layout/arrangeAreas';
import { blockPadding } from '../../layout/padding';
import { resolveStyle } from '../../layout/resolveStyle';
import { wrapText } from '../../layout/wrapText';
import type { LayoutStyle } from '../DrawingEngine';
import { fillPaint, ALIGN_HORIZONTAL, strokePaint, TEXT_SUFFIX, textBox } from '../scene';
import type { Selection } from '../transform';

export type StylePatch = Record<string, string | number | boolean | null>;

export interface StyleChange {
    ids: string[];
    autoText: string[];
}

export interface StyleHooks {
    measurer?: TextMeasurer;
    checkText?: boolean;
}

const TYPOGRAPHY = ['font', 'font_size', 'font_weight', 'italic'];

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

export function paintBlock(selection: Selection, block: DrawnBlock, style: LayoutStyle, behind: string, measurer?: TextMeasurer, retype = true): void {
    const { editor } = selection;
    const frame = editor.graph.getNode(block.id);
    if (!frame) return;
    const resolved = resolveStyle(block.areaKey, block.kind === 'area' ? 'area' : block.kind, style as ElementStyle, behind);
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
    const current = editor.graph.getNode(block.id + TEXT_SUFFIX)?.text.split('\n') ?? block.textLines;
    let lines = current;
    let height = frame.height;
    if (retype) {
        const whole = block.textLines.join(' ');
        const width = block.kind === 'area' ? frame.width - 2 * AREA_PADDING - whole.length * AREA_LETTER_SPACING * resolved.font.size : textBox(drawn, frame.width, frame.height).width;
        lines = measurer ? wrapText(whole, width, resolved.font, measurer) : block.textLines;
        const pad = blockPadding(block);
        height = Math.max(frame.height, 2 * pad.y + lines.length * resolved.lineHeight);
        if (height > frame.height) editor.updateNodeWithUndo(block.id, { height }, 'Style');
    }
    editor.updateNodeWithUndo(
        block.id + TEXT_SUFFIX,
        {
            ...textBox({ ...drawn, textLines: lines }, frame.width, height),
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

const descendants = (blocks: Map<string, DrawnBlock>, roots: Set<string>): Set<string> => {
    const found = new Set(roots);
    for (let grew = true; grew; ) {
        grew = false;
        for (const block of blocks.values()) {
            const parent = block.kind === 'area' ? null : (block.parentId ?? block.areaKey);
            if (parent === null || !found.has(parent) || found.has(block.id)) continue;
            found.add(block.id);
            grew = true;
        }
    }
    return found;
};

export function setStyle(selection: Selection, canvas: DrawnCanvas, patch: StylePatch, hooks: StyleHooks = {}): StyleChange {
    const blocks = new Map(canvas.blocks.map((block) => [block.id, block]));
    const targets = selection.ids.filter((id) => id !== 'direction' && blocks.has(id));
    if (targets.length === 0) return { ids: [], autoText: [] };
    const { graph } = selection.editor;
    const styles = new Map(canvas.blocks.map((block) => [block.id, storedStyle(graph, block.id)]));
    for (const id of targets) styles.set(id, mergeStyle(styles.get(id) as LayoutStyle, patch));
    const resolved = (id: string) => resolveStyle((blocks.get(id) as DrawnBlock).areaKey, (blocks.get(id) as DrawnBlock).kind, styles.get(id) as ElementStyle);
    const autoText: string[] = [];
    const repaint = 'fill' in patch || 'fill_opacity' in patch || hooks.checkText === true;
    const typed = Object.keys(patch).some((key) => TYPOGRAPHY.includes(key));
    const affected = repaint ? descendants(blocks, new Set(targets)) : new Set(targets);
    if (repaint) {
        for (const id of affected) {
            const own = styles.get(id)?.text_colour;
            if (typeof own === 'string' && contrastRatio(own, backdropOf(blocks.get(id) as DrawnBlock, blocks, resolved)) < MINIMUM_CONTRAST) {
                styles.set(id, mergeStyle(styles.get(id) as LayoutStyle, { text_colour: null }));
                autoText.push(id);
            }
        }
    }
    selection.editor.undo.runBatch('Style', () => {
        for (const id of affected) paintBlock(selection, blocks.get(id) as DrawnBlock, styles.get(id) as LayoutStyle, behindOf(blocks.get(id) as DrawnBlock, blocks, resolved), hooks.measurer, typed && targets.includes(id));
    });
    return { ids: [...new Set([...targets, ...autoText])], autoText };
}
