export const CANVAS_FONTS = ['Inter', 'Space Grotesk'] as const;

export type CanvasFontFamily = (typeof CANVAS_FONTS)[number];

export const CANVAS_FONT_WEIGHTS = [400, 500, 600, 700] as const;

export const supportsItalic = (family: CanvasFontFamily): boolean => family === 'Inter';

export const fontFiles = (family: CanvasFontFamily): string[] => {
    const slug = family.toLowerCase().replace(' ', '-');
    const styles = supportsItalic(family) ? ['normal', 'italic'] : ['normal'];
    return CANVAS_FONT_WEIGHTS.flatMap((weight) => styles.map((style) => `${slug}-latin-${weight}-${style}.woff2`));
};
