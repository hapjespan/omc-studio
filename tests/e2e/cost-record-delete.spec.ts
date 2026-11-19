import { randomUUID } from 'node:crypto';
import { expect, test } from './support/guards';
import { seedCostRecord, seedOrganisation, tinker } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('deletion names the consequence in numbers and Ongedaan maken restores it', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    seedCostRecord(organisation, 'Licentie', { budgetCents: 100, sets: 2 });
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);

    await page.getByRole('button', { name: 'Verwijderen Licentie' }).click();
    const dialog = page.getByRole('dialog', { name: 'Licentie verwijderen?' });
    await expect(dialog).toContainText('Verwijdert ook 1 jaarwaarde en 2 toewijzingssets');
    await dialog.getByRole('button', { name: 'Verwijderen' }).click();
    await expect(page.getByRole('row', { name: /Licentie/ })).toHaveCount(0);

    await page.getByRole('button', { name: 'Ongedaan maken' }).click();
    await expect(page.getByRole('row', { name: /Licentie/ })).toBeVisible();
});

test('archive hides the row, Toon gearchiveerd marks it as text and Terugzetten restores it', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    seedCostRecord(organisation, 'Licentie');
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);

    await page.getByRole('button', { name: 'Licentie', exact: true }).click();
    await page.getByRole('dialog', { name: 'Licentie' }).getByRole('button', { name: 'Archiveren' }).click();
    await expect(page.getByRole('row', { name: /Licentie/ })).toHaveCount(0);

    await page.getByLabel('Toon gearchiveerd').check();
    await expect(page.getByRole('row', { name: /Licentie/ })).toContainText('Gearchiveerd');
    await page.getByRole('button', { name: 'Terugzetten Licentie' }).click();
    await page.getByLabel('Toon gearchiveerd').uncheck();
    await expect(page.getByRole('row', { name: /Licentie/ })).not.toContainText('Gearchiveerd');
});

test('a bulk deletion reports deleted and skipped rows', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    seedCostRecord(organisation, 'Een');
    seedCostRecord(organisation, 'Twee');
    seedCostRecord(organisation, 'Drie', { budgetCents: 100 });
    tinker(`$f = App\\Models\\FiscalYear::where('organisation_id', ${organisation})->first(); $c = App\\Models\\Canvas::factory()->create(['organisation_id' => ${organisation}, 'name' => 'Strategie']); App\\Models\\FinalisedYear::factory()->create(['canvas_id' => $c->id, 'fiscal_year_id' => $f->id]);`);
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);

    await page.getByLabel('Alle rijen selecteren').check();
    await page.getByRole('button', { name: 'Verwijderen', exact: true }).first().click();

    await expect(page.getByText('2 verwijderd, 1 overgeslagen').first()).toBeVisible();
    await expect(page.getByText('Drie: 2026 is definitief in canvas Strategie')).toBeVisible();
    await expect(page.getByRole('row', { name: /Een/ })).toHaveCount(0);

    await expect(page.getByText('2 kostenposten verwijderd')).toBeVisible();
    await page.getByRole('button', { name: 'Ongedaan maken' }).click();

    await expect(page.getByRole('row', { name: /Een/ })).toBeVisible();
    await expect(page.getByRole('row', { name: /Twee/ })).toBeVisible();
});
