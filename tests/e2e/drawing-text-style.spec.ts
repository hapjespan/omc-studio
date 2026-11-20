import { expect, test } from './support/guards';
import { openDrawing } from './support/drawing';
import { seedViewCanvas } from './support/view';

test('the text properties offer only the two shipped fonts and are saved and drawn in Bekijken', async ({ page }) => {
    const canvas = seedViewCanvas();
    const layers = await openDrawing(page, canvas);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const font = page.getByLabel('Lettertype van Onderwijs');
    await expect(font.locator('option')).toHaveText(['Inter', 'Space Grotesk']);

    await font.selectOption('inter');
    const size = page.getByLabel('Lettergrootte van Onderwijs (10 tot 48)');
    await size.fill('20');
    await size.press('Enter');
    await page.getByRole('group', { name: 'Gewicht van de tekst van Onderwijs' }).getByRole('button', { name: 'normaal' }).click();
    await page.getByLabel('Cursief voor Onderwijs').check();
    await page.getByRole('group', { name: 'Horizontale uitlijning van Onderwijs' }).getByRole('button', { name: 'midden' }).click();
    await page.getByRole('group', { name: 'Verticale uitlijning van Onderwijs' }).getByRole('button', { name: 'onder' }).click();
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');

    await page.reload();
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await expect(page.getByLabel('Lettergrootte van Onderwijs (10 tot 48)')).toHaveValue('20');
    await expect(page.getByLabel('Cursief voor Onderwijs')).toBeChecked();

    await page.goto(`/canvases/${canvas.id}/view`);
    const block = page.locator('[data-block]', { hasText: 'Onderwijs' }).first();
    await expect(block).toHaveCSS('font-size', '20px');
    await expect(block).toHaveCSS('font-style', 'italic');
    await expect(block).toHaveCSS('font-weight', '400');
    await expect(block).toHaveCSS('text-align', 'center');
    await expect(block).toHaveCSS('font-family', /Inter/);
});

test('italic is not offered for Space Grotesk, a size outside 10 to 48 is refused and an Area has no text properties', async ({ page }) => {
    const layers = await openDrawing(page);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await page.getByLabel('Lettertype van Onderwijs').selectOption('space_grotesk');
    await expect(page.getByLabel('Cursief voor Onderwijs')).toBeDisabled();
    const size = page.getByLabel('Lettergrootte van Onderwijs (10 tot 48)');
    await size.fill('9');
    await size.press('Enter');
    await expect(page.getByText('Tekst Onderwijs: kies een grootte van 10 tot 48 pixels')).toBeVisible();
    await layers.getByRole('button', { name: 'Primaire activiteiten', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Kleur' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Tekst', exact: true })).toHaveCount(0);
});
