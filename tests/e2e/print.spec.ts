import { randomUUID } from 'node:crypto';
import type { Page } from '@playwright/test';
import { expect, test } from './support/guards';
import { seedOverlay } from './support/overlay';
import { signInAs } from './support/shell';
import { seedBulkCanvas, seedViewCanvas } from './support/view';

const SIX_POINTS_IN_PIXELS = 8;
const A3_LANDSCAPE_POINTS = { width: 1190.55, height: 841.89 };

const printedFontSizes = (page: Page): Promise<{ count: number; smallest: number }> =>
    page.evaluate(() => {
        const artboard = document.querySelector<HTMLElement>('[data-artboard]');
        const scale = parseFloat(getComputedStyle(artboard as HTMLElement).zoom);
        const sizes = [...document.querySelectorAll('[data-artboard] *')]
            .filter((node) => [...node.childNodes].some((child) => child.nodeType === 3 && child.textContent?.trim()))
            .map((node) => parseFloat(getComputedStyle(node).fontSize) * scale);

        return { count: sizes.length, smallest: Math.min(...sizes) };
    });

const withinOnePoint = (actual: number | undefined, expected: number): void => expect(Math.abs((actual ?? 0) - expected)).toBeLessThan(1);

const readPdf = (pdf: Buffer): { pages: number; boxes: number[][] } => {
    const text = pdf.toString('latin1');

    return {
        pages: [...text.matchAll(/\/Type\s*\/Page(?![s\w])/g)].length,
        boxes: [...text.matchAll(/\/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]/g)].map((match) => match.slice(1).map(Number)),
    };
};

test('the menu of a Reader holds the PNG and print entries', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.getByRole('button', { name: 'Exporteren' }).click();
    await expect(page.getByRole('menuitem', { name: 'Afdrukken' })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Exporteren als PNG' })).toBeVisible();
});

test('print media puts the Artboard at the top of the first A3 page and hides the shell', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.evaluate(() => (window.print = () => undefined));
    await page.getByRole('button', { name: 'Exporteren' }).click();
    await page.getByRole('menuitem', { name: 'Afdrukken' }).click();
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('header.shell-top')).toBeHidden();
    await expect(page.locator('#detail-panel')).toBeHidden();
    await expect(page.getByRole('button', { name: 'Exporteren' })).toBeHidden();
    await expect(page.getByRole('heading', { level: 1 })).toBeHidden();
    const box = await page.locator('[data-artboard]').boundingBox();
    expect(box?.y).toBeCloseTo(0, 0);
    expect(box?.height).toBeGreaterThan(1100);
    expect(box?.height).toBeLessThan(1130);
});

test('print media leaves other pages alone', async ({ page }) => {
    await signInAs(page, 'administrator');
    await page.goto('/overview');
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('header.shell-top')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
});

test('the warning offers Toch afdrukken and prints only from that button', async ({ page }) => {
    const canvas = seedBulkCanvas(12, 12);
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.evaluate(() => {
        (window as unknown as { printed: number }).printed = 0;
        window.print = () => ((window as unknown as { printed: number }).printed += 1);
    });
    await page.getByRole('button', { name: 'Exporteren' }).click();
    await page.getByRole('menuitem', { name: 'Afdrukken' }).click();
    await expect(page.getByTestId('export-note')).toContainText(/Niet alles past op het A3-tekenvlak: \d+ blokken komen op een volgende pagina/);
    expect(await page.evaluate(() => (window as unknown as { printed: number }).printed)).toBe(0);
    await page.getByRole('button', { name: 'Toch afdrukken' }).click();
    expect(await page.evaluate(() => (window as unknown as { printed: number }).printed)).toBe(1);
});

test('without overflow the print dialog opens straight away', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.evaluate(() => {
        (window as unknown as { printed: number }).printed = 0;
        window.print = () => ((window as unknown as { printed: number }).printed += 1);
    });
    await page.getByRole('button', { name: 'Exporteren' }).click();
    await page.getByRole('menuitem', { name: 'Afdrukken' }).click();
    expect(await page.evaluate(() => (window as unknown as { printed: number }).printed)).toBe(1);
});

