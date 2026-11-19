import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedOrganisation, tinker } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

function seedLookups(organisation: number): void {
    tinker(`App\\Models\\Owner::factory()->create(['organisation_id' => ${organisation}, 'name' => 'Afdeling ICT']); App\\Models\\Application::factory()->create(['organisation_id' => ${organisation}, 'name' => 'Planningssysteem']);`);
}

test('the form shows every field, saves a Cost record and keeps values on refusal', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    seedLookups(organisation);
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);

    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    const panel = page.getByRole('dialog', { name: 'Nieuwe kostenpost' });
    for (const label of ['Code', 'Naam', 'Soort kosten', 'Leverancier', 'Eigenaar', 'Kostenplaats', 'Applicatie', 'Omschrijving']) {
        await expect(panel.getByLabel(label)).toBeVisible();
    }
    await expect(panel.getByText('Afdeling of rol, geen persoonsnaam')).toBeVisible();
    await expect(panel.getByLabel('Soort kosten').locator('option')).toContainText(['Leverancierscontract', 'Personeel', 'Project', 'Overig']);

    await panel.getByLabel('Code').fill('LC-001');
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(panel.getByRole('link', { name: 'Naam: vul een naam in' })).toBeVisible();
    await expect(panel.getByLabel('Naam')).toHaveAttribute('aria-invalid', 'true');
    await expect(panel.getByLabel('Naam')).toBeFocused();
    await expect(panel.getByLabel('Code')).toHaveValue('LC-001');
    await expectNoAxeViolations(page);

    await panel.getByLabel('Naam').fill('Licentie planning');
    await panel.getByLabel('Soort kosten').selectOption('supplier_contract');
    await panel.getByLabel('Eigenaar').selectOption({ label: 'Afdeling ICT' });
    await panel.getByLabel('Applicatie').selectOption({ label: 'Planningssysteem' });
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(panel).toBeHidden();
    await expect(page.getByRole('button', { name: 'Licentie planning' })).toBeVisible();
});

test('a duplicate code is refused next to the field', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    seedLookups(organisation);
    tinker(`App\\Models\\CostRecord::factory()->create(['organisation_id' => ${organisation}, 'code' => 'LC-001', 'name' => 'Bestaand']);`);
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);

    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    const panel = page.getByRole('dialog', { name: 'Nieuwe kostenpost' });
    await panel.getByLabel('Code').fill('lc-001');
    await panel.getByLabel('Naam').fill('Tweede');
    await panel.getByLabel('Soort kosten').selectOption('other');
    await panel.getByLabel('Eigenaar').selectOption({ index: 1 });
    await panel.getByRole('button', { name: 'Opslaan' }).click();

    await expect(panel.getByRole('link', { name: 'Code: deze code bestaat al in deze organisatie' })).toBeVisible();
    await expect(panel.getByLabel('Code')).toHaveAttribute('aria-invalid', 'true');
});
