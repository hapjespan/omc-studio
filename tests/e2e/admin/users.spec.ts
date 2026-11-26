import { randomUUID } from 'node:crypto';
import type { Page } from '@playwright/test';
import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedPhp } from '../support/seed';
const reauthenticate = 'Huidig wachtwoord: vul je huidige wachtwoord in om je e-mailadres te wijzigen';

interface Account {
    name: string;
    email: string;
    password: string;
}

function seedUser(options: { role?: 'administrator' | 'reader'; secondFactor?: boolean } = {}): Account {
    const account = { name: `Gebruiker ${randomUUID().slice(0, 8)}`, email: `${randomUUID()}@example.test`, password: `pw-${randomUUID()}` };
    const state = options.role === 'administrator' ? '->administrator()' : '';
    const factor = options.secondFactor ? ", 'two_factor_confirmed_at' => now()" : '';
    seedPhp(`App\\Models\\User::factory()${state}->create(['name' => '${account.name}', 'email' => '${account.email}', 'password' => '${account.password}'${factor}]);`);

    return account;
}

async function signIn(page: Page, account: Account): Promise<void> {
    await page.goto('/sign-in');
    await page.getByLabel('E-mailadres').fill(account.email);
    await page.getByLabel('Wachtwoord').fill(account.password);
    await page.getByRole('button', { name: 'Inloggen' }).click();
    await page.waitForURL((url) => url.pathname !== '/sign-in');
    await page.getByRole('heading', { level: 1 }).waitFor();
}

const onePassword = (page: Page) => page.getByRole('region', { name: 'Eenmalig wachtwoord' });

test('an Administrator creates a Reader, copies the one-time password once, and Back does not show it', async ({ page, context, browserName }) => {
    if (browserName === 'chromium') await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    const name = `Gebruiker ${randomUUID().slice(0, 8)}`;
    const email = `${randomUUID()}@example.test`;
    await signInAs(page, 'administrator');
    await page.goto('/admin/users');
    await expect(page.getByRole('heading', { level: 1, name: 'Gebruikers' })).toBeVisible();
    await expect(page).toHaveTitle(/Gebruikers/);

    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    const panel = page.getByRole('dialog', { name: 'Nieuwe gebruiker' });
    await panel.getByLabel('Naam').fill(name);
    await panel.getByLabel('E-mailadres').fill(email);
    await expect(panel.getByRole('combobox', { name: 'Rol' })).toHaveText('Lezer');
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(panel).toBeHidden();
    await expect(page.getByRole('button', { name, exact: true })).toBeVisible();
    await expect(page.getByRole('row', { name: new RegExp(name) })).toContainText('Lezer');

    const password = (await onePassword(page).getByTestId('one-time-password').innerText()).trim();
    expect(password).toHaveLength(16);
    await expectNoAxeViolations(page);

    await onePassword(page).getByRole('button', { name: 'Kopiëren' }).click();
    await expect(page.getByRole('status').filter({ hasText: /Wachtwoord gekopieerd|Kopiëren is niet gelukt/ })).toBeVisible();
    if (browserName === 'chromium') {
        await expect(page.getByRole('status').filter({ hasText: 'Wachtwoord gekopieerd' })).toBeVisible();
        expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(password);
    }

    await page.goto('/admin/audit-log');
    await page.goBack();
    await expect(page.getByRole('heading', { level: 1, name: 'Gebruikers' })).toBeVisible();
    await expect(onePassword(page)).toHaveCount(0);
    const state = await page.evaluate(() => JSON.stringify(window.history.state));
    expect(state, 'the one-time password in the Back stack').not.toContain(password);

    await page.reload();
    await expect(onePassword(page)).toHaveCount(0);
});

test('the user list sorts on the E-mailadres column', async ({ page }) => {
    const admin = seedUser({ role: 'administrator' });
    const token = randomUUID().slice(0, 8);
    const first = `aaa-${token}@example.test`;
    const last = `zzz-${token}@example.test`;
    seedPhp(`App\\Models\\User::factory()->create(['name' => 'Sorteer ${token} A', 'email' => '${last}']); App\\Models\\User::factory()->create(['name' => 'Sorteer ${token} Z', 'email' => '${first}']);`);
    await signIn(page, admin);
    await page.goto('/admin/users');
    await page.getByRole('searchbox', { name: 'Zoeken' }).fill(token);

    const header = page.getByRole('columnheader', { name: /E-mailadres/ });
    const cells = page.getByRole('cell', { name: /@example\.test$/ });
    await expect(cells).toHaveCount(2);
    await header.getByRole('button').click();
    await expect(header).toHaveAttribute('aria-sort', 'ascending');
    await expect(cells.first()).toHaveText(first);

    await header.getByRole('button').click();
    await expect(header).toHaveAttribute('aria-sort', 'descending');
    await expect(cells.first()).toHaveText(last);
});

test('an e-mail address that differs only in case is refused', async ({ page }) => {
    const existing = seedUser();
    await signInAs(page, 'administrator');
    await page.goto('/admin/users');

    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    const panel = page.getByRole('dialog', { name: 'Nieuwe gebruiker' });
    await panel.getByLabel('Naam').fill(`Gebruiker ${randomUUID().slice(0, 8)}`);
    await panel.getByLabel('E-mailadres').fill(existing.email.toUpperCase());
    await panel.getByRole('button', { name: 'Opslaan' }).click();

    await expect(panel.getByRole('link', { name: 'E-mailadres: dit adres is al in gebruik' })).toBeVisible();
    await expect(panel.getByLabel('E-mailadres')).toHaveAttribute('aria-invalid', 'true');
    await expect(panel.getByLabel('E-mailadres')).toBeFocused();
    await expectNoAxeViolations(page);
});

