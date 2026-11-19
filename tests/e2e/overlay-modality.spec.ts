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

    await expect(page.locator('[data-modality-counts]').filter({ hasText: 'C 2, A 1, S 0, D 0, geen 2' })).toHaveCount(1);
    await expect(page.locator('[data-block]', { hasText: 'Toetsen' }).locator('[aria-hidden="true"]', { hasText: 'A' }).first()).toBeVisible();
    await expect(page.locator('[data-financial-card]')).toHaveCount(0);
    await expectNoAxeViolations(page);
});

test('switching the overlay keeps the mode in the address', async ({ page }) => {
    const seed = seedOverlay(unique());
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?year=2026`);

    await page.getByLabel('Overlay').selectOption('modality');
    await expect(page).toHaveURL(/overlay=modality/);
    await page.getByLabel('Overlay').selectOption('financial');
    await expect(page).toHaveURL(/overlay=financial/);
    await expect(page.locator('[data-financial-card]').first()).toBeVisible();
    await page.getByLabel('Overlay').selectOption('none');
    await expect(page).not.toHaveURL(/overlay=/);
    await expect(page.locator('[data-modality-counts]')).toHaveCount(0);
});
