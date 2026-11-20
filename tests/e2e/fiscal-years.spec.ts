import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedCostRecord, seedOrganisation } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;
const refusal = 'Boekjaar: kies een jaar van 2000 tot en met 2100 dat nog niet bestaat';

test('a Fiscal year is added, out-of-range and duplicate years are refused', async ({ page }) => {
    const organisation = seedOrganisation(unique(), []);
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records`);

    await page.getByRole('button', { name: 'Boekjaren' }).click();
    const panel = page.getByRole('dialog', { name: 'Boekjaren' });
    await panel.getByLabel('Jaar').fill('2026');
    await panel.getByRole('button', { name: 'Boekjaar toevoegen' }).click();
    await expect(panel.getByText('2026', { exact: true })).toBeVisible();

    for (const bad of ['1999', '2101', '2026']) {
        await panel.getByLabel('Jaar').fill(bad);
        await panel.getByRole('button', { name: 'Boekjaar toevoegen' }).click();
        await expect(panel.getByRole('link', { name: refusal })).toBeVisible();
        await expect(panel.getByLabel('Jaar')).toHaveAttribute('aria-invalid', 'true');
    }
    await expectNoAxeViolations(page);
});

test('deletion needs confirmation and a year with Yearly figures is refused', async ({ page }) => {
    const organisation = seedOrganisation(unique(), [2026, 2027]);
    seedCostRecord(organisation, 'Licentie', { budgetCents: 100, year: 2026 });
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);

    await page.getByRole('button', { name: 'Boekjaren' }).click();
    const panel = page.getByRole('dialog', { name: 'Boekjaren' });
    await panel.getByRole('button', { name: 'Verwijderen 2026' }).click();
    await page.getByRole('dialog', { name: '2026 verwijderen?' }).getByRole('button', { name: 'Verwijderen' }).click();
    await expect(panel.getByRole('alert')).toContainText('Dit boekjaar heeft nog jaarwaarden');

    await panel.getByRole('button', { name: 'Verwijderen 2027' }).click();
    await page.getByRole('dialog', { name: '2027 verwijderen?' }).getByRole('button', { name: 'Verwijderen' }).click();
    await expect(panel.getByText('2027', { exact: true })).toHaveCount(0);
});
