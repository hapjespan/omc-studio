export const CANVAS_FONTS = ['Inter', 'Space Grotesk'] as const;

export type CanvasFontFamily = (typeof CANVAS_FONTS)[number];

export const CANVAS_FONT_WEIGHTS = [400, 500, 600, 700] as const;

export const supportsItalic = (family: CanvasFontFamily): boolean => family === 'Inter';
