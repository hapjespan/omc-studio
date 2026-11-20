import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedOrganisation, tinker } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

function seedFinalisedCanvas(organisation: number, name: string): void {
    tinker(`$c = App\\Models\\Canvas::factory()->create(['organisation_id' => ${organisation}, 'name' => '${name}']); $f = App\\Models\\FiscalYear::where('organisation_id', ${organisation})->where('year', 2026)->first(); App\\Models\\FinalisedYear::factory()->create(['canvas_id' => $c->id, 'fiscal_year_id' => $f->id]);`);
}

test('the panel shows the state per canvas as text and Heropenen asks for confirmation', async ({ page }) => {
    const organisation = seedOrganisation(unique(), [2026]);
    const canvas = `Canvas ${randomUUID().slice(0, 8)}`;
    seedFinalisedCanvas(organisation, canvas);
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);

    await page.getByRole('button', { name: 'Boekjaren' }).click();
    const panel = page.getByRole('dialog', { name: 'Boekjaren' });
    await expect(panel.getByText(/Definitief sinds \d{2}-\d{2}-\d{4} door/)).toBeVisible();
    await expectNoAxeViolations(page);

    await panel.getByRole('button', { name: `Heropenen 2026 ${canvas}` }).click();
    const confirm = page.getByRole('dialog', { name: `Jaar 2026 heropenen in canvas ${canvas}?` });
    await confirm.getByRole('button', { name: 'Annuleren' }).click();
    await expect(panel.getByText(/Definitief sinds/)).toBeVisible();

    await panel.getByRole('button', { name: `Heropenen 2026 ${canvas}` }).click();
    await page.getByRole('dialog', { name: `Jaar 2026 heropenen in canvas ${canvas}?` }).getByRole('button', { name: 'Heropenen' }).click();
    await expect(panel.getByText('Open', { exact: true })).toBeVisible();
});
