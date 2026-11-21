import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedPhp } from './support/seed';

function seed(organisation: string, owners: string[], used?: { owner: string; records: number }): number {
    const records = used ? `$u = App\\Models\\Owner::where('organisation_id', $o->id)->where('name', '${used.owner}')->first(); App\\Models\\CostRecord::factory()->count(${used.records})->create(['organisation_id' => $o->id, 'owner_id' => $u->id]);` : '';
    const code = `$o = App\\Models\\Organisation::factory()->create(['name' => '${organisation}']); foreach (${JSON.stringify(owners).replace(/"/g, "'")} as $n) { App\\Models\\Owner::factory()->create(['organisation_id' => $o->id, 'name' => $n]); } ${records} echo 'ID=' . $o->id;`;
    const output = seedPhp(code);

    return Number(/ID=(\d+)/.exec(output)?.[1]);
}

const unique = (): string => randomUUID().slice(0, 8);

test('an Owner is created with its hint, a duplicate is refused, and the page is accessible', async ({ page }) => {
    const id = seed(`Org ${unique()}`, []);
    await signInAs(page, 'administrator');
    await page.goto(`/admin/organisations/${id}/owners`);
    await expect(page.getByRole('heading', { level: 1, name: 'Eigenaren' })).toBeVisible();

    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    const panel = page.getByRole('dialog', { name: 'Nieuwe eigenaar' });
    await expect(panel.getByText('Afdeling of rol, geen persoonsnaam')).toBeVisible();
    await panel.getByLabel('Naam').fill('Afdeling ICT');
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByRole('button', { name: 'Afdeling ICT', exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    await panel.getByLabel('Naam').fill('  afdeling ict ');
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(panel.getByRole('link', { name: 'Naam: deze naam staat al in de lijst' })).toBeVisible();
    await expectNoAxeViolations(page);
});

test('an unused Owner is deleted with undo, a used Owner only through Vervangen door', async ({ page }) => {
    const id = seed(`Org ${unique()}`, ['Gebruikt', 'Ander', 'Los'], { owner: 'Gebruikt', records: 3 });
    await signInAs(page, 'administrator');
    await page.goto(`/admin/organisations/${id}/owners`);

    await page.getByRole('button', { name: 'Verwijderen Los' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Verwijderen' }).click();
    await expect(page.getByRole('button', { name: 'Los', exact: true })).toBeHidden();
    await page.getByRole('button', { name: 'Ongedaan maken' }).click();
    await expect(page.getByRole('button', { name: 'Los', exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Verwijderen Gebruikt' }).click();
    await expect(page.getByRole('dialog').getByText(/3 kostenposten/)).toBeVisible();
    await page.getByRole('dialog').getByRole('button', { name: 'Verwijderen' }).click();
    const replace = page.getByRole('dialog', { name: /Vervangen door voor Gebruikt/ });
    await replace.getByLabel('Vervangen door').click();
    await page.getByRole('option', { name: 'Ander' }).click();
    await replace.getByRole('button', { name: 'Vervangen en verwijderen' }).click();
    await expect(page.getByRole('button', { name: 'Gebruikt', exact: true })).toBeHidden();
    await expectNoAxeViolations(page);
});
