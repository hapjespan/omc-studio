import { randomUUID } from 'node:crypto';
import { expectNoAxeViolations, expect, test } from '../support/guards';
import { firstAdministratorEmail, firstAdministratorState, oneTimePassword } from '../support/accounts';

test('clean start: sign in with the logged password, choose a new one, sign out, sign in again', async ({ page }) => {
    test.slow();
    const administrator = { email: firstAdministratorEmail(), password: oneTimePassword() };
    expect([administrator.email, administrator.password], 'the install log holds the administrator and the one-time password').not.toContain('');
    const state = firstAdministratorState();
    expect(state, 'the first administrator from the install log exists').not.toBe('missing');
    test.skip(state === 'changed', 'needs a fresh install (scripts/serve-e2e.sh)');
    const chosen = `new-${randomUUID()}`;

    await page.goto('/sign-in');
    await expectNoAxeViolations(page);
    await page.getByLabel('E-mailadres').fill(administrator.email);
    await page.getByLabel('Wachtwoord').fill(administrator.password);
    await page.getByRole('button', { name: 'Inloggen' }).click();

    await expect(page.getByRole('heading', { name: 'Nieuw wachtwoord kiezen' })).toBeVisible();
    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'Nieuw wachtwoord kiezen' })).toBeVisible();
    await expectNoAxeViolations(page);

    await page.getByLabel('Wachtwoord', { exact: true }).fill('short');
    await page.getByLabel('Herhaal wachtwoord').fill('short');
    await page.getByRole('button', { name: 'Wachtwoord opslaan' }).click();
    await expect(page.getByRole('link', { name: 'Wachtwoord: gebruik minstens 12 tekens' })).toBeVisible();

    await page.getByLabel('Wachtwoord', { exact: true }).fill(chosen);
    await page.getByLabel('Herhaal wachtwoord').fill(chosen);
    await page.getByRole('button', { name: 'Wachtwoord opslaan' }).click();
    await expect(page.getByRole('heading', { name: 'Overzicht' })).toBeVisible();

    await page.getByRole('button', { name: 'Administrator' }).click();
    await page.getByRole('menuitem', { name: 'Uitloggen' }).click();
    await expect(page.getByRole('heading', { name: 'Inloggen' })).toBeVisible();

    await page.getByLabel('E-mailadres').fill(administrator.email);
    await page.getByLabel('Wachtwoord').fill(chosen);
    await page.getByRole('button', { name: 'Inloggen' }).click();
    await expect(page.getByRole('heading', { name: 'Overzicht' })).toBeVisible();
});
