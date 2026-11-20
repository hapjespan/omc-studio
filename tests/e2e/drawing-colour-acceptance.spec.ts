import type { Page } from '@playwright/test';
import { expect, test } from './support/guards';
import { ARTBOARD_WIDTH } from './support/artboard';
import { openDrawing, seedMoveCanvas, seedMoveContent } from './support/drawing';

const STYLE_PROPERTIES = ['background-color', 'border-top-width', 'border-top-style', 'border-top-left-radius', 'color', 'font-family', 'font-size'];

async function styleOf(page: Page, canvas: number, name: string): Promise<Record<string, string>> {
    await page.goto(`/canvases/${canvas}/view`);
    const block = page.locator('[data-block]', { has: page.getByText(name, { exact: true }) }).first();
    await block.waitFor();

    return block.evaluate((element, properties) => Object.fromEntries(properties.map((property) => [property, getComputedStyle(element).getPropertyValue(property)])), STYLE_PROPERTIES);
}

test('SC-025: a Domain moved, resized and coloured in Tekenen is the same after reload in Tekenen and Bekijken, structure unchanged', async ({ page }) => {
    const canvas = seedMoveCanvas();
    const layers = await openDrawing(page, canvas);
    const elementsBefore = await page.evaluate(async (id) => (await (await fetch(`/canvases/${id}/export/json`)).json()).canvas.elements.map((element: { name: string }) => element.name), canvas.id);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    for (const [label, value] of [['X van Onderwijs', '44'], ['Y van Onderwijs', '64'], ['B (breedte) van Onderwijs', '320']]) {
        const field = page.getByLabel(label);
        await field.fill(value);
        await field.press('Enter');
    }
    const fill = page.getByLabel('Vulling van Onderwijs', { exact: true });
    await fill.fill('#112233');
    await fill.press('Enter');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');

    await page.reload();
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await expect(page.getByLabel('X van Onderwijs')).toHaveValue('44');
    await expect(page.getByLabel('Y van Onderwijs')).toHaveValue('64');
    await expect(page.getByLabel('B (breedte) van Onderwijs')).toHaveValue('320');
    await expect(page.getByLabel('Vulling van Onderwijs', { exact: true })).toHaveValue('#112233');

    await page.goto(`/canvases/${canvas.id}/view`);
    const artboard = (await page.locator('[data-artboard]').boundingBox())!;
    const block = page.locator('[data-block]', { has: page.getByText('Onderwijs', { exact: true }) }).first();
    const box = (await block.boundingBox())!;
    const scale = artboard.width / ARTBOARD_WIDTH;
    expect(Math.abs((box.x - artboard.x) / scale - (780 + 44))).toBeLessThanOrEqual(1);
    expect(Math.abs((box.y - artboard.y) / scale - (400 + 64))).toBeLessThanOrEqual(1);
    expect(Math.abs(box.width / scale - 320)).toBeLessThanOrEqual(1);
    await expect(block).toHaveCSS('background-color', 'rgb(17, 34, 51)');

    const elementsAfter = await page.evaluate(async (id) => (await (await fetch(`/canvases/${id}/export/json`)).json()).canvas.elements.map((element: { name: string }) => element.name), canvas.id);
    expect(elementsAfter).toEqual(elementsBefore);
});

test('SC-028: Alles automatisch opmaken gives the styles of a new Canvas with the same content', async ({ page }) => {
    const canvas = seedMoveCanvas();
    const layers = await openDrawing(page, canvas);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const fill = page.getByLabel('Vulling van Onderwijs', { exact: true });
    await fill.fill('#112233');
    await fill.press('Enter');
    const radius = page.getByLabel('Hoeken van Onderwijs (0 tot 64)');
    await radius.fill('30');
    await radius.press('Enter');
    await page.getByLabel('Randbreedte van Onderwijs (0 tot 8)').fill('4');
    await page.getByLabel('Randbreedte van Onderwijs (0 tot 8)').press('Enter');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    const styled = await styleOf(page, canvas.id, 'Onderwijs');
    expect(styled['background-color']).toBe('rgb(17, 34, 51)');

    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('button', { name: 'Alles automatisch opmaken' }).click();
    await page.getByRole('dialog', { name: 'Alles automatisch opmaken?' }).getByRole('button', { name: 'Terugzetten' }).click();
    await expect(page.locator('[data-announcer]')).toContainText('Opmaak teruggezet naar automatisch');

    const reset = await styleOf(page, canvas.id, 'Onderwijs');
    const fresh = await styleOf(page, seedMoveContent().id, 'Onderwijs');
    expect(reset).toEqual(fresh);
    expect(reset['background-color']).not.toBe('rgb(17, 34, 51)');
});
