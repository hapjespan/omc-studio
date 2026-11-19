import { expect, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedListCanvas } from '../support/view';

test('the Direction band shows the Logo box, uppercase labels and the goals as a numbered list with a bold title', async ({ page }) => {
    const canvas = seedListCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);

    await expect(page.locator('[data-direction-logo]')).toHaveText('Logo');
    expect(await page.locator('[data-direction-logo]').evaluate((element) => getComputedStyle(element).borderStyle)).toBe('dashed');
    const label = page.locator('[data-direction="goals"] p').first();
    await expect(label).toHaveText('Strategische doelen');
    expect(await label.evaluate((element) => getComputedStyle(element).textTransform)).toBe('uppercase');
    const goal = page.locator('[data-direction="goals"] p').nth(1);
    await expect(goal).toHaveText('1. Eén kindprofiel. Van opvang tot school');
    expect(await goal.locator('strong').textContent()).toBe('1. Eén kindprofiel.');
});

test('the Rand A3 edge is a 1 pixel ink line', async ({ page }) => {
    const canvas = seedListCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    const edge = page.locator('[data-artboard-edge]');
    expect(await edge.evaluate((element) => [getComputedStyle(element).borderTopWidth, getComputedStyle(element).borderTopStyle])).toEqual(['1px', 'solid']);
});
