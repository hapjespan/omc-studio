import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedOverlay } from './support/overlay';
import { signInAs } from './support/shell';

const unique = (): string => `Uitsplitsing ${randomUUID().slice(0, 8)}`;

test('hover and focus on a Domain list each Cost record in whole euros and stay while the pointer is over', async ({ page }) => {
    const seed = seedOverlay(unique());
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);

    const domain = page.locator('[data-kind="domain"]').filter({ hasText: 'Onderwijs' });
    await domain.hover({ position: { x: 6, y: 6 } });
    const content = page.locator('[data-hover-content]');
    await expect(content).toContainText('Uitsplitsing per kostenpost');
    await expect(content.locator('[data-breakdown]')).toContainText('Salarissen');
    await expect(content.locator('[data-breakdown]')).toContainText('100,0% · € 1.000');
    await content.hover();
    await page.waitForTimeout(400);
    await expect(content).toBeVisible();
    await expectNoAxeViolations(page);
    await page.keyboard.press('Escape');
    await expect(content).toHaveCount(0);

    await page.mouse.move(0, 0);
    await domain.focus();
    await expect(content.locator('[data-breakdown]')).toContainText('Salarissen');
});

test('selecting a Domain shows the figures and the breakdown in cents in the detail panel', async ({ page }) => {
    const seed = seedOverlay(unique());
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);

    await page.locator('[data-kind="domain"]').filter({ hasText: 'Onderwijs' }).click({ position: { x: 6, y: 6 } });
    const figures = page.locator('[data-element-figures]');
    await expect(figures).toContainText('Direct bedrag');
    await expect(figures).toContainText('€ 1.000,00');
    await expect(figures).toContainText('Prognose');
    await expect(figures).toContainText('€ 900,00');
    await expect(figures).toContainText('1,50');
    await expect(figures.locator('[data-breakdown]')).toContainText('100,00% · € 1.000,00');
    await expectNoAxeViolations(page);
});
