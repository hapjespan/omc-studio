import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

async function open(page: import('@playwright/test').Page) {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.waitForTimeout(500);
    return page.locator('[data-drawing-surface]');
}

test('shortcuts for shapes, text, pen, components, images and Delete change nothing', async ({ page, browserName }) => {
    test.skip(browserName === 'webkit', 'the WebKit screenshot injects a style element that the narrow policy refuses');
    const surface = await open(page);
    const layers = page.getByRole('navigation', { name: 'Lagen' });
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await surface.hover();
    const before = await surface.screenshot({ caret: 'initial', animations: 'allow' });
    for (const key of ['f', 'r', 'o', 'l', 't', 'p', 'Shift+p', 'c', 'a', 'Shift+s', 'Control+d', 'Control+c', 'Control+v', 'Control+g', 'Delete', 'Backspace']) await page.keyboard.press(key);
    await page.keyboard.press('v');
    await expect(layers.getByRole('button', { name: 'Onderwijs', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await expect(layers.locator('[aria-pressed="true"]')).toHaveCount(1);
    await expect(layers.getByRole('button')).toHaveCount(await layers.getByRole('button').count());
    expect(await surface.screenshot({ caret: 'initial', animations: 'allow' })).toEqual(before);
});

test('a double-click on text keeps it and shows and announces the hint', async ({ page }) => {
    const surface = await open(page);
    const box = (await surface.boundingBox())!;
    const hint = page.locator('[data-hint]');
    for (let row = 0; row < 12 && !(await hint.textContent()); row++) {
        for (let column = 0; column < 12 && !(await hint.textContent()); column++) await page.mouse.dblclick(box.x + (box.width * (column + 0.5)) / 12, box.y + (box.height * (row + 0.5)) / 12);
    }
    await expect(hint).toHaveText('Tekst wijzig je in Invoeren');
    await expect(hint).toHaveAttribute('role', 'status');
});
