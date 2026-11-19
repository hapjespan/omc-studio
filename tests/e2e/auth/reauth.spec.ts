import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';
import { createUser } from '../support/accounts';
import { expectNoAxeViolations, expect, test } from '../support/guards';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

function leaveSeconds(email: string, seconds: number): void {
    const code = [
        `$user = App\\Models\\User::where('email', '${email}')->firstOrFail();`,
        "foreach (Illuminate\\Support\\Facades\\DB::table('sessions')->where('user_id', $user->id)->get() as $row) {",
        '$payload = json_decode(base64_decode($row->payload), true);',
        `$payload['signed_in_at'] = time() - (12 * 3600 - ${seconds});`,
        "Illuminate\\Support\\Facades\\DB::table('sessions')->where('id', $row->id)->update(['payload' => base64_encode(json_encode($payload))]); }",
    ].join(' ');
    execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env });
}

async function signIn(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/sign-in');
    await page.getByLabel('E-mailadres').fill(email);
    await page.getByLabel('Wachtwoord', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Inloggen' }).click();
    await page.waitForURL((url) => url.pathname !== '/sign-in');
}

test('the dialog in the last ten minutes refuses a wrong password and renews the session without losing input', async ({ page }) => {
    const user = createUser();
    await page.clock.install();
    await signIn(page, user.email, user.password);

    await page.goto('/account');
    leaveSeconds(user.email, 630);
    await page.reload();
    await page.getByLabel('Herhaal wachtwoord').fill('nog-niet-opgeslagen');

    await page.clock.fastForward(60_000);
    await page.clock.resume();

    const dialog = page.getByRole('dialog');
    await expect(dialog.getByText('Je sessie eindigt over 10 minuten')).toBeVisible();
    await expect(dialog.getByText('Bevestig je wachtwoord om door te werken')).toBeVisible();
    await expect(dialog.locator(':focus')).toHaveCount(1);
    await expectNoAxeViolations(page);

    await dialog.getByLabel('Wachtwoord', { exact: true }).fill('niet-het-wachtwoord');
    await dialog.getByRole('button', { name: 'Doorwerken' }).click();
    await expect(dialog.getByRole('link', { name: 'Wachtwoord: dit wachtwoord is onjuist' })).toBeVisible();
    await expect(dialog).toBeVisible();

    await dialog.getByLabel('Wachtwoord', { exact: true }).fill(user.password);
    await dialog.getByRole('button', { name: 'Doorwerken' }).click();

    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByRole('status').filter({ hasText: 'Je sessie is verlengd' })).toBeVisible();
    await expect(page.getByLabel('Herhaal wachtwoord')).toHaveValue('nog-niet-opgeslagen');
    await expect(page.getByLabel('Herhaal wachtwoord')).toBeFocused();
});

test('Escape with a half typed password asks to discard, and a dismissed dialog comes back', async ({ page }) => {
    const user = createUser();
    await page.clock.install();
    await signIn(page, user.email, user.password);

    await page.goto('/account');
    leaveSeconds(user.email, 630);
    await page.reload();
    await page.clock.fastForward(60_000);
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();

    await dialog.getByLabel('Wachtwoord', { exact: true }).fill('half');
    await page.keyboard.press('Escape');
    await expect(dialog.getByText('Wijzigingen weggooien?')).toBeVisible();
    await dialog.getByRole('button', { name: 'Verder bewerken' }).click();
    await expect(dialog).toBeVisible();
    await expect(dialog.getByLabel('Wachtwoord', { exact: true })).toHaveValue('half');

    await page.keyboard.press('Escape');
    await dialog.getByRole('button', { name: 'Weggooien' }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Mijn account' })).toBeVisible();

    await page.clock.fastForward(60_000);

    await expect(page.getByRole('dialog')).toBeVisible();
});
