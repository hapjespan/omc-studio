import defaults from '../../data/default-colours.json';

const channel = (value: number): number => {
    const s = value / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
};

const luminance = (hex: string): number => {
    const [r, g, b] = [1, 3, 5].map((i) => channel(parseInt(hex.slice(i, i + 2), 16)));
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

export const contrastRatio = (a: string, b: string): number => {
    const [high, low] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (high + 0.05) / (low + 0.05);
};

export const autoTextColour = (background: string): string =>
    contrastRatio(background, defaults.ink) >= contrastRatio(background, defaults.white) ? defaults.ink : defaults.white;

export const defaultColourPairs = (): { background: string; text: string }[] =>
    Object.values(defaults.areas)
        .flatMap((levels) => Object.values(levels))
        .filter((background): background is string => background !== null)
        .map((background) => ({ background, text: autoTextColour(background) }));

export { defaults as defaultColours };
