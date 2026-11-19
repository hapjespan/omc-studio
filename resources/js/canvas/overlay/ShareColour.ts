import { autoTextColour, contrastRatio } from '../../colour';

const TARGET = [0x1e, 0x5f, 0xbf];
const FULL_SHARE_BP = 5000;
const MIN_CONTRAST = 4.5;

const channels = (hex: string): number[] => [1, 3, 5].map((index) => parseInt(hex.slice(index, index + 2), 16));
const mix = (base: string, amount: number): string =>
    `#${channels(base).map((value, index) => Math.round(value + (TARGET[index] - value) * amount).toString(16).padStart(2, '0')).join('').toUpperCase()}`;

export function shareColour(shareBp: number | null, base: string | null): { background: string; text: string } {
    const origin = base ?? '#FFFFFF';
    let amount = shareBp === null ? 0 : Math.min(1, shareBp / FULL_SHARE_BP) * 0.85;
    let background = mix(origin, amount);
    while (amount < 1 && contrastRatio(background, autoTextColour(background)) < MIN_CONTRAST) {
        amount = Math.min(1, amount + 0.02);
        background = mix(origin, amount);
    }
    return { background, text: autoTextColour(background) };
}
