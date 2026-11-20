import { expect, test } from './support/guards';
import { openDrawing, seedMoveCanvas } from './support/drawing';
import { seedViewCanvas } from './support/view';

test('Terug naar automatische plek asks first and returns an Element to Automatic layout', async ({ page }) => {
    const layers = await openDrawing(page, seedMoveCanvas());
    await layers.getByRole('button', { name: 'Les geven', exact: true }).first().click();
    await expect(page.getByLabel('B (breedte) van Les geven')).toHaveValue('200');
    await page.getByRole('button', { name: 'Terug naar automatische plek' }).click();
    const dialog = page.getByRole('dialog', { name: 'Terug naar automatische plek voor Les geven?' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Terugzetten' }).click();
    await expect(dialog).toBeHidden();
    await expect(async () => {
        await layers.getByRole('button', { name: 'Les geven', exact: true }).first().click();
        await expect(page.getByLabel('B (breedte) van Les geven')).toBeVisible({ timeout: 500 });
        await expect(page.getByLabel('B (breedte) van Les geven')).not.toHaveValue('200', { timeout: 500 });
    }).toPass();
});

test('Domein terug naar automatisch is offered for a Domain only and resets everything in it', async ({ page }) => {
    const layers = await openDrawing(page, seedMoveCanvas());
    await layers.getByRole('button', { name: 'Les geven', exact: true }).first().click();
    await expect(page.getByRole('button', { name: 'Domein terug naar automatisch' })).toHaveCount(0);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await page.getByRole('button', { name: 'Domein terug naar automatisch' }).click();
    const dialog = page.getByRole('dialog', { name: 'Domein Onderwijs terug naar automatisch?' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Terugzetten' }).click();
    await expect(dialog).toBeHidden();
    await expect(async () => {
        await layers.getByRole('button', { name: 'Les geven', exact: true }).first().click();
        await expect(page.getByLabel('B (breedte) van Les geven')).toBeVisible({ timeout: 500 });
        await expect(page.getByLabel('B (breedte) van Les geven')).not.toHaveValue('200', { timeout: 500 });
    }).toPass();
});

test('Alles automatisch opmaken gives the same places as a new Canvas of the same content', async ({ page }) => {
    const layers = await openDrawing(page, seedMoveCanvas());
    await page.getByRole('button', { name: 'Alles automatisch opmaken' }).click();
    const dialog = page.getByRole('dialog', { name: 'Alles automatisch opmaken?' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Terugzetten' }).click();
    await expect(dialog).toBeHidden();
    const read = async (row: import('@playwright/test').Locator, name: string) => {
        await row.getByRole('button', { name, exact: true }).first().click();
        return [await page.getByLabel(`X van ${name}`).inputValue(), await page.getByLabel(`Y van ${name}`).inputValue(), await page.getByLabel(`B (breedte) van ${name}`).inputValue()];
    };
    await expect(async () => {
        await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
        await expect(page.getByLabel('X van Onderwijs')).not.toHaveValue('20', { timeout: 500 });
    }).toPass();
    const reset = [await read(layers, 'Onderwijs'), await read(layers, 'Lessen')];
    const other = seedViewCanvas();
    await page.goto(`/canvases/${other.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.waitForTimeout(500);
    const fresh = [await read(page.getByRole('navigation', { name: 'Lagen' }), 'Onderwijs'), await read(page.getByRole('navigation', { name: 'Lagen' }), 'Lessen')];
    expect(reset).toEqual(fresh);
});
