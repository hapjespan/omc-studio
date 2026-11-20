import { expect, test } from './support/guards';
import { openDrawing } from './support/drawing';

test('a value that is not #RRGGBB stays in the field, is marked invalid and nothing is saved', async ({ page }) => {
    const saves: string[] = [];
    page.on('request', (request) => request.method() === 'PUT' && saves.push(request.url()));
    const layers = await openDrawing(page);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const fill = page.getByLabel('Vulling van Onderwijs', { exact: true });
    for (const typed of ['#12345', 'rood']) {
        await fill.fill(typed);
        await fill.press('Enter');
        await expect(fill).toHaveValue(typed);
        await expect(fill).toHaveAttribute('aria-invalid', 'true');
        await expect(page.getByText('Vulling Onderwijs: gebruik de vorm #RRGGBB')).toBeVisible();
    }
    await page.waitForTimeout(800);
    expect(saves).toEqual([]);
});

test('a text colour below 4.5:1 shows the ratio as text, keeps the value invalid with focus and the server refusal is displayed', async ({ page }) => {
    const layers = await openDrawing(page);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await page.getByLabel('Vulling van Onderwijs', { exact: true }).fill('#FFFFFF');
    await page.getByLabel('Vulling van Onderwijs', { exact: true }).press('Enter');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    const text = page.getByLabel('Tekstkleur van Onderwijs', { exact: true });
    await expect(page.locator('[data-contrast]')).toContainText(/\d+,\d:1/);
    await text.fill('#FFFFFE');
    await text.press('Enter');
    await expect(text).toHaveValue('#FFFFFE');
    await expect(text).toHaveAttribute('aria-invalid', 'true');
    await expect(text).toBeFocused();
    await expect(page.locator('[data-contrast]')).toContainText('1,0:1');
    await expect(page.getByText('Tekst Onderwijs: kies een tekstkleur met meer contrast, nu 1,0:1, minstens 4,5:1').first()).toBeVisible();
    await expect(page.locator('[data-announcer]')).toContainText('Tekst Onderwijs: kies een tekstkleur met meer contrast, nu 1,0:1, minstens 4,5:1');
});

test('a dark fill returns dark own text to automatic and tells the user', async ({ page }) => {
    const layers = await openDrawing(page);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const fill = page.getByLabel('Vulling van Onderwijs', { exact: true });
    await fill.fill('#FFFFFF');
    await fill.press('Enter');
    const text = page.getByLabel('Tekstkleur van Onderwijs', { exact: true });
    await text.fill('#222222');
    await text.press('Enter');
    await expect(text).toHaveValue('#222222');
    await fill.fill('#000000');
    await fill.press('Enter');
    await expect(text).toHaveValue('#FFFFFF');
    await expect(page.locator('[data-announcer]')).toContainText('De tekstkleur van Onderwijs is weer automatisch');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
});
