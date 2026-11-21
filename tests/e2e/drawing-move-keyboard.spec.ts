import { expect, test } from './support/guards';
import { openDrawing, seedMoveCanvas } from './support/drawing';

test('Verplaatsen naar in the panel moves a block by keyboard through the same dialog', async ({ page }) => {
    const layers = await openDrawing(page, seedMoveCanvas());
    await layers.getByRole('button', { name: 'Les geven', exact: true }).first().click();
    const panel = page.locator('[data-move-panel]');
    await expect(panel).toBeVisible();
    await panel.getByRole('combobox', { name: 'Verplaatsen naar' }).focus();
    await page.keyboard.press('Enter');
    await page.getByRole('option', { name: 'Primaire activiteiten › Verkoop' }).click();
    await panel.getByRole('button', { name: 'Verplaatsen' }).click();
    const dialog = page.getByRole('dialog', { name: 'Verplaatsen naar Primaire activiteiten › Verkoop?' });
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(panel.getByRole('button', { name: 'Verplaatsen' })).toBeFocused();
    await panel.getByRole('button', { name: 'Verplaatsen' }).click();
    await page.keyboard.press('Enter');
    await expect(page.locator('[data-announcer]')).toContainText('Les geven hangt nu onder Verkoop');
});

test('the targets offered leave out the current parent, the Unknown allocation and name clashes', async ({ page }) => {
    const layers = await openDrawing(page, seedMoveCanvas());
    await layers.getByRole('button', { name: 'Les geven', exact: true }).first().click();
    const panel = page.locator('[data-move-panel]');
    await panel.getByRole('combobox', { name: 'Verplaatsen naar' }).focus();
    await page.keyboard.press('Enter');
    const options = await page.getByRole('option').allTextContents();
    expect(options).not.toContain('Primaire activiteiten › Onderwijs › Lessen');
    expect(options.some((option) => option.includes('Onbekende allocatie'))).toBe(false);
    expect(options).not.toContain('Primaire activiteiten › Smal');
    expect(options).toContain('Primaire activiteiten › Verkoop');
});
