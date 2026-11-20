import { autoTextColour, defaultColours, effectiveFill } from '../colour';
import { supportsItalic } from './fonts';
import { isListArea } from './arrangeAreas';
import type { AreaKey, BlockStyle, ElementStyle, FontSpec } from './types';

export type StyleLevel = 'area' | 'domain' | 'cluster' | 'activity';

const HAIRLINE = '#DCDFE2';

const font = (family: FontSpec['family'], size: number, weight: number): FontSpec => ({ family, size, weight, italic: false });

const typography = (area: AreaKey, level: StyleLevel): Pick<BlockStyle, 'font' | 'lineHeight' | 'radius'> => {
    if (level === 'area') return { font: font('Space Grotesk', 13, 600), lineHeight: 18, radius: 10 };
    if (isListArea(area)) return { font: font('Inter', 13, level === 'domain' ? 600 : 400), lineHeight: 18, radius: 0 };
    if (level === 'domain') return { font: font('Space Grotesk', 14, 600), lineHeight: 20, radius: 8 };
    return { font: font('Inter', 13, level === 'cluster' ? 600 : 400), lineHeight: 18, radius: 6 };
};

export function resolveStyle(area: AreaKey, level: StyleLevel, override: ElementStyle = {}): BlockStyle {
    const levels = defaultColours.areas[area];
    const base = level === 'area' ? (area === 'direction' ? levels.domain : defaultColours.white) : levels[level];
    const fill = override.fill ?? base;
    const fillOpacity = override.fill_opacity ?? 100;
    const defaultBorder = level === 'area' ? (area === 'direction' ? null : HAIRLINE) : level === 'activity' && fill !== null && !isListArea(area) ? HAIRLINE : null;
    const borderColour = override.border_colour ?? defaultBorder ?? HAIRLINE;
    const borderWidth = override.border_width ?? (defaultBorder === null ? 0 : 1);
    const border = borderWidth === 0 ? null : (override.border_colour ?? defaultBorder ?? HAIRLINE);
    const seen = fill === null ? null : fillOpacity < 100 ? effectiveFill(fill, fillOpacity, defaultColours.white) : fill;
    const textColour = override.text_colour ?? (seen === null ? defaultColours.ink : autoTextColour(seen));
    const typed = typography(area, level);
    const family = override.font === undefined ? typed.font.family : override.font === 'inter' ? 'Inter' : 'Space Grotesk';
    const size = override.font_size ?? typed.font.size;
    const weight = override.font_weight === undefined ? typed.font.weight : override.font_weight === 'bold' ? 700 : 400;

    return {
        fill,
        fillOpacity,
        border,
        borderColour,
        borderWidth,
        borderStyle: override.border_style ?? 'solid',
        alignHorizontal: override.align_horizontal ?? 'left',
        alignVertical: override.align_vertical ?? 'top',
        textColour,
        labelColour: area === 'direction' && level === 'area' ? defaultColours.directionLabels : textColour,
        font: { family, size, weight, italic: (override.italic ?? false) && supportsItalic(family) },
        lineHeight: override.font_size === undefined ? typed.lineHeight : Math.round(size * 1.4),
        radius: override.corner_radius ?? typed.radius,
    };
}
