import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedPhp } from './support/seed';

function seed(applications: string[], used?: string): number {
    const records = used ? `$u = App\\Models\\Application::where('organisation_id', $o->id)->where('name', '${used}')->first(); App\\Models\\CostRecord::factory()->count(2)->create(['organisation_id' => $o->id, 'application_id' => $u->id]);` : '';
    const code = `$o = App\\Models\\Organisation::factory()->create(['name' => 'Org ${randomUUID().slice(0, 8)}']); foreach (${JSON.stringify(applications).replace(/"/g, "'")} as $n) { App\\Models\\Application::factory()->create(['organisation_id' => $o->id, 'name' => $n]); } ${records} echo 'ID=' . $o->id;`;
    const output = seedPhp(code);

    return Number(/ID=(\d+)/.exec(output)?.[1]);
}

test('an Application is saved with its Supplier and the page is accessible', async ({ page }) => {
    const id = seed([]);
    await signInAs(page, 'administrator');
    await page.goto(`/admin/organisations/${id}/applications`);
    await expect(page.getByRole('heading', { level: 1, name: 'Applicaties' })).toBeVisible();

    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    const panel = page.getByRole('dialog', { name: 'Nieuwe applicatie' });
    await panel.getByLabel('Naam').fill('Planningssysteem');
    await panel.getByLabel('Leverancier').fill('Leverancier X');
    await panel.getByRole('button', { name: 'Opslaan' }).click();

    await expect(page.getByRole('button', { name: 'Planningssysteem', exact: true })).toBeVisible();
    await expect(page.getByRole('cell', { name: 'Leverancier X' })).toBeVisible();
    await expectNoAxeViolations(page);
});

test('a used Application is replaced by another Application or by Geen applicatie', async ({ page }) => {
    const id = seed(['Gebruikt', 'Ander'], 'Gebruikt');
    await signInAs(page, 'administrator');
    await page.goto(`/admin/organisations/${id}/applications`);

    await page.getByRole('button', { name: 'Verwijderen Gebruikt' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Verwijderen' }).click();
    const replace = page.getByRole('dialog', { name: /Vervangen door voor Gebruikt/ });
    await replace.getByLabel('Vervangen door').click();
    await expect(page.getByRole('option')).toHaveText(['Ander', 'Geen applicatie']);
    await page.getByRole('option', { name: 'Geen applicatie' }).click();
    await replace.getByRole('button', { name: 'Vervangen en verwijderen' }).click();
    await expect(page.getByRole('button', { name: 'Gebruikt', exact: true })).toBeHidden();
    await expectNoAxeViolations(page);
});
