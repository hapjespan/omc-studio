import { autoTextColour } from '../../colour';

const PAPER = '#FFFFFF';
const TINT = [0x0e, 0x4d, 0x64];
const FULL_SHARE_BP = 5000;
export const MIN_OPACITY = 0.03;
export const MAX_OPACITY = 0.28;

const channels = (hex: string): number[] => [1, 3, 5].map((index) => parseInt(hex.slice(index, index + 2), 16));
const mix = (base: string, opacity: number): string =>
    `#${channels(base).map((value, index) => Math.round(value + (TINT[index] - value) * opacity).toString(16).padStart(2, '0')).join('').toUpperCase()}`;

export function shareColour(shareBp: number | null, base: string | null): { background: string; text: string } {
    const background = shareBp === null ? (base ?? PAPER) : mix(PAPER, MIN_OPACITY + (MAX_OPACITY - MIN_OPACITY) * Math.min(1, Math.max(0, shareBp) / FULL_SHARE_BP));
    return { background, text: autoTextColour(background) };
}
