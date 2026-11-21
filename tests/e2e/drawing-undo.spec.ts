import { expect, test } from './support/guards';
import { openDrawing } from './support/drawing';

test('undo and redo reverse one finished action by buttons and by Ctrl+Z', async ({ page }) => {
    const layers = await openDrawing(page);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const x = page.getByLabel('X van Onderwijs');
    const start = Number(await x.inputValue());
    await expect(page.getByRole('button', { name: 'Ongedaan maken' })).toBeDisabled();
    await x.fill(String(start + 40));
    await x.press('Enter');
    await expect(page.getByRole('button', { name: 'Ongedaan maken' })).toBeEnabled();
    await page.getByRole('button', { name: 'Ongedaan maken' }).click();
    await expect(x).toHaveValue(String(start));
    await expect(page.getByRole('button', { name: 'Opnieuw' })).toBeEnabled();
    await page.getByRole('button', { name: 'Opnieuw' }).click();
    await expect(x).toHaveValue(String(start + 40));
    await page.locator('[data-state="ready"]').focus();
    await page.keyboard.press('Control+z');
    await expect(x).toHaveValue(String(start));
    await page.keyboard.press('Control+Shift+z');
    await expect(x).toHaveValue(String(start + 40));
});
