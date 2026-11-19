import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
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

test('Met overlay puts the chosen overlay in the PNG and is off by default', async ({ page }) => {
    const seed = seedOverlay(`Png ${randomUUID().slice(0, 8)}`);
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);
    const toggle = page.getByRole('switch', { name: 'Met overlay' });
    await expect(toggle).not.toBeChecked();

    const download = async (): Promise<Buffer> => {
        await page.getByRole('button', { name: 'Exporteren' }).click();
        const pending = page.waitForEvent('download');
        await page.getByRole('menuitem', { name: 'Exporteren als PNG' }).click();
        return readFileSync((await (await pending).path()) as string);
    };
    const plain = await download();
    await expect(page.locator('[data-overlay-card]').first()).toBeVisible();
    await toggle.check();
    const overlaid = await download();
    expect(overlaid.equals(plain)).toBe(false);
    expect(overlaid.readUInt32BE(16)).toBe(3360);
    await expect(page.getByTestId('export-note')).toHaveText('PNG is gemaakt');
});
