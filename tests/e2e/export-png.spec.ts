import { readFileSync } from 'node:fs';
import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
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
