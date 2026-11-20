import { autoTextColour, defaultColours } from '../colour';
import { isListArea } from './arrangeAreas';
import type { AreaKey, BlockStyle, ElementStyle, FontSpec } from './types';

export type StyleLevel = 'area' | 'domain' | 'cluster' | 'activity';

const HAIRLINE = '#DCDFE2';
const SECONDARY = '#454C57';
const AREA_TRACKING = 0.04;

const font = (family: FontSpec['family'], size: number, weight: number): FontSpec => ({ family, size, weight, italic: false });

const typography = (area: AreaKey, level: StyleLevel): Pick<BlockStyle, 'font' | 'lineHeight' | 'radius'> => {
    if (level === 'area') return { font: { ...font('Space Grotesk', 13, 600), ...(area === 'direction' ? {} : { tracking: AREA_TRACKING }) }, lineHeight: 18, radius: 10 };
    if (isListArea(area)) return { font: font('Inter', 13, level === 'domain' ? 600 : 400), lineHeight: 18, radius: 0 };
    if (level === 'domain') return { font: font('Space Grotesk', 14, 600), lineHeight: 20, radius: 8 };
    return { font: font('Inter', 13, level === 'cluster' ? 600 : 400), lineHeight: 18, radius: 6 };
};

export function resolveStyle(area: AreaKey, level: StyleLevel, override: ElementStyle = {}): BlockStyle {
    const levels = defaultColours.areas[area];
    const base = level === 'area' ? (area === 'direction' ? levels.domain : defaultColours.white) : levels[level];
    const fill = override.fill ?? base;
    const border = override.border ?? (level === 'area' ? (area === 'direction' ? null : HAIRLINE) : level === 'activity' && fill !== null && !isListArea(area) ? HAIRLINE : null);
    const heading = level === 'area' && area !== 'direction';
    const textColour = override.textColour ?? (heading ? SECONDARY : fill === null ? defaultColours.ink : autoTextColour(fill));

    return {
        fill,
        border,
        textColour,
        labelColour: area === 'direction' && level === 'area' ? defaultColours.directionLabels : textColour,
        ...typography(area, level),
    };
}
