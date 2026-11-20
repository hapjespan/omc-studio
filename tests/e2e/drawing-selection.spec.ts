import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

async function open(page: import('@playwright/test').Page) {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.waitForTimeout(500);
    return page.getByRole('navigation', { name: 'Lagen' });
}

const pressed = (layers: import('@playwright/test').Locator) => layers.locator('[aria-pressed="true"]');

test('Space selects a row and Ctrl+Space adds and removes rows', async ({ page }) => {
    const layers = await open(page);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).focus();
    await page.keyboard.press('Space');
    await expect(pressed(layers)).toHaveCount(1);
    await layers.getByRole('button', { name: 'Beheer', exact: true }).focus();
    await page.keyboard.press('Control+Space');
    await expect(pressed(layers)).toHaveCount(2);
    await page.keyboard.press('Control+Space');
    await expect(pressed(layers)).toHaveCount(1);
    await expect(layers.getByRole('button', { name: 'Onderwijs', exact: true })).toHaveAttribute('aria-pressed', 'true');
});

test('a click in the drawing is reflected in Lagen', async ({ page }) => {
    const layers = await open(page);
    const box = (await page.locator('[data-drawing-surface]').boundingBox())!;
    for (let row = 0; row < 14 && (await pressed(layers).count()) === 0; row++) {
        for (let column = 0; column < 14 && (await pressed(layers).count()) === 0; column++) await page.mouse.click(box.x + (box.width * (column + 0.5)) / 14, box.y + (box.height * (row + 0.5)) / 14);
    }
    await expect(pressed(layers)).toHaveCount(1);
});

test('the Direction band is not a row in Lagen and a click on it selects nothing', async ({ page }) => {
    const layers = await open(page);
    await expect(layers.getByRole('button', { name: 'Koers' })).toHaveCount(0);
    const box = (await page.locator('[data-drawing-surface]').boundingBox())!;
    await page.mouse.click(box.x + box.width / 2, box.y + 12);
    await expect(pressed(layers)).toHaveCount(0);
});
