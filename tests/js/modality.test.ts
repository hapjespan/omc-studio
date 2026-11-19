import { describe, expect, it } from 'vitest';
import { MODALITIES } from '../../resources/js/components/modality';

const luminance = (hex: string): number => {
    const [r, g, b] = [1, 3, 5].map((index) => {
        const channel = parseInt(hex.slice(index, index + 2), 16) / 255;

        return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

const contrast = (a: string, b: string): number => {
    const [high, low] = [luminance(a), luminance(b)].sort((x, y) => y - x);

    return (high + 0.05) / (low + 0.05);
};

describe('modality marks', () => {
    it.each(MODALITIES.map((modality) => [modality.key, modality.colour]))('%s carries a white letter at 4.5:1', (_key, colour) => {
        expect(contrast('#FFFFFF', colour)).toBeGreaterThanOrEqual(4.5);
    });
});
