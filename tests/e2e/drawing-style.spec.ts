import { expect, test } from './support/guards';
import { openDrawing } from './support/drawing';
import { seedViewCanvas } from './support/view';

test('the Kleur group shows the Default colours and a changed fill is saved and drawn in Bekijken', async ({ page }) => {
    const canvas = seedViewCanvas();
    const layers = await openDrawing(page, canvas);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const panel = page.getByRole('complementary', { name: 'Eigenschappen' });
    await expect(panel.getByRole('heading', { name: 'Kleur' })).toBeVisible();
    const fill = page.getByLabel('Vulling van Onderwijs', { exact: true });
    await expect(fill).toHaveValue(/^#[0-9A-F]{6}$/);
    await expect(page.getByLabel('Dekking van de vulling van Onderwijs (0 tot 100)')).toHaveValue('100');
    await expect(page.getByLabel('Randbreedte van Onderwijs (0 tot 8)')).toHaveValue('0');
    await expect(page.getByLabel('Hoeken van Onderwijs (0 tot 64)')).toHaveValue('8');

    await fill.fill('#112233');
    await fill.press('Enter');
    await page.getByLabel('Randbreedte van Onderwijs (0 tot 8)').fill('3');
    await page.getByLabel('Randbreedte van Onderwijs (0 tot 8)').press('Enter');
    await page.getByLabel('Randstijl van Onderwijs').selectOption('dashed');
    await page.getByLabel('Hoeken van Onderwijs (0 tot 64)').fill('20');
    await page.getByLabel('Hoeken van Onderwijs (0 tot 64)').press('Enter');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');

    await page.reload();
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await expect(page.getByLabel('Vulling van Onderwijs', { exact: true })).toHaveValue('#112233');
    await expect(page.getByLabel('Randstijl van Onderwijs')).toHaveValue('dashed');

    await page.goto(`/canvases/${canvas.id}/view`);
    const block = page.locator('[data-block]', { hasText: 'Onderwijs' }).first();
    await expect(block).toHaveCSS('background-color', 'rgb(17, 34, 51)');
    await expect(block).toHaveCSS('border-top-style', 'dashed');
    await expect(block).toHaveCSS('border-top-width', '3px');
    await expect(block).toHaveCSS('border-top-left-radius', '20px');
});

test('the fields of the Kleur group are operated by keyboard and labelled with the name', async ({ page }) => {
    const layers = await openDrawing(page);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    for (const label of ['Kleurkiezer voor vulling van Onderwijs', 'Dekking van de vulling van Onderwijs (0 tot 100)', 'Kleurkiezer voor rand van Onderwijs', 'Randkleur van Onderwijs', 'Randstijl van Onderwijs']) {
        await expect(page.getByLabel(label)).toBeVisible();
    }
    const opacity = page.getByLabel('Dekking van de vulling van Onderwijs (0 tot 100)');
    await opacity.fill('150');
    await opacity.press('Enter');
    await expect(page.getByText('Vulling Onderwijs: kies een dekking van 0 tot 100')).toBeVisible();
});
