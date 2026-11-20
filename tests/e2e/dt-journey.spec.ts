import { expect, expectNoAxeViolations, test } from './support/guards';
import { dragTo, LES_GEVEN, openDrawing, seedMoveCanvas, VERKOOP } from './support/drawing';

test('the Tekenen journey: arrange, save, reload, Bekijken equals Tekenen, axe clean in every state', async ({ page }) => {
    const canvas = seedMoveCanvas();
    const layers = await openDrawing(page, canvas);
    await expectNoAxeViolations(page);

    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await expectNoAxeViolations(page);
    const x = page.getByLabel('X van Onderwijs');
    const y = page.getByLabel('Y van Onderwijs');
    const width = page.getByLabel('B (breedte) van Onderwijs');
    await x.fill('44');
    await x.press('Enter');
    await width.fill('320');
    await width.press('Enter');
    await y.fill('64');
    await y.press('Enter');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    await expectNoAxeViolations(page);

    await dragTo(page, { x: LES_GEVEN.x + 24, y: LES_GEVEN.y + 24 }, VERKOOP);
    await expect(page.getByRole('dialog')).toBeVisible();
    await expectNoAxeViolations(page);
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toBeHidden();

    await page.reload();
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await expect(page.getByLabel('X van Onderwijs')).toHaveValue('44');
    await expect(page.getByLabel('Y van Onderwijs')).toHaveValue('64');
    await expect(page.getByLabel('B (breedte) van Onderwijs')).toHaveValue('320');
    await expectNoAxeViolations(page);

    await page.goto(`/canvases/${canvas.id}/view`);
    const artboard = (await page.locator('[data-artboard]').boundingBox())!;
    const block = (await page.locator('[data-block]', { has: page.getByText('Onderwijs', { exact: true }) }).first().boundingBox())!;
    expect(Math.abs(block.x - artboard.x - (780 + 44))).toBeLessThanOrEqual(1);
    expect(Math.abs(block.y - artboard.y - (400 + 64))).toBeLessThanOrEqual(1);
    expect(Math.abs(block.width - 320)).toBeLessThanOrEqual(1);
    await expectNoAxeViolations(page);
});

test('the keyboard-only journey: select, move, resize, undo and redo without a pointer', async ({ page }) => {
    const layers = await openDrawing(page, seedMoveCanvas());
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).focus();
    await page.keyboard.press('Space');
    const x = page.getByLabel('X van Onderwijs');
    const width = page.getByLabel('B (breedte) van Onderwijs');
    const start = Number(await x.inputValue());
    const wide = Number(await width.inputValue());
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('Shift+ArrowRight');
    await expect(x).toHaveValue(String(start + 9));
    await page.keyboard.press('Alt+ArrowRight');
    await expect(width).toHaveValue(String(wide + 1));
    await page.keyboard.press('Control+z');
    await expect(width).toHaveValue(String(wide));
    await page.keyboard.press('Control+Shift+z');
    await expect(width).toHaveValue(String(wide + 1));
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    await expectNoAxeViolations(page);
});
