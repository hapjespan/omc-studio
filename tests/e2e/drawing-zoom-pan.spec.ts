import type { Page } from '@playwright/test';
import { expect, test } from './support/guards';
import { openDrawing } from './support/drawing';

const view = async (page: Page): Promise<{ x: number; y: number; zoom: number }> => JSON.parse((await page.locator('[data-view]').getAttribute('data-view')) ?? '{}');
const settled = async (page: Page): Promise<{ x: number; y: number; zoom: number }> => {
    await page.waitForTimeout(450);
    return view(page);
};

test('Tekenen zooms in, out and to 100 percent with buttons that a keyboard can operate', async ({ page }) => {
    await openDrawing(page);
    const start = await settled(page);

    await page.getByRole('button', { name: 'Inzoomen', exact: true }).focus();
    await page.keyboard.press('Enter');
    expect((await settled(page)).zoom).toBeGreaterThan(start.zoom);
    await expect(page.locator('output').filter({ hasText: /^Zoom \d+%$/ })).not.toHaveAttribute('aria-live', 'polite');
    await expect(page.getByRole('status').filter({ hasText: /^Zoom ingesteld op \d+%$/ })).toBeAttached();

    await page.getByRole('button', { name: 'Uitzoomen', exact: true }).click();
    await page.getByRole('button', { name: 'Uitzoomen', exact: true }).click();
    expect((await settled(page)).zoom).toBeLessThan(start.zoom);

    await page.getByRole('button', { name: '100%', exact: true }).click();
    expect((await settled(page)).zoom).toBeCloseTo(1, 2);
    await expect(page.getByText('Zoom 100%')).toBeVisible();
});

test('Tekenen pans in four directions with buttons', async ({ page }) => {
    await openDrawing(page);
    const start = await settled(page);

    await page.getByRole('button', { name: 'Beeld naar rechts', exact: true }).click();
    const right = await settled(page);
    expect(right.x).toBeLessThan(start.x);
    await page.getByRole('button', { name: 'Beeld naar links', exact: true }).click();
    expect((await settled(page)).x).toBeGreaterThan(right.x);

    await page.getByRole('button', { name: 'Beeld omlaag', exact: true }).click();
    const down = await settled(page);
    expect(down.y).toBeLessThan(start.y);
    await page.getByRole('button', { name: 'Beeld omhoog', exact: true }).click();
    expect((await settled(page)).y).toBeGreaterThan(down.y);
});
