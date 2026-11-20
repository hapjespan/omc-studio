import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { changeLiveCanvas, damageSnapshot, seedSnapshot } from './support/snapshot';
import { signInAs } from './support/shell';

const unique = (): string => `Momentopname ${randomUUID().slice(0, 8)}`;

test('the financial overlay on a Snapshot shows the numbers of the moment after the live allocations were removed', async ({ page }) => {
    const seed = seedSnapshot(unique());
    changeLiveCanvas(seed.canvas);
    await signInAs(page, 'reader');
    await page.goto(`/snapshots/${seed.snapshot}/view?overlay=financial`);

    const card = page.locator('[data-kind="domain"]').filter({ hasText: 'Onderwijs' }).first();
    const id = await card.getAttribute('data-block');
    await expect(page.locator(`[data-overlay-card="${id}"] [data-total]`)).toHaveText('€ 1.000 25,0% van totaal');
    await expect(page.locator('[data-figure-panel]')).toContainText('€ 4.000');
    await expectNoAxeViolations(page);
});

test('the detail panel and the hover breakdown work from the stored figures', async ({ page }) => {
    const seed = seedSnapshot(unique());
    await signInAs(page, 'reader');
    await page.goto(`/snapshots/${seed.snapshot}/view?overlay=financial`);

    const card = page.locator('[data-kind="domain"]').filter({ hasText: 'Onderwijs' }).first();
    await expect(page.locator('[data-financial-card]').first()).toBeVisible();
    await card.hover();
    await expect(page.locator('[data-hover-content] [data-breakdown]')).toContainText('Salarissen');
    await card.click();
    await expect(page.locator('[data-element-figures]')).toContainText('Totaal');
    await expect(page.locator('[data-element-figures] [data-breakdown]')).toContainText('Salarissen');
});

test('the modality overlay works on a Snapshot', async ({ page }) => {
    const seed = seedSnapshot(unique());
    await signInAs(page, 'reader');
    await page.goto(`/snapshots/${seed.snapshot}/view?overlay=modality`);
    await expect(page.getByRole('button', { name: 'Modaliteit' })).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('[data-overlay-card]').first()).toBeAttached();
});

test('a damaged Snapshot shows the damaged message and no figures and no Elements', async ({ page }) => {
    const seed = seedSnapshot(unique());
    damageSnapshot(seed.snapshot);
    await signInAs(page, 'reader');
    await page.goto(`/snapshots/${seed.snapshot}/view?overlay=financial`);

    await expect(page.getByRole('alert')).toHaveText('Deze momentopname is beschadigd en wordt niet getoond');
    await expect(page.locator('[data-financial-card]')).toHaveCount(0);
    await expect(page.locator('[data-block]')).toHaveCount(0);
    await expect(page.locator('[data-figure-panel]')).toHaveCount(0);
});
