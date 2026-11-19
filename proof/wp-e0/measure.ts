import type { FontSpec, TextMeasurer } from './linebreak';

export function canvasMeasurer(): TextMeasurer {
    const context = document.createElement('canvas').getContext('2d')!;
    context.textRendering = 'geometricPrecision';
    return {
        width(text: string, font: FontSpec) {
            context.font = `${font.weight} ${font.size}px "${font.family}"`;
            return context.measureText(text).width;
        },
    };
}
