import { expect, test } from './support/guards';
import { ARTBOARD_WIDTH } from './support/artboard';
import { openDrawing, screenOf, seedMoveCanvas } from './support/drawing';

const AREA = { x: 780, y: 400 };
const CORNER = 6;

test.use({ viewport: { width: 1440, height: 1100 } });

test('SC-025: a Domain dragged with the pointer and coloured keeps its place and fill after a reload, in Tekenen and in Bekijken within 1 pixel', async ({ page }) => {
    const canvas = seedMoveCanvas();
    const layers = await openDrawing(page, canvas);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const read = async (label: string): Promise<number> => Number(await page.getByLabel(`${label} van Onderwijs`).inputValue());
    const startX = await read('X');
    const startY = await read('Y');

    const grab = await screenOf(page, { x: AREA.x + startX + CORNER, y: AREA.y + startY + CORNER });
    await page.mouse.move(grab.x, grab.y);
    await page.mouse.down();
    await page.mouse.move(grab.x + 60, grab.y + 24, { steps: 8 });
    await page.mouse.up();
    await expect.poll(() => read('X')).not.toBe(startX);
    const draggedX = await read('X');
    const draggedY = await read('Y');
    const width = await read('B (breedte)');

    const fill = page.getByLabel('Vulling van Onderwijs', { exact: true });
    await fill.fill('#112233');
    await fill.press('Enter');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');

    await page.reload();
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await expect(page.getByLabel('X van Onderwijs')).toHaveValue(String(draggedX));
    await expect(page.getByLabel('Y van Onderwijs')).toHaveValue(String(draggedY));
    await expect(page.getByLabel('Vulling van Onderwijs', { exact: true })).toHaveValue('#112233');

    await page.goto(`/canvases/${canvas.id}/view`);
    const artboard = (await page.locator('[data-artboard]').boundingBox())!;
    const block = page.locator('[data-block]', { has: page.getByText('Onderwijs', { exact: true }) }).first();
    const box = (await block.boundingBox())!;
    const scale = artboard.width / ARTBOARD_WIDTH;
    expect(Math.abs((box.x - artboard.x) / scale - (AREA.x + draggedX))).toBeLessThanOrEqual(1);
    expect(Math.abs((box.y - artboard.y) / scale - (AREA.y + draggedY))).toBeLessThanOrEqual(1);
    expect(Math.abs(box.width / scale - width)).toBeLessThanOrEqual(1);
    await expect(block).toHaveCSS('background-color', 'rgb(17, 34, 51)');
});
