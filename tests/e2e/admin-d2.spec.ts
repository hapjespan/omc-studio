import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

function seed(archived: boolean): { name: string; id: number } {
    const name = `Beheer ${randomUUID().slice(0, 8)}`;
    const state = archived ? ", 'archived_at' => now()" : '';
    const code = `$o = App\\Models\\Organisation::factory()->create(['name' => '${name}'${state}]); App\\Models\\Owner::factory()->create(['organisation_id' => $o->id, 'name' => 'Vast']); echo 'ID=' . $o->id;`;
    const output = execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env }).toString();

    return { name, id: Number(/ID=(\d+)/.exec(output)?.[1]) };
}

test('Eigenaren and Applicaties appear in Beheer for Administrators and open a list with an Organisation chooser', async ({ page }) => {
    const organisation = seed(false);
    await signInAs(page, 'administrator');
    await page.goto('/admin');
    await expect(page.getByRole('link', { name: 'Eigenaren' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Applicaties' })).toBeVisible();

    await page.goto(`/admin/organisations/${organisation.id}/owners`);
    const chooser = page.getByRole('navigation', { name: 'Organisatie' });
    await expect(chooser.getByLabel('Organisatie')).toHaveValue(String(organisation.id));
    await chooser.getByLabel('Organisatie').selectOption({ label: organisation.name });
    await expect(page.getByRole('heading', { level: 1, name: 'Eigenaren' })).toBeVisible();
    await expectNoAxeViolations(page);
});

test('an archived Organisation shows its lists read-only', async ({ page }) => {
    const organisation = seed(true);
    await signInAs(page, 'administrator');
    await page.goto(`/admin/organisations/${organisation.id}/owners`);

    await expect(page.getByRole('button', { name: 'Nieuw', exact: true })).toBeHidden();
    await expect(page.getByRole('button', { name: 'Verwijderen Vast' })).toBeHidden();
    await page.getByRole('button', { name: 'Vast', exact: true }).click();
    await expect(page.getByRole('dialog').getByRole('button', { name: 'Opslaan' })).toBeHidden();
});

test('a Reader gets no access to the lists', async ({ page }) => {
    const organisation = seed(false);
    await signInAs(page, 'reader');
    const response = await page.goto(`/admin/organisations/${organisation.id}/owners`);

    expect(response?.status()).toBe(403);
});
