import { CANVAS_FONT_WEIGHTS, CANVAS_FONTS, supportsItalic } from './fonts';
import type { FontSpec, TextMeasurer } from './types';

const fontShorthand = (font: FontSpec): string => `${font.italic ? 'italic ' : ''}${font.weight} ${font.size}px "${font.family}"`;

export const loadCanvasFonts = async (fonts: Pick<Document, 'fonts'> = document): Promise<void> => {
    const shorthands = CANVAS_FONTS.flatMap((family) =>
        CANVAS_FONT_WEIGHTS.flatMap((weight) =>
            [false, ...(supportsItalic(family) ? [true] : [])].map((italic) => fontShorthand({ family, size: 16, weight, italic })),
        ),
    );
    await Promise.all(
        shorthands.map(async (shorthand) => {
            if ((await fonts.fonts.load(shorthand, 'Aa')).length === 0) throw new Error(`Font face missing: ${shorthand}`);
        }),
    );
    await fonts.fonts.ready;
};

export const createBrowserMeasurer = async (): Promise<TextMeasurer> => {
    await loadCanvasFonts();
    const context = document.createElement('canvas').getContext('2d');
    if (!context) throw new Error('Canvas 2D is not available');
    context.textRendering = 'geometricPrecision';
    return {
        width(text: string, font: FontSpec): number {
            context.font = fontShorthand(font);
            return context.measureText(text).width;
        },
    };
};
