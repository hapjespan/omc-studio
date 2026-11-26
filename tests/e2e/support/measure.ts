import type { Locator } from '@playwright/test';
import { expect } from './guards';

export const style = (locator: Locator, property: string): Promise<string> => locator.evaluate((element, name) => getComputedStyle(element).getPropertyValue(name), property);
export const height = async (locator: Locator): Promise<number> => Math.round((await locator.boundingBox())!.height);

export async function expectTitle(title: Locator): Promise<void> {
    expect(await style(title, 'font-family')).toContain('Space Grotesk');
    expect(await style(title, 'font-size')).toBe('22px');
    expect(await style(title, 'font-weight')).toBe('600');
}

export async function expectPanel(panel: Locator, radius = '10px', filled = true): Promise<void> {
    if (filled) expect(await style(panel, 'background-color')).toBe('rgb(255, 255, 255)');
    expect(await style(panel, 'border-top-color')).toBe('rgb(220, 223, 226)');
    expect(await style(panel, 'border-top-width')).toBe('1px');
    expect(await style(panel, 'border-top-left-radius')).toBe(radius);
}
