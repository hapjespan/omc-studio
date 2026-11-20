import { expect, test } from './support/guards';
import { tinker } from './support/overview';
import { createAccount, signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

test('every mode has its own page title and the sign-in page has one', async ({ page }) => {
    await page.goto('/sign-in');
    await expect(page).toHaveTitle(/Inloggen/);

    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    const titles: string[] = [];
    for (const [mode, label] of [['entry', 'Invoeren'], ['drawing', 'Tekenen'], ['view', 'Bekijken']]) {
        await page.goto(`/canvases/${canvas.id}/${mode}`);
        await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
        await expect(page).toHaveTitle(new RegExp(`${canvas.name} - ${label}`));
        titles.push(await page.title());
    }
    expect(new Set(titles).size).toBe(3);

    await page.context().clearCookies();
    const account = createAccount('reader');
    tinker(`App\\Models\\User::where('email', '${account.email}')->update(['must_change_password' => true]);`);
    await page.goto('/sign-in');
    await page.getByLabel('E-mailadres').fill(account.email);
    await page.getByLabel('Wachtwoord').fill(account.password);
    await page.getByRole('button', { name: 'Inloggen' }).click();
    await page.waitForURL((url) => url.pathname === '/password/new');
    await expect(page).toHaveTitle(/Nieuw wachtwoord kiezen/);
});
