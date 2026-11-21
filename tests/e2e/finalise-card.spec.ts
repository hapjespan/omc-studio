import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedCostRecord, seedOrganisation } from './support/finance';
import { signInAs } from './support/shell';
import { seedPhp } from './support/seed';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('the Finalise card is disabled while Cost records sit in Unknown allocation and the selector shows the state', async ({ page }) => {
    const organisation = seedOrganisation(unique(), [2026]);
    seedCostRecord(organisation, 'Licentie', { budgetCents: 100, year: 2026 });
    const canvas = Number(/ID=(\d+)/.exec(seedPhp(`$c = App\\Models\\Canvas::factory()->create(['organisation_id' => ${organisation}]); App\\Services\\Canvas\\UnknownAllocationDomain::ensure($c); echo 'ID=' . $c->id;`))?.[1]);
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);

    await expect(page.getByText('Het jaar kan definitief zodra alle kostenposten zijn toegewezen. Nog 1 te gaan.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Jaar definitief maken' })).toBeDisabled();
    await expect(page.getByLabel('Boekjaar')).toContainText('2026 (open)');
    await expectNoAxeViolations(page);
});
