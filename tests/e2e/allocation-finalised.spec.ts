import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedAllocation, tinker, expandTarget } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('a Finalised year shows the banner and disables every edit control', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    tinker(`$f = App\\Models\\FiscalYear::where('organisation_id', ${seed.organisation})->where('year', 2026)->first(); App\\Models\\FinalisedYear::factory()->create(['canvas_id' => ${seed.canvas}, 'fiscal_year_id' => $f->id]);`);
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/allocation?year=2026&cost_record=${seed.records.Licentie}`);
    await expandTarget(page, 'Inkoop');

    await expect(page.getByText('2026 is definitief')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Opslaan' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Set leegmaken' })).toBeDisabled();
    for (const button of await page.getByRole('button', { name: /^Toevoegen aan verdeling/ }).all()) await expect(button).toBeDisabled();
    await expectNoAxeViolations(page);
});
