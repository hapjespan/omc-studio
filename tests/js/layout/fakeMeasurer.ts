import type { FontSpec, TextMeasurer } from '../../../resources/js/layout/types';

export const fakeMeasurer: TextMeasurer = {
    width: (text: string, font: FontSpec) => text.length * font.size * 0.5,
};
