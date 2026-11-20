import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedAllocation } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('the left column lists the Cost records of the year with a dot plus status text and searches', async ({ page }) => {
    const seed = seedAllocation(unique(), { 'Licentie planning': 23000, 'Beheer cluster': 10000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/allocation?year=2026`);

    await expect(page.getByRole('heading', { level: 1, name: /Toewijzen/ })).toBeVisible();
    const row = page.getByRole('button', { name: /Licentie planning/ });
    await expect(row).toContainText('Onbekende allocatie');
    await expect(row).toContainText('€ 230,00');

    await page.getByLabel('Zoeken').first().fill('beheer');
    await expect(page.getByRole('button', { name: /Licentie planning/ })).toHaveCount(0);
    await page.getByLabel('Zoeken').first().fill('');

    await row.click();
    await expect(page.getByRole('heading', { level: 2, name: 'Licentie planning' })).toBeVisible();
    await expectNoAxeViolations(page);
});

test('a Reader is refused', async ({ page }) => {
    const seed = seedAllocation(unique(), { Post: 100 });
    await signInAs(page, 'reader');

    expect((await page.goto(`/canvases/${seed.canvas}/allocation`))?.status()).toBe(403);
});
