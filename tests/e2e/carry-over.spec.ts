import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedCostRecord, seedOrganisation, tinker } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

function seedCanvas(organisation: number): number {
    return Number(/ID=(\d+)/.exec(tinker(`$c = App\\Models\\Canvas::factory()->create(['organisation_id' => ${organisation}]); App\\Services\\Canvas\\UnknownAllocationDomain::ensure($c); echo 'ID=' . $c->id;`))?.[1]);
}

test('a run reports counts and tables with headings, a Finalised target year is refused', async ({ page }) => {
    const organisation = seedOrganisation(unique(), [2026, 2027]);
    seedCostRecord(organisation, 'Licentie', { budgetCents: 100, year: 2026 });
    const canvas = seedCanvas(organisation);
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);

    await page.getByRole('button', { name: 'Jaar overnemen' }).click();
    const panel = page.getByRole('dialog', { name: 'Jaar overnemen' });
    await panel.getByLabel('Bronjaar').selectOption('2026');
    await panel.getByLabel('Doeljaar').selectOption('2027');
    await panel.getByLabel('Ook jaarwaarden overnemen').check();
    await panel.getByRole('button', { name: 'Overnemen' }).click();

    await expect(panel.getByRole('status')).toHaveText('Overgenomen: 1, overgeslagen: 0, handmatig toewijzen: 0');
    await expectNoAxeViolations(page);

    tinker(`$f = App\\Models\\FiscalYear::where('organisation_id', ${organisation})->where('year', 2027)->first(); App\\Models\\FinalisedYear::factory()->create(['canvas_id' => ${canvas}, 'fiscal_year_id' => $f->id]);`);
    await panel.getByRole('button', { name: 'Overnemen' }).click();
    await expect(panel.getByRole('alert')).toContainText('2027 is definitief in canvas');
});
