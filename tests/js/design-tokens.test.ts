import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const resources = fileURLToPath(new URL('../../resources', import.meta.url));

function files(directory: string): string[] {
    return readdirSync(directory).flatMap((name) => {
        const path = join(directory, name);
        return statSync(path).isDirectory() ? files(path) : [path];
    });
}

function theme(): Record<string, string> {
    const source = readFileSync(join(resources, 'css/app.css'), 'utf8');
    const block = /@theme\s*\{([\s\S]*?)\n\}/.exec(source);
    if (block === null) throw new Error('no @theme block in app.css');
    const tokens: Record<string, string> = {};
    for (const [, name, value] of block[1].matchAll(/--([\w-]+):\s*([^;]+);/g)) tokens[name] = value.trim();
    return tokens;
}

function channel(value: number): number {
    return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
}

function luminance(hex: string): number {
    const [red, green, blue] = [1, 3, 5].map((start) => channel(parseInt(hex.slice(start, start + 2), 16) / 255));
    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

function contrast(one: string, other: string): number {
    const [high, low] = [luminance(one), luminance(other)].sort((a, b) => b - a);
    return Math.round(((high + 0.05) / (low + 0.05)) * 10) / 10;
}

const tokens = theme();
const white = '#ffffff';
const value = (name: string): string => {
    const found = tokens[name];
    if (found === undefined) throw new Error(`missing token --${name}`);
    return found;
};

const palette: [string, string][] = [
    ['color-ink', '#171b21'],
    ['color-secondary', '#454c57'],
    ['color-muted', '#5f6772'],
    ['color-page', '#f2f3f1'],
    ['color-surface', '#f6f7f5'],
    ['color-hairline', '#dcdfe2'],
    ['color-edge', '#848b95'],
    ['color-dashed', '#8a919b'],
    ['color-success', '#1f7a4d'],
    ['color-success-soft', '#e7f3ec'],
    ['color-warning', '#8a5200'],
    ['color-warning-soft', '#fff3d6'],
    ['color-danger', '#a12a1e'],
    ['color-danger-edge', '#c0645a'],
    ['color-accent', '#2459d6'],
    ['color-accent-soft', '#e3ebfb'],
    ['color-disabled', '#e6e8e6'],
    ['color-common', '#1f7a4d'],
    ['color-adaptive', '#1e5fbf'],
    ['color-specialized', '#b4540a'],
    ['color-distinct', '#6b3fb5'],
];

const ratios: [string, string, string, number, number][] = [
    ['ink on white', 'color-ink', white, 4.5, 17.3],
    ['ink on the page', 'color-ink', 'color-page', 4.5, 15.5],
    ['secondary text on white', 'color-secondary', white, 4.5, 8.7],
    ['muted text on white', 'color-muted', white, 4.5, 5.7],
    ['muted text on the page', 'color-muted', 'color-page', 4.5, 5.1],
    ['white on Common', white, 'color-common', 4.5, 5.3],
    ['white on Adaptive', white, 'color-adaptive', 4.5, 6.1],
    ['white on Specialized', white, 'color-specialized', 4.5, 5],
    ['white on Distinct', white, 'color-distinct', 4.5, 7],
    ['warning text on its callout', 'color-warning', 'color-warning-soft', 4.5, 5.8],
    ['success text on white', 'color-success', white, 4.5, 5.3],
    ['danger text on white', 'color-danger', white, 4.5, 7.3],
    ['the focus accent on white', 'color-accent', white, 3, 6],
    ['a control border on white', 'color-edge', white, 3, 3.4],
    ['a control border on the page', 'color-edge', 'color-page', 3, 3.1],
    ['a dashed border on white', 'color-dashed', white, 3, 3.2],
    ['a danger outline on white', 'color-danger-edge', white, 3, 4],
];

describe('design tokens', () => {
    it.each(palette)('holds the contract value for --%s', (name, expected) => {
        expect(value(name)).toBe(expected);
    });

    it('names Inter as the body face and Space Grotesk as the display face', () => {
        expect(value('font-sans')).toMatch(/^'Inter'/);
        expect(value('font-display')).toMatch(/^'Space Grotesk'/);
    });

    it.each(ratios)('keeps %s readable', (_label, foreground, background, minimum, documented) => {
        const measured = contrast(foreground.startsWith('#') ? foreground : value(foreground), background.startsWith('#') ? background : value(background));
        expect(measured).toBeGreaterThanOrEqual(minimum);
        expect(measured).toBe(documented);
    });

    it('carries no navy left over from before the contract', () => {
        const left = files(resources).filter((path) => /\.(css|vue|ts)$/.test(path) && /#1f3a5f/i.test(readFileSync(path, 'utf8')));
        expect(left).toEqual([]);
    });
});
