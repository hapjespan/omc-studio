import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedOverlay } from './support/overlay';
import { signInAs } from './support/shell';

const unique = (): string => `Zijpaneel ${randomUUID().slice(0, 8)}`;

test('the side panel lists the totals and proves they close to the cent, with Nu toewijzen for an Administrator', async ({ page }) => {
    const seed = seedOverlay(unique());
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);

    const panel = page.getByRole('complementary', { name: 'Totalen van het canvas' });
    await expect(panel).toContainText('Budget');
    await expect(panel).toContainText('€ 4.000');
    await expect(panel).toContainText('Prognose');
    await expect(panel).toContainText('1 kostenpost · € 1.000');
    await expect(panel).toContainText('1 kostenpost · € 3.000');
    await expect(panel.locator('[data-closing]')).toHaveText('Totalen sluiten: € 1.000,00 + € 3.000,00 = € 4.000,00');
    await expect(panel).toContainText('Geen modaliteit');
    await expect(panel).toContainText('Onbekende allocatie');
    await expect(panel.getByRole('link', { name: 'Nu toewijzen' })).toBeVisible();
    await expectNoAxeViolations(page);
});

test('a Reader sees the side panel without the Nu toewijzen link', async ({ page }) => {
    const seed = seedOverlay(unique());
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);
    await expect(page.getByRole('complementary', { name: 'Totalen van het canvas' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Nu toewijzen' })).toHaveCount(0);
});
