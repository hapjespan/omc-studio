import { readFileSync } from 'node:fs';
import type { Page } from '@playwright/test';
import { expect, test } from './support/guards';
import { openDrawing, seedMoveCanvas } from './support/drawing';
import { ARTBOARD_WIDTH, PNG_SCALE } from './support/artboard';
import { pixelAt } from './support/png';

const exportPng = async (page: Page): Promise<Buffer> => {
    await page.getByRole('button', { name: 'Exporteren' }).click();
    const download = page.waitForEvent('download');
    await page.getByRole('menuitem', { name: 'Exporteren als PNG' }).click();
    const bytes = readFileSync((await (await download).path()) as string);
    await expect(page.getByTestId('export-note')).toHaveText('PNG is gemaakt');

    return bytes;
};

test('a fill changed in Tekenen shows in the PNG export', async ({ page }) => {
    test.setTimeout(120_000);
    const canvas = seedMoveCanvas();
    const layers = await openDrawing(page, canvas);
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.locator('[data-artboard]').waitFor();
    const artboard = (await page.locator('[data-artboard]').boundingBox())!;
    const scale = artboard.width / ARTBOARD_WIDTH;
    const block = (await page.locator('[data-block]', { has: page.getByText('Onderwijs', { exact: true }) }).first().boundingBox())!;
    const x = Math.round(((block.x + block.width - artboard.x) / scale - 10) * PNG_SCALE);
    const y = Math.round(((block.y + block.height - artboard.y) / scale - 10) * PNG_SCALE);

    const before = await pixelAt(page, await exportPng(page), x, y);
    expect(before).toEqual([0xf6, 0xe6, 0xcc]);

    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const fill = page.getByLabel('Vulling van Onderwijs', { exact: true });
    await fill.fill('#FF0000');
    await fill.press('Enter');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.locator('[data-artboard]').waitFor();

    const after = await pixelAt(page, await exportPng(page), x, y);
    for (const [index, channel] of [255, 0, 0].entries()) expect(Math.abs(after[index] - channel)).toBeLessThanOrEqual(2);
});
