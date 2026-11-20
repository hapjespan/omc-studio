import { expect, expectNoAxeViolations, test } from './support/guards';
import { ARTBOARD_WIDTH } from './support/artboard';
import { dragTo, ACTIVITY, openDrawing, seedMoveCanvas, DOMAIN_TARGET } from './support/drawing';

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

    await dragTo(page, { x: ACTIVITY.x + 24, y: ACTIVITY.y + 24 }, DOMAIN_TARGET);
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
    const scale = artboard.width / ARTBOARD_WIDTH;
    expect(Math.abs((block.x - artboard.x) / scale - (780 + 44))).toBeLessThanOrEqual(1);
    expect(Math.abs((block.y - artboard.y) / scale - (400 + 64))).toBeLessThanOrEqual(1);
    expect(Math.abs(block.width / scale - 320)).toBeLessThanOrEqual(1);
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

test('axe is clean in the reset dialog, the failed state, the invalid field and the leave dialog', async ({ page, context }) => {
    const layers = await openDrawing(page, seedMoveCanvas());
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await page.getByRole('button', { name: 'Terug naar automatische plek' }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await expectNoAxeViolations(page);
    await page.keyboard.press('Escape');

    const id = await layers.getByRole('button', { name: 'Onderwijs', exact: true }).getAttribute('data-layer');
    await page.route('**/layout', (route) => (route.request().method() === 'PUT' ? route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ message: 'refused', errors: { rule: ['Opmaak Onderwijs: plek en grootte zijn ongeldig'], codes: ['invalid_layout'], elements: [id] } }) }) : route.continue()));
    await page.getByLabel('X van Onderwijs').fill('50');
    await page.getByLabel('X van Onderwijs').press('Enter');
    await expect(page.locator('[data-announcer]')).toContainText('plek en grootte zijn ongeldig');
    await expectNoAxeViolations(page);
    await page.unroute('**/layout');

    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await context.setOffline(true);
    await page.getByLabel('X van Onderwijs').fill('52');
    await page.getByLabel('X van Onderwijs').press('Enter');
    await expect(page.locator('[data-save-state]')).toContainText('Opmaak niet opgeslagen');
    await expectNoAxeViolations(page);
    await page.getByRole('link', { name: 'Bekijken' }).click();
    await expect(page.getByRole('dialog', { name: 'Wijziging niet opgeslagen' })).toBeVisible();
    await expectNoAxeViolations(page);
    await context.setOffline(false);
});