test('the own row keeps the role and the account, and an e-mail change asks for the current password', async ({ page }) => {
    const administrator = seedUser({ role: 'administrator' });
    seedUser({ role: 'administrator' });
    await signIn(page, administrator);
    await page.goto('/admin/users');

    const searched = page.waitForResponse((response) => response.url().includes('search='));
    await page.getByRole('searchbox', { name: 'Zoeken' }).fill(administrator.name);
    await searched;
    await expect(page.getByRole('status').filter({ hasText: '1 resultaat' })).toBeVisible();
    await page.getByRole('button', { name: administrator.name, exact: true }).click();
    const panel = page.getByRole('dialog', { name: administrator.name });
    const role = panel.getByRole('combobox', { name: 'Rol' });
    await expect(role).toBeDisabled();
    await expect(role).toHaveText('Administrator');
    await expect(role).toHaveAccessibleDescription('Je kunt je eigen account niet verwijderen of je eigen rol wijzigen');
    const remove = panel.getByRole('button', { name: 'Verwijderen' });
    await expect(remove).toBeDisabled();
    await expect(remove).toHaveAccessibleDescription('Je kunt je eigen account niet verwijderen of je eigen rol wijzigen');
    await expect(panel.getByRole('button', { name: 'Wachtwoord resetten' })).toHaveCount(0);
    await expect(panel.getByRole('button', { name: 'Tweede factor resetten' })).toHaveCount(0);
    await expect(panel.getByRole('button', { name: 'Sessies beëindigen' })).toHaveCount(0);
    await expect(panel.getByLabel('Huidig wachtwoord')).toHaveCount(0);

    const changed = `${randomUUID()}@example.test`;
    await panel.getByLabel('E-mailadres').fill(changed);
    await expect(panel.getByLabel('Huidig wachtwoord')).toBeVisible();
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(panel.getByRole('alert').getByRole('link', { name: reauthenticate })).toBeVisible();
    await expect(panel.getByLabel('Huidig wachtwoord')).toBeFocused();
    await expectNoAxeViolations(page);

    await panel.getByLabel('Huidig wachtwoord').fill('not-the-password');
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(panel.getByRole('link', { name: reauthenticate })).toBeVisible();

    await panel.getByLabel('Huidig wachtwoord').fill(administrator.password);
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(panel).toBeHidden();
    await expect(page.getByRole('cell', { name: changed })).toBeVisible();
});

test('an Administrator resets the password and the second factor of another user, ends their sessions and deletes them', async ({ page }) => {
    const administrator = seedUser({ role: 'administrator' });
    const other = seedUser({ secondFactor: true });
    await signIn(page, administrator);
    await page.goto('/admin/users');
    const panel = page.getByRole('dialog', { name: other.name });

    await page.getByRole('button', { name: other.name, exact: true }).click();
    await panel.getByRole('button', { name: 'Tweede factor resetten' }).click();
    const factor = page.getByRole('dialog', { name: `Tweede factor van ${other.name} resetten?` });
    await expect(factor).toContainText('herstelcodes');
    await factor.getByRole('button', { name: 'Tweede factor resetten' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'De tweede factor is gereset en de sessies zijn beëindigd' })).toBeVisible();

    await page.getByRole('button', { name: other.name, exact: true }).click();
    await expect(panel.getByRole('button', { name: 'Tweede factor resetten' })).toHaveCount(0);
    await panel.getByRole('button', { name: 'Wachtwoord resetten' }).click();
    await expect(panel).toBeHidden();
    await expect(page.getByRole('status').filter({ hasText: 'Het wachtwoord is gereset' })).toBeVisible();
    expect((await onePassword(page).getByTestId('one-time-password').innerText()).trim()).toHaveLength(16);

    await page.getByRole('button', { name: other.name, exact: true }).click();
    await panel.getByRole('button', { name: 'Sessies beëindigen' }).click();
    const confirm = page.getByRole('dialog', { name: `Sessies van ${other.name} beëindigen?` });
    await expect(confirm).toContainText('Deze gebruiker moet opnieuw inloggen');
    await expectNoAxeViolations(page);
    await confirm.getByRole('button', { name: 'Sessies beëindigen' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'De sessies van deze gebruiker zijn beëindigd' })).toBeVisible();

    await page.getByRole('button', { name: other.name, exact: true }).click();
    await panel.getByRole('button', { name: 'Verwijderen' }).click();
    const removal = page.getByRole('dialog', { name: `${other.name} verwijderen?` });
    await expect(removal).toContainText('De sessies van deze gebruiker stoppen meteen');
    await removal.getByRole('button', { name: 'Verwijderen', exact: true }).click();
    await expect(page.getByRole('button', { name: other.name, exact: true })).toHaveCount(0);
    await expect(page.getByRole('status').filter({ hasText: 'De gebruiker is verwijderd' })).toBeVisible();
    await expect(page.getByRole('searchbox', { name: 'Zoeken' })).toBeFocused();
});

test('a Reader requesting the Users page gets Geen toegang', async ({ page }) => {
    await signInAs(page, 'reader');
    const response = await page.goto('/admin/users');
    expect(response?.status()).toBe(403);
});
