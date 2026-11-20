import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedViewCanvas } from '../support/view';

test('a Reader opens a Canvas, zooms, selects an Element and reads its fields', async ({ page, guards }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto('/overview');
    await page.getByRole('link', { name: canvas.name, exact: true }).click();
    await page.waitForURL(/\/canvases\/\d+\/view/);

    await expect(page.getByRole('heading', { level: 1, name: canvas.name })).toBeVisible();
    await expect(page.locator('[data-artboard]')).toBeVisible();
    await expectNoAxeViolations(page);

    await expect(page.locator('[data-zoom]')).not.toHaveText('100%');
    const opening = Number((await page.locator('[data-zoom]').textContent())?.replace('%', ''));
    await page.getByRole('button', { name: 'Inzoomen' }).click();
    await expect(page.locator('[data-zoom]')).toHaveText(`${Math.floor(opening / 10) * 10 + 10}%`);
    await page.getByRole('button', { name: 'Passend maken' }).click();
    await expectNoAxeViolations(page);

    await page.getByRole('button', { name: /^Les geven( |$)/ }).click();
    const panel = page.locator('#detail-panel');
    await expect(panel.getByRole('heading', { name: 'Les geven' })).toBeVisible();
    await expect(panel.locator('[data-modality]')).toContainText('Common');
    await expect(panel).toContainText('Omschrijving');
    await expect(panel.locator('input, textarea, select')).toHaveCount(0);
    await expectNoAxeViolations(page);

    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(page.getByRole('button', { name: /^Les geven( |$)/ })).toBeFocused();

    expect(await page.locator('input[type="color"]').count()).toBe(0);
    guards.assertNoOutsideRequest();
    await guards.assertNoCspViolation();
});
