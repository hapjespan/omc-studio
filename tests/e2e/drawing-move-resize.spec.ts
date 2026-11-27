import { expect, test, type Page } from './support/guards';
import { openDrawing, screenOf, seedMoveCanvas } from './support/drawing';
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
    await expect(width).toHaveValue('62');
    await layers.getByRole('button', { name: 'Les geven', exact: true }).click();
    const leaf = page.getByLabel('B (breedte) van Les geven');
    await leaf.fill('5');
    await leaf.press('Enter');
    await expect(leaf).toHaveValue('40');
});

test.describe('pointer', () => {
    test.use({ viewport: { width: 1440, height: 1100 } });

    test('dragging a block changes its X in the panel', async ({ page }) => {
    const layers = await open(page);
    const surface = page.locator('[data-drawing-surface]');
    const box = (await surface.boundingBox())!;
    const x = page.getByLabel(/X van /);
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
    await expect.poll(async () => Number(await page.getByLabel(/X van /).inputValue())).not.toBe(before);
});
});

test('a narrow domain contains its cluster and wraps its activity text after saving', async ({ page }) => {
    const layers = await open(page);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const width = page.getByLabel('B (breedte) van Onderwijs');
    await width.fill('90');
    await width.press('Enter');
    await expect(width).toHaveValue('90');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    await page.getByRole('group', { name: 'Modus' }).getByRole('link', { name: 'Bekijken' }).click();
    const block = (name: string) => page.locator('[data-block]').filter({ hasText: name });
    const domain = await block('Onderwijs').boundingBox();
    const cluster = await block('Lessen').boundingBox();
    const activity = page.locator('[data-kind="activity"][data-area="primary_activities"]');
    const child = await activity.boundingBox();
    for (const [outer, inner] of [[domain!, cluster!], [cluster!, child!]]) {
        expect(inner.x).toBeGreaterThanOrEqual(outer.x - 1);
        expect(inner.x + inner.width).toBeLessThanOrEqual(outer.x + outer.width + 1);
        expect(inner.y + inner.height).toBeLessThanOrEqual(outer.y + outer.height + 1);
    }
    expect(await activity.locator('[data-line]').count()).toBeGreaterThan(1);
});

test('the header colour is saved per canvas and its text stays readable', async ({ page }) => {
    await open(page);
    const colour = page.getByLabel('Kleur kopband');
    await colour.fill('#eedd99');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    await page.reload();
    await expect(colour).toHaveValue('#eedd99');
    await page.getByRole('group', { name: 'Modus' }).getByRole('link', { name: 'Bekijken' }).click();
    const header = page.locator('[data-block="direction"]');
    await expect(header).toHaveCSS('background-color', 'rgb(238, 221, 153)');
    await expect(header).toHaveCSS('color', 'rgb(23, 27, 33)');
});

test('dragging the right handle narrows a domain and Undo restores its cluster', async ({ page }) => {
    await page.setViewportSize({ width: 1600, height: 1000 });
    const layers = await openDrawing(page, seedMoveCanvas());
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const handle = await screenOf(page, { x: 1100, y: 540 });
    const to = await screenOf(page, { x: 944, y: 540 });
    await page.mouse.move(handle.x, handle.y);
    await page.mouse.down();
    await page.mouse.move(to.x, to.y, { steps: 8 });
    await page.mouse.up();
    const width = page.getByLabel('B (breedte) van Onderwijs');
    await expect.poll(async () => Number(await width.inputValue())).toBeLessThan(200);
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    await page.getByRole('button', { name: 'Ongedaan maken', exact: true }).click();
    await expect(width).toHaveValue('300');
    await layers.getByRole('button', { name: 'Lessen', exact: true }).click();
    await expect(page.getByLabel('B (breedte) van Lessen')).toHaveValue('260');
});

test('reducing the height uses the space above a cluster before letting its activity overflow', async ({ page }) => {
    const layers = await openDrawing(page, seedMoveCanvas());
    const select = (name: string) => layers.getByRole('button', { name, exact: true }).click();
    await select('Onderwijs');
    const height = page.getByLabel('H (hoogte) van Onderwijs');
    await height.fill('100');
    await height.press('Enter');
    await expect.poll(async () => Number(await height.inputValue())).toBeLessThan(200);
    await select('Lessen');
    await expect.poll(async () => Number(await page.getByLabel('Y van Lessen').inputValue())).toBeLessThan(40);
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    await page.reload();
    await page.locator('[data-state="ready"]').waitFor();
    await select('Lessen');
    await expect.poll(async () => Number(await page.getByLabel('Y van Lessen').inputValue())).toBeLessThan(40);
});
