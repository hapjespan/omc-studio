import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedAllocation } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('the Canvas variant shows the Toegewezen column with dot plus text and the three cards', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000, Beheer: 10000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/cost-records?year=2026`);

    await expect(page.getByRole('columnheader', { name: 'Toegewezen' })).toBeVisible();
    await expect(page.getByRole('row', { name: /Licentie/ })).toContainText('Onbekende allocatie');
    const cards = page.getByRole('region', { name: 'Samenvatting van het canvas' });
    await expect(cards).toContainText('Totaal budget');
    await expect(cards).toContainText('€ 330');
    await expect(cards).toContainText('0 van 2 kostenposten');
    await expect(cards).toContainText('2 kostenposten, 100%');
    await expectNoAxeViolations(page);
});

test('a Reader is refused', async ({ page }) => {
    const seed = seedAllocation(unique(), { Post: 100 });
    await signInAs(page, 'reader');

    expect((await page.goto(`/canvases/${seed.canvas}/cost-records`))?.status()).toBe(403);
});
