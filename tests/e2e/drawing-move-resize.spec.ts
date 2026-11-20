import { expect, test, type Page } from './support/guards';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

async function open(page: Page) {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.waitForTimeout(500);
    return page.getByRole('navigation', { name: 'Lagen' });
}

test('typed X, Y, B and H move and resize the block like dragging, with labelled fields', async ({ page }) => {
    const layers = await open(page);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const x = page.getByLabel('X van Onderwijs');
    await expect(page.getByLabel('Y van Onderwijs')).toBeVisible();
    await expect(page.getByLabel('B (breedte) van Onderwijs')).toBeVisible();
    await expect(page.getByLabel('H (hoogte) van Onderwijs')).toBeVisible();
    await x.fill('20');
    await x.press('Enter');
    await expect(x).toHaveValue('20');
    const width = page.getByLabel('B (breedte) van Onderwijs');
    await width.fill('300');
    await width.press('Enter');
    await expect(width).toHaveValue('300');
    await expect(page.locator('[data-size-label]')).toContainText('300 ×');
    await width.fill('5');
    await width.press('Enter');
    await expect.poll(async () => Number(await width.inputValue())).toBeGreaterThan(100);
    await layers.getByRole('button', { name: 'Les geven', exact: true }).click();
    const leaf = page.getByLabel('B (breedte) van Les geven');
    await leaf.fill('5');
    await leaf.press('Enter');
    await expect(leaf).toHaveValue('40');
});

test('dragging a block changes its X in the panel', async ({ page }) => {
    const layers = await open(page);
    const surface = page.locator('[data-drawing-surface]');
    const box = (await surface.boundingBox())!;
    const x = page.getByLabel(/^X van /);
    let point: { x: number; y: number } | null = null;
    for (let row = 0; row < 14 && !point; row++) {
        for (let column = 0; column < 14 && !point; column++) {
            const candidate = { x: box.x + (box.width * (column + 0.5)) / 14, y: box.y + (box.height * (row + 0.5)) / 14 };
            await page.mouse.click(candidate.x, candidate.y);
            if ((await layers.locator('[aria-pressed="true"]').count()) === 1 && (await x.count()) === 1) point = candidate;
        }
    }
    expect(point).not.toBeNull();
    const before = Number(await x.inputValue());
    await page.mouse.move(point!.x, point!.y);
    await page.mouse.down();
    await page.mouse.move(point!.x + 30, point!.y, { steps: 6 });
    await page.mouse.up();
    await expect.poll(async () => Number(await page.getByLabel(/^X van /).inputValue())).not.toBe(before);
});
