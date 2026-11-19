import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { changeLiveCanvas, seedSnapshot } from './support/snapshot';
import { signInAs } from './support/shell';

const unique = (): string => `Momentopname ${randomUUID().slice(0, 8)}`;

test('a Reader opens a Snapshot from the Versies list and sees the banner and the old structure after the live Canvas changed', async ({ page }) => {
    const seed = seedSnapshot(unique());
    changeLiveCanvas(seed.canvas);
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/versions`);
    await page.getByRole('link', { name: 'Stand voor de stuurgroep' }).click();

    await expect(page).toHaveURL(new RegExp(`/snapshots/${seed.snapshot}/view`));
    const banner = page.getByRole('region', { name: 'Momentopname' });
    await expect(banner).toContainText(/Momentopname Stand voor de stuurgroep van \d{2}-\d{2}-\d{4}/);
    await expect(page.getByRole('status').filter({ hasText: 'Momentopname Stand voor de stuurgroep' })).toHaveCount(1);
    await expect(page.locator('[data-kind="domain"]').filter({ hasText: 'Onderwijs' }).first()).toBeVisible();
    await expect(page.locator('[data-kind="activity"]').filter({ hasText: 'Toetsen' }).first()).toBeVisible();
    await expect(page.getByText('Hernoemd')).toHaveCount(0);
    await expectNoAxeViolations(page);
});

test('the Versie selector leads to the live view', async ({ page }) => {
    const seed = seedSnapshot(unique());
    await signInAs(page, 'reader');
    await page.goto(`/snapshots/${seed.snapshot}/view`);
    await expect(page.getByRole('region', { name: 'Momentopname' })).toContainText('Boekjaar 2026');
    await page.getByLabel('Versie').selectOption({ label: 'Actueel' });
    await expect(page).toHaveURL(new RegExp(`/snapshots/${seed.snapshot}/view`));
    await page.getByRole('button', { name: 'Openen' }).click();
    await expect(page).toHaveURL(new RegExp(`/canvases/${seed.canvas}/view`));
    await expect(page.getByRole('option', { name: /Stand voor de stuurgroep \(2026, \d{2}-\d{2}-\d{4}\)/ })).toBeAttached();
    await page.getByLabel('Versie').selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Openen' }).click();
    await expect(page).toHaveURL(new RegExp(`/snapshots/${seed.snapshot}/view`));
    await expect(page.getByRole('link', { name: 'Versies' })).toHaveAttribute('aria-current', 'page');
});
