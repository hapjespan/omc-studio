import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedCostRecord, seedOrganisation } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('the table lists the year, shows "geen jaarwaarden", searches and has no Toegewezen column', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    seedCostRecord(organisation, 'Licentie planning', { code: 'LC-001', budgetCents: 1250000 });
    seedCostRecord(organisation, 'Zonder cijfers');
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);

    await expect(page.getByRole('heading', { level: 1, name: /Kostenposten/ })).toBeVisible();
    const row = page.getByRole('row', { name: /Licentie planning/ });
    await expect(row).toContainText('LC-001');
    await expect(row).toContainText('€ 12.500,00');
    await expect(page.getByRole('row', { name: /Zonder cijfers/ })).toContainText('geen jaarwaarden');
    await expect(page.getByRole('columnheader', { name: 'Toegewezen' })).toHaveCount(0);

    await page.getByLabel('Zoeken').fill('licentie');
    await expect(page.getByRole('row', { name: /Zonder cijfers/ })).toHaveCount(0);
    await expectNoAxeViolations(page);
});

test('a filter narrows the table and sortable headers are buttons', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    seedCostRecord(organisation, 'Toegewezen post', { budgetCents: 100, sets: 1 });
    seedCostRecord(organisation, 'Losse post', { budgetCents: 100 });
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);

    await page.getByLabel('Toewijzing').selectOption('unallocated');
    await expect(page.getByRole('row', { name: /Toegewezen post/ })).toHaveCount(0);
    await expect(page.getByRole('row', { name: /Losse post/ })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /Naam/ }).getByRole('button')).toBeVisible();
});

test('a Reader is refused', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    await signInAs(page, 'reader');
    const response = await page.goto(`/organisations/${organisation}/cost-records`);

    expect(response?.status()).toBe(403);
});
