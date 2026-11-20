import { expect, test } from './support/guards';
import { openDrawing, seedMoveCanvas, seedMoveContent } from './support/drawing';

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

test('Terug naar automatische plek returns an Area to its automatic place', async ({ page }) => {
    const layers = await openDrawing(page, seedMoveCanvas());
    await layers.getByRole('button', { name: 'Primaire activiteiten', exact: true }).click();
    await expect(page.getByLabel('X van Primaire activiteiten')).toHaveValue('780');
    await page.getByRole('button', { name: 'Terug naar automatische plek' }).click();
    const dialog = page.getByRole('dialog', { name: 'Terug naar automatische plek voor Primaire activiteiten?' });
    await dialog.getByRole('button', { name: 'Terugzetten' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.locator('[data-announcer]')).toContainText('Opmaak teruggezet naar automatisch');
    await expect(async () => {
        await layers.getByRole('button', { name: 'Primaire activiteiten', exact: true }).click();
        await expect(page.getByLabel('X van Primaire activiteiten')).toBeVisible({ timeout: 500 });
        await expect(page.getByLabel('X van Primaire activiteiten')).not.toHaveValue('780', { timeout: 500 });
    }).toPass();
});

test('Alles automatisch opmaken gives every Area and Element the place and size of a new Canvas of the same content', async ({ page }) => {
    const layers = await openDrawing(page, seedMoveCanvas());
    const opener = page.getByRole('button', { name: 'Alles automatisch opmaken' });
    await opener.click();
    const dialog = page.getByRole('dialog', { name: 'Alles automatisch opmaken?' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Terugzetten' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.locator('[data-announcer]')).toContainText('Opmaak teruggezet naar automatisch');
    const all = async (navigation: import('@playwright/test').Locator) => {
        const names = (await navigation.getByRole('button').allTextContents()).map((name) => name.trim());
        const boxes: Record<string, string[]> = {};
        for (const [index, name] of names.entries()) {
            await navigation.getByRole('button').nth(index).click();
            boxes[`${index} ${name}`] = [await page.getByLabel(`X van ${name}`).inputValue(), await page.getByLabel(`Y van ${name}`).inputValue(), await page.getByLabel(`B (breedte) van ${name}`).inputValue(), await page.getByLabel(`H (hoogte) van ${name}`).inputValue()];
        }
        return boxes;
    };
    await expect(async () => {
        await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
        await expect(page.getByLabel('X van Onderwijs')).not.toHaveValue('20', { timeout: 500 });
    }).toPass();
    const reset = await all(layers);
    const other = seedMoveContent();
    await page.goto(`/canvases/${other.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.waitForTimeout(500);
    const fresh = await all(page.getByRole('navigation', { name: 'Lagen' }));
    expect(Object.keys(reset).length).toBeGreaterThan(10);
    expect(reset).toEqual(fresh);
});
