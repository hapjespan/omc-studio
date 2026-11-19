import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedOverlay } from './support/overlay';
import { signInAs } from './support/shell';

const unique = (): string => `Filters ${randomUUID().slice(0, 8)}`;

test('a filter on Owner recalculates every number and shows above the canvas', async ({ page }) => {
    const seed = seedOverlay(unique());
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);

    const panel = page.getByRole('complementary', { name: 'Totalen van het canvas' });
    await expect(panel).toContainText('€ 4.000');
    await expect(page.getByLabel('Eigenaar')).toHaveValue('');

    await page.getByLabel('Eigenaar').selectOption({ label: 'Afdeling ICT' });
    await expect(page).toHaveURL(/owner=\d+/);
    await expect(page.locator('[data-active-filters]')).toHaveText('Actieve filters: Eigenaar: Afdeling ICT');
    await expect(panel).toContainText('€ 1.000');
    await expect(panel).not.toContainText('€ 4.000');
    await expect(panel.locator('[data-closing]')).toHaveText('Totalen sluiten: € 1.000,00 + € 0,00 = € 1.000,00');
    await expect(page.locator('[data-total]').first()).toHaveText('Totaal € 1.000');
    await expectNoAxeViolations(page);

    await page.getByLabel('Eigenaar').selectOption({ label: 'Alle' });
    await expect(page.locator('[data-active-filters]')).toHaveCount(0);
    await expect(panel).toContainText('€ 4.000');
});

test('a Cost kind filter narrows the figures', async ({ page }) => {
    const seed = seedOverlay(unique());
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026&cost_kind=supplier_contract`);
    await expect(page.getByLabel('Soort kosten')).toHaveValue('supplier_contract');
    await expect(page.getByRole('complementary', { name: 'Totalen van het canvas' })).toContainText('€ 3.000');
});

test('the Unknown allocation card shows its amount and Share like any card', async ({ page }) => {
    const seed = seedOverlay(unique());
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);

    const card = page.locator('[data-area="supporting_activities"][data-kind="domain"]').last();
    await card.hover({ position: { x: 4, y: 4 } });
    const content = page.locator('[data-hover-content]');
    await expect(content).toContainText('Totaal € 3.000');
    await expect(content).toContainText('75,0% van totaal');
    await expect(page.getByRole('complementary', { name: 'Totalen van het canvas' })).toContainText('Onbekende allocatie');
});
