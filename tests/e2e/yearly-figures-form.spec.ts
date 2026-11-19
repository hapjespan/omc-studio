import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedCostRecord, seedOrganisation, tinker } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

async function open(page: import('@playwright/test').Page, organisation: number, name: string) {
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);
    await page.getByRole('button', { name, exact: true }).click();

    return page.getByRole('dialog', { name });
}

test('Yearly figures are saved and the Undetermined share shows', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    seedCostRecord(organisation, 'Licentie');
    const panel = await open(page, organisation, 'Licentie');

    await expect(panel.getByLabel(/^Budget/)).toHaveAccessibleName(/bijvoorbeeld 1\.234,56/);
    await panel.getByLabel(/^Budget/).fill('12.500,00');
    await panel.getByLabel(/^Prognose/).fill('13.000');
    await panel.getByLabel(/^Run/).fill('60');
    await panel.getByLabel(/^Change/).fill('30');
    await panel.getByLabel(/^FTE/).fill('1,5');
    await expect(panel.getByText('Onbepaald deel: 10,00%')).toBeVisible();
    await panel.getByRole('button', { name: 'Jaarwaarden opslaan' }).click();

    await expect(page.locator('tr', { hasText: 'Licentie' })).toContainText('€ 12.500,00');
});

test('refusals show next to the field and values are kept', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    seedCostRecord(organisation, 'Licentie');
    const panel = await open(page, organisation, 'Licentie');

    await panel.getByLabel(/^Budget/).fill('12.50');
    await panel.getByLabel(/^Run/).fill('70');
    await panel.getByLabel(/^Change/).fill('40');
    await panel.getByRole('button', { name: 'Jaarwaarden opslaan' }).click();

    await expect(panel.getByRole('link', { name: /^Budget: vul een bedrag in van € 0,00 tot € 999\.999\.999,99/ })).toBeVisible();
    await expect(panel.getByLabel(/^Budget/)).toHaveAttribute('aria-invalid', 'true');
    await expect(panel.getByLabel(/^Budget/)).toHaveValue('12.50');
    await expectNoAxeViolations(page);

    await panel.getByLabel(/^Budget/).fill('12,50');
    await panel.getByRole('button', { name: 'Jaarwaarden opslaan' }).click();
    await expect(panel.getByRole('alert')).toContainText('Run en change: samen maximaal 100%');
});

test('Ongedaan maken restores deleted Yearly figures with their Allocation sets', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    seedCostRecord(organisation, 'Licentie', { budgetCents: 100, sets: 2 });
    const panel = await open(page, organisation, 'Licentie');

    await panel.getByRole('button', { name: 'Jaarwaarden verwijderen' }).click();
    await page.getByRole('dialog', { name: '2026 verwijderen?' }).getByRole('button', { name: 'Verwijderen' }).click();
    await expect(page.locator('tr', { hasText: 'Licentie' })).toContainText('geen jaarwaarden');

    await expect(panel.getByText('Jaarwaarden verwijderd')).toBeVisible();
    await panel.getByRole('button', { name: 'Ongedaan maken' }).click();
    await expect(page.locator('tr', { hasText: 'Licentie' })).toContainText('€ 1,00');
});

test('a Finalised year refuses with the Canvas name and deletion is counted', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    seedCostRecord(organisation, 'Licentie', { budgetCents: 100, sets: 2 });
    const panel = await open(page, organisation, 'Licentie');

    await panel.getByRole('button', { name: 'Jaarwaarden verwijderen' }).click();
    await expect(page.getByRole('dialog', { name: '2026 verwijderen?' })).toContainText('Verwijdert ook 2 toewijzingssets');
    await page.getByRole('dialog', { name: '2026 verwijderen?' }).getByRole('button', { name: 'Verwijderen' }).click();
    await expect(page.locator('tr', { hasText: 'Licentie' })).toContainText('geen jaarwaarden');

    tinker(`$f = App\\Models\\FiscalYear::where('organisation_id', ${organisation})->first(); $c = App\\Models\\Canvas::factory()->create(['organisation_id' => ${organisation}, 'name' => 'Strategie']); App\\Models\\FinalisedYear::factory()->create(['canvas_id' => $c->id, 'fiscal_year_id' => $f->id]);`);
    await page.reload();
    await page.getByRole('button', { name: 'Licentie', exact: true }).click();
    await panel.getByLabel(/^Budget/).fill('1');
    await panel.getByRole('button', { name: 'Jaarwaarden opslaan' }).click();
    await expect(panel.getByRole('alert')).toContainText('2026 is definitief in canvas Strategie. Heropen het jaar eerst');
});
