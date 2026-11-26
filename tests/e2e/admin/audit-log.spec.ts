import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedPhp } from '../support/seed';

function renameElement(userName: string, canvasName: string, oldName: string, newName: string): void {
    const code = [
        `$u = App\\Models\\User::factory()->administrator()->create(['name' => '${userName}']);`,
        `$c = App\\Models\\Canvas::factory()->create(['name' => '${canvasName}']);`,
        `$e = App\\Models\\Element::factory()->create(['canvas_id' => $c->id, 'name' => '${oldName}']);`,
        `Illuminate\\Support\\Facades\\Auth::login($u);`,
        `$e->update(['name' => '${newName}']);`,
    ].join(' ');
    seedPhp(code);
}

test('a renamed Element shows in Logboek with old and new name and filters by Canvas and user', async ({ page }) => {
    const canvasName = `Canvas ${randomUUID().slice(0, 8)}`;
    const userName = `Tester ${randomUUID().slice(0, 8)}`;
    renameElement(userName, canvasName, 'Inkoop', 'Inkoop en contractbeheer');
    await signInAs(page, 'administrator');
    await page.goto('/admin/audit-log');
    await expect(page.getByRole('heading', { level: 1, name: 'Logboek' })).toBeVisible();
    await expect(page).toHaveTitle(/Logboek/);

    await page.getByLabel('Canvas', { exact: true }).selectOption({ label: canvasName });
    await page.getByLabel('Gebruiker', { exact: true }).selectOption({ label: userName });
    await page.getByRole('button', { name: 'Filteren' }).click();

    const entry = page.getByRole('listitem').filter({ hasText: 'Inkoop en contractbeheer' }).first();
    await expect(entry).toContainText('Gewijzigd');
    await expect(entry).toContainText('Element');
    await expect(entry).toContainText(canvasName);
    await expect(entry).toContainText(userName);
    await expect(entry).toContainText('Naam');
    await expect(entry).toContainText('oud Inkoop,');
    await expect(entry).toContainText('nieuw Inkoop en contractbeheer');
    await expect(page.getByTestId('audit-count')).toContainText('vermeldingen');
    await expectNoAxeViolations(page);
});

test('a Reader requesting Logboek gets Geen toegang', async ({ page }) => {
    await signInAs(page, 'reader');
    const response = await page.goto('/admin/audit-log');
    expect(response?.status()).toBe(403);
});
