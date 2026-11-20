import { expect, test } from './support/guards';
import { openDrawing } from './support/drawing';

test('arrow keys move 1 pixel, Shift 8 and Alt plus arrow resizes', async ({ page }) => {
    const layers = await openDrawing(page);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const x = page.getByLabel('X van Onderwijs');
    const width = page.getByLabel('B (breedte) van Onderwijs');
    const start = Number(await x.inputValue());
    const wide = Number(await width.inputValue());
    await page.locator('[data-state="ready"]').focus();
    await page.keyboard.press('ArrowRight');
    await expect(x).toHaveValue(String(start + 1));
    await page.keyboard.press('Shift+ArrowRight');
    await expect(x).toHaveValue(String(start + 9));
    await page.keyboard.press('Alt+ArrowRight');
    await expect(width).toHaveValue(String(wide + 1));
    await page.keyboard.press('Control+z');
    await expect(width).toHaveValue(String(wide));
});

test('Alt plus an arrow is kept from the browser history while the Artboard or Lagen has focus', async ({ page }) => {
    const layers = await openDrawing(page);
    await page.evaluate(() => {
        (window as unknown as { prevented: boolean[] }).prevented = [];
        document.addEventListener('keydown', (event) => event.key === 'ArrowLeft' && (window as unknown as { prevented: boolean[] }).prevented.push(event.defaultPrevented));
    });
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await page.locator('[data-state="ready"]').focus();
    await page.keyboard.press('Alt+ArrowLeft');
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).focus();
    await page.keyboard.press('Alt+ArrowLeft');
    expect(await page.evaluate(() => (window as unknown as { prevented: boolean[] }).prevented)).toEqual([true, true]);
});
