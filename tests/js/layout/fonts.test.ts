import { existsSync, readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { CANVAS_FONT_WEIGHTS, CANVAS_FONTS, supportsItalic, type CanvasFontFamily } from '../../../resources/js/layout/fonts';

const fontFiles = (family: CanvasFontFamily): string[] => {
    const slug = family.toLowerCase().replace(' ', '-');
    const styles = supportsItalic(family) ? ['normal', 'italic'] : ['normal'];

    return CANVAS_FONT_WEIGHTS.flatMap((weight) => styles.map((style) => `${slug}-latin-${weight}-${style}.woff2`));
};

describe('canvas fonts', () => {
    it('lists exactly Inter and Space Grotesk', () => {
        expect([...CANVAS_FONTS]).toEqual(['Inter', 'Space Grotesk']);
    });

    it('has a vendored file and an @font-face rule for every face', () => {
        const css = readFileSync('resources/css/fonts.css', 'utf8');
        for (const family of CANVAS_FONTS) {
            for (const file of fontFiles(family)) {
                expect(existsSync(`public/fonts/${file}`), file).toBe(true);
                expect(css).toContain(`/fonts/${file}`);
            }
        }
    });

    it('names no outside URL in the font rules', () => {
        expect(readFileSync('resources/css/fonts.css', 'utf8')).not.toMatch(/https?:/);
    });
});
