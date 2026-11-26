import type { Page } from '@playwright/test';
import { createUser } from '../support/accounts';
import { expectNoAxeViolations, expect, test } from '../support/guards';

async function signIn(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/sign-in');
    await page.getByLabel('E-mailadres').fill(email);
    await page.getByLabel('Wachtwoord', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Inloggen' }).click();
    await page.waitForURL((url) => url.pathname !== '/sign-in');
}

test('the idle warning moves no focus and extending keeps unsaved input', async ({ page }) => {
    const user = createUser();
    await page.clock.install();
    await signIn(page, user.email, user.password);

    await page.getByRole('banner').getByRole('button', { name: 'Lezer' }).click();
    await page.getByRole('menuitem', { name: 'Mijn account' }).click();
    await expect(page.getByRole('heading', { name: 'Mijn account' })).toBeVisible();
    const field = page.getByLabel('Herhaal wachtwoord');
    await field.fill('nog-niet-opgeslagen');
    await expect(field).toBeFocused();

    await page.clock.fastForward(118 * 60_000);
    await page.clock.resume();

    await expect(page.getByText('Je sessie verloopt over 2 minuten').first()).toBeVisible();
    await expect(field).toBeFocused();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expectNoAxeViolations(page);

    await page.getByRole('button', { name: 'Sessie verlengen' }).click();

    await expect(page.getByRole('button', { name: 'Sessie verlengen' })).toHaveCount(0);
    await expect(field).toHaveValue('nog-niet-opgeslagen');
});

test('the session is still valid after extending and signing out works through the role menu', async ({ page }) => {
    const user = createUser();
    await page.clock.install();
    await signIn(page, user.email, user.password);

    await page.clock.fastForward(118 * 60_000);
    await page.clock.resume();
    await page.getByRole('button', { name: 'Sessie verlengen' }).click();
    await expect(page.getByRole('button', { name: 'Sessie verlengen' })).toHaveCount(0);

    await page.getByRole('banner').getByRole('button', { name: 'Lezer' }).click();
    await page.getByRole('menuitem', { name: 'Uitloggen' }).click();
    await expect(page.getByRole('heading', { name: 'Inloggen' })).toBeVisible();
});