test('the print layout follows the Met overlay switch through beforeprint and afterprint and keeps text at 6 pt or more', async ({ page }) => {
    const seed = seedOverlay(`Print ${randomUUID().slice(0, 8)}`);
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);
    const toggle = page.getByRole('switch', { name: 'Met overlay' });
    await expect(toggle).toBeChecked();
    const printed = async (): Promise<number> =>
        page.evaluate(
            () =>
                new Promise<number>((resolve) => {
                    window.dispatchEvent(new Event('beforeprint'));
                    setTimeout(() => {
                        const layers = document.querySelectorAll('[data-overlay-card]').length;
                        window.dispatchEvent(new Event('afterprint'));
                        resolve(layers);
                    }, 50);
                }),
        );
    expect(await printed()).toBeGreaterThan(0);
    await toggle.uncheck();
    expect(await printed()).toBe(0);
    await expect(page.locator('[data-overlay-card]').first()).toBeAttached();

    await page.emulateMedia({ media: 'print' });
    const { count, smallest } = await printedFontSizes(page);
    expect(count).toBeGreaterThan(0);
    expect(smallest).toBeGreaterThanOrEqual(SIX_POINTS_IN_PIXELS);
});

test('without an overlay the Met overlay switch is absent', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await expect(page.getByRole('switch', { name: 'Met overlay' })).toHaveCount(0);
});

test('in print the scroller is at least as tall as the Artboard', async ({ page }) => {
    const seed = seedOverlay(`Print ${randomUUID().slice(0, 8)}`);
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);
    for (let step = 0; step < 8; step++) await page.getByRole('button', { name: 'Inzoomen' }).click();
    await page.emulateMedia({ media: 'print' });
    const scroller = (await page.locator('[data-scroller]').boundingBox())!;
    const artboard = (await page.locator('[data-artboard]').boundingBox())!;
    expect(scroller.height).toBeGreaterThanOrEqual(artboard.height - 1);
});

test.describe('SC-019 PDF through the print layout (Chromium only: page.pdf exists nowhere else)', () => {
    test.skip(({ browserName }) => browserName !== 'chromium', 'page.pdf is Chromium-only');

    test('the overflowing seed continues on further A3 landscape pages', async ({ page }) => {
        const canvas = seedBulkCanvas(12, 12);
        await signInAs(page, 'reader');
        await page.goto(`/canvases/${canvas.id}/view`);
        await expect(page.locator('[data-artboard] [data-block]').first()).toBeVisible();
        await page.emulateMedia({ media: 'print' });

        const { pages, boxes } = readPdf(await page.pdf({ preferCSSPageSize: true, printBackground: true }));

        console.log(`SC-019 overflowing seed: ${pages} pages, MediaBox ${JSON.stringify(boxes[0])}`);
        expect(pages).toBeGreaterThan(1);
        expect(boxes).toHaveLength(pages);
        for (const [left, bottom, width, height] of boxes) {
            expect([left, bottom]).toEqual([0, 0]);
            withinOnePoint(width, A3_LANDSCAPE_POINTS.width);
            withinOnePoint(height, A3_LANDSCAPE_POINTS.height);
        }
    });

    test('a Canvas that fits makes exactly one A3 landscape page', async ({ page }) => {
        const canvas = seedViewCanvas();
        await signInAs(page, 'reader');
        await page.goto(`/canvases/${canvas.id}/view`);
        await expect(page.locator('[data-artboard] [data-block]').first()).toBeVisible();
        await page.emulateMedia({ media: 'print' });

        const { pages, boxes } = readPdf(await page.pdf({ preferCSSPageSize: true, printBackground: true }));

        expect(pages).toBe(1);
        withinOnePoint(boxes[0]?.[2], A3_LANDSCAPE_POINTS.width);
        withinOnePoint(boxes[0]?.[3], A3_LANDSCAPE_POINTS.height);
    });

    test('the text of the overflowing seed is at least 6 pt in print', async ({ page }) => {
        const canvas = seedBulkCanvas(12, 12);
        await signInAs(page, 'reader');
        await page.goto(`/canvases/${canvas.id}/view`);
        await expect(page.locator('[data-artboard] [data-block]').first()).toBeVisible();
        await page.emulateMedia({ media: 'print' });

        const { count, smallest } = await printedFontSizes(page);

        expect(count).toBeGreaterThan(100);
        expect(smallest).toBeGreaterThanOrEqual(SIX_POINTS_IN_PIXELS);
    });
});
