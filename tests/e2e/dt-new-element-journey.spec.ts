import type { Page } from '@playwright/test';
import { expect, test } from './support/guards';
import { ARTBOARD_WIDTH } from './support/artboard';
import { openEntry } from './support/entry';
import { seedMoveCanvas } from './support/drawing';

const AREA = { x: 780, y: 400 };
const DOMAIN = { x: 20, y: 40 };
const NAME = 'Nieuwe activiteit';

interface Rectangle {
    x: number;
    y: number;
    width: number;
    height: number;
}

async function rectangleOf(page: Page, name: string): Promise<Rectangle> {
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name, exact: true }).click();
    const read = async (label: string): Promise<number> => Number(await page.getByLabel(`${label} van ${name}`).inputValue());

    return { x: await read('X'), y: await read('Y'), width: await read('B (breedte)'), height: await read('H (hoogte)') };
}

const overlaps = (a: Rectangle, b: Rectangle): boolean => a.x < b.x + b.width && b.x < a.x + a.width && a.y < b.y + b.height && b.y < a.y + a.height;

test('SC-026: an Activity added in Invoeren under a Manual Domain is placed by Tekenen under its lowest Manual child without handwork and Bekijken draws it at the same place', async ({ page }) => {
    const canvas = seedMoveCanvas();
    await openEntry(page, canvas.id);
    const field = page.getByLabel(/toevoegen aan Onderwijs/);
    await field.fill(NAME);
    await field.press('Enter');
    await expect(page.getByRole('list', { name: 'Structuur van Primaire activiteiten' }).getByText(NAME).first()).toBeVisible();

    await page.getByRole('group', { name: 'Modus' }).getByRole('link', { name: 'Tekenen' }).click();
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    const layers = page.getByRole('navigation', { name: 'Lagen' });
    const row = layers.getByRole('button', { name: NAME, exact: true });
    await expect(row).toBeVisible();
    await expect(row.locator('xpath=..').getByText('nieuw', { exact: true })).toBeVisible();

    const domain = await rectangleOf(page, 'Onderwijs');
    const cluster = await rectangleOf(page, 'Lessen');
    const added = await rectangleOf(page, NAME);
    expect(added.width).toBeGreaterThan(0);
    expect(added.height).toBeGreaterThan(0);
    expect(added.y, 'placed under the lowest Manual child').toBeGreaterThanOrEqual(cluster.y + cluster.height);
    expect(overlaps(added, cluster)).toBe(false);
    expect(added.x).toBeGreaterThanOrEqual(0);
    expect(added.x + added.width).toBeLessThanOrEqual(domain.width);
    expect(domain.height).toBeGreaterThanOrEqual(added.y + added.height);

    await page.goto(`/canvases/${canvas.id}/view`);
    const artboard = (await page.locator('[data-artboard]').boundingBox())!;
    const block = page.locator('[data-block]', { has: page.getByText(NAME, { exact: true }) }).first();
    const box = (await block.boundingBox())!;
    const scale = artboard.width / ARTBOARD_WIDTH;
    expect(Math.abs((box.x - artboard.x) / scale - (AREA.x + DOMAIN.x + added.x))).toBeLessThanOrEqual(1);
    expect(Math.abs((box.y - artboard.y) / scale - (AREA.y + DOMAIN.y + added.y))).toBeLessThanOrEqual(1);
    expect(Math.abs(box.width / scale - added.width)).toBeLessThanOrEqual(1);
});
