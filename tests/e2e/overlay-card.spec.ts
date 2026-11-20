import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedOverlay } from './support/overlay';
import { signInAs } from './support/shell';

const unique = (): string => `Overlay ${randomUUID().slice(0, 8)}`;

test('a Domain card draws its Total, Share, bar, forecast line and counts inside the card', async ({ page }) => {
    const seed = seedOverlay(unique());
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);

    const card = page.locator('[data-kind="domain"]').filter({ hasText: 'Onderwijs' }).first();
    const id = await card.getAttribute('data-block');
    const layer = page.locator(`[data-overlay-card="${id}"]`);
    await expect(layer.locator('[data-total]')).toHaveText('€ 1.000 25,0% van totaal');
    await expect(layer.locator('[data-forecast]')).toHaveText('Prognose € 900 · 90% van budget');
    await expect(layer.locator('[data-counts]')).toHaveText('4 activiteiten · 1 cluster');
    await expect(layer.locator('[data-bar]')).toBeVisible();
    await expect(card).toContainText('Prognose € 900');
    await expectNoAxeViolations(page);
});

test('a Domain without costs reads that no costs are allocated in the year', async ({ page }) => {
    const seed = seedOverlay(unique());
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);
    await expect(page.locator('[data-empty]').first()).toHaveText('Geen kosten toegewezen in 2026');
});

test('the closing message stays away while the figures close', async ({ page }) => {
    const seed = seedOverlay(unique());
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);
    await expect(page.locator('[data-financial-card]').first()).toBeVisible();
    await expect(page.locator('[data-figures-message]')).toHaveCount(0);
});
