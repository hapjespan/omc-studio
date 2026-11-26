import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedOverlay } from './support/overlay';
import { signInAs } from './support/shell';

const unique = (): string => `Modaliteit ${randomUUID().slice(0, 8)}`;

test('the modality overlay marks Elements with their letter, lists the legend and counts per Domain', async ({ page }) => {
    const seed = seedOverlay(unique());
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=modality&year=2026`);

    const legend = page.locator('[data-legend="modality"]');
    for (const text of ['C: Common', 'A: Adaptive', 'S: Specialized', 'D: Distinct']) await expect(legend).toContainText(text);

    const counts = page.locator('[data-modality-counts]').filter({ hasText: 'C 2, A 1, S 0, D 0, geen 2' });
    await expect(counts).toHaveCount(1);
    await expect(counts).toBeVisible();
    const clear = await page.evaluate(() => {
        const strip = document.querySelector('[data-modality-counts]')!;
        const rect = strip.getBoundingClientRect();
        const layer = Number(getComputedStyle(strip.closest('[data-overlay-card]')!).zIndex);
        const blocks = [...document.querySelectorAll('[data-block]')];
        const above = blocks.every((node) => Number(getComputedStyle(node).zIndex) < layer);
        const free = [...document.querySelectorAll('[data-kind="activity"], [data-kind="cluster"]')].every((node) => {
            const box = node.getBoundingClientRect();
            return box.bottom <= rect.top + 1 || box.top >= rect.bottom || box.right <= rect.left || box.left >= rect.right;
        });
        return above && free;
    });
    expect(clear).toBe(true);
    await expect(page.locator('[data-block]', { hasText: 'Toetsen' }).locator('[aria-hidden="true"]', { hasText: 'A' }).first()).toBeVisible();
    await expect(page.locator('[data-financial-card]')).toHaveCount(0);
    await expectNoAxeViolations(page);
});

test('switching the overlay keeps the mode in the address', async ({ page }) => {
    const seed = seedOverlay(unique());
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?year=2026`);

    await page.getByRole('group', { name: 'Overlay' }).getByRole('button', { name: 'Modaliteit' }).click();
    await expect(page).toHaveURL(/overlay=modality/);
    await page.getByRole('group', { name: 'Overlay' }).getByRole('button', { name: 'Financieel' }).click();
    await expect(page).toHaveURL(/overlay=financial/);
    await expect(page.locator('[data-financial-card]').first()).toBeVisible();
    await page.getByRole('group', { name: 'Overlay' }).getByRole('button', { name: 'Geen' }).click();
    await expect(page).not.toHaveURL(/overlay=/);
    await expect(page.locator('[data-modality-counts]')).toHaveCount(0);
});
