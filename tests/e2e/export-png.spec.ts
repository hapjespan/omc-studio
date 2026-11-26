import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import type { Page } from '@playwright/test';
import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedOverlay } from './support/overlay';
import { seedViewCanvas } from './support/view';

test('Exporteren als PNG downloads a picture at twice the Artboard size', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);

    await page.getByRole('button', { name: 'Exporteren' }).click();
    const download = page.waitForEvent('download');
    await page.getByRole('menuitem', { name: 'Exporteren als PNG' }).click();
    const file = await download;
    expect(file.suggestedFilename()).toMatch(/^canvas-\d{4}-\d{2}-\d{2}\.png$/);

    const bytes = readFileSync((await file.path()) as string);
    expect(bytes.readUInt32BE(16)).toBe(3360);
    expect(bytes.readUInt32BE(20)).toBeGreaterThanOrEqual(2376);
    await expect(page.getByTestId('export-note')).toHaveText('PNG is gemaakt');
});

const A3_LANDSCAPE_WIDTH_POINTS = 1190.55;
const PNG_SCALE = 2;
const MINIMUM_POINTS = 6;

test('the PNG is twice the Artboard width and every text is at least 6 pt when the Artboard is printed as an A3 page', async ({ page }) => {
    const seed = seedOverlay(`Png ${randomUUID().slice(0, 8)}`);
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);
    await expect(page.locator('[data-overlay-card]').first()).toBeAttached();

    const artboard = await page.evaluate(() => {
        const node = document.querySelector<HTMLElement>('[data-artboard]') as HTMLElement;
        const sizes = [...node.querySelectorAll('*')]
            .filter((element) => [...element.childNodes].some((child) => child.nodeType === 3 && child.textContent?.trim()))
            .map((element) => parseFloat(getComputedStyle(element).fontSize));

        return { width: node.offsetWidth, count: sizes.length, smallest: Math.min(...sizes) };
    });

    await page.getByRole('button', { name: 'Exporteren' }).click();
    const download = page.waitForEvent('download');
    await page.getByRole('menuitem', { name: 'Exporteren als PNG' }).click();
    const png = readFileSync(((await (await download).path()) as string));

    expect(png.readUInt32BE(16)).toBe(PNG_SCALE * artboard.width);
    expect(artboard.count).toBeGreaterThan(0);
    const smallestPoints = (artboard.smallest * PNG_SCALE * A3_LANDSCAPE_WIDTH_POINTS) / png.readUInt32BE(16);
    console.log(`SC-019 PNG: width ${png.readUInt32BE(16)} px, Artboard ${artboard.width} px, ${artboard.count} text elements, smallest ${artboard.smallest} px = ${smallestPoints.toFixed(2)} pt on A3`);
    expect(smallestPoints).toBeGreaterThanOrEqual(MINIMUM_POINTS);
});

const setup = async (page: Page): Promise<void> => {
    const seed = seedOverlay(`Png ${randomUUID().slice(0, 8)}`);
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);
    await page.evaluate(() => {
        const state = window as unknown as { layers: number[] };
        state.layers = [];
        const create = document.createElement.bind(document);
        document.createElement = ((name: string, options?: ElementCreationOptions) => {
            if (name === 'a') state.layers.push(document.querySelectorAll('[data-overlay-card]').length);
            return create(name, options);
        }) as typeof document.createElement;
    });
};

test('the PNG follows the Met overlay switch, on by default', async ({ page }) => {
    await setup(page);
    const toggle = page.getByRole('switch', { name: 'Met overlay' });
    await expect(toggle).toBeChecked();
    const download = async (): Promise<Buffer> => {
        await page.getByRole('button', { name: 'Exporteren' }).click();
        const pending = page.waitForEvent('download');
        await page.getByRole('menuitem', { name: 'Exporteren als PNG' }).click();
        const bytes = readFileSync((await (await pending).path()) as string);
        await expect(page.getByTestId('export-note')).toHaveText('PNG is gemaakt');
        return bytes;
    };
    const overlaid = await download();
    await toggle.uncheck();
    const plain = await download();
    expect(overlaid.equals(plain)).toBe(false);
    const layers = await page.evaluate(() => (window as unknown as { layers: number[] }).layers);
    expect(layers[0]).toBeGreaterThan(0);
    expect(layers[1]).toBe(0);
    await expect(page.locator('[data-overlay-card]').first()).toBeAttached();
});

test('a double click on Exporteren als PNG with the switch off still gives a plain PNG', async ({ page }) => {
    await setup(page);
    await page.getByRole('switch', { name: 'Met overlay' }).uncheck();
    await page.getByRole('button', { name: 'Exporteren' }).click();
    await page.getByRole('menuitem', { name: 'Exporteren als PNG' }).dblclick();
    await expect(page.getByTestId('export-note')).toHaveText('PNG is gemaakt');
    const layers = await page.evaluate(() => (window as unknown as { layers: number[] }).layers);
    expect(layers).toEqual([0]);
});
