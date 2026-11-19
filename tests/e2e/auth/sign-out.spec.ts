import { createUser } from '../support/accounts';
import { expect, test } from '../support/guards';

test('Back after signing out shows the sign-in screen, not the previous page', async ({ page }) => {
    const user = createUser();
    await page.goto('/sign-in');
    await page.getByLabel('E-mailadres').fill(user.email);
    await page.getByLabel('Wachtwoord').fill(user.password);
    await page.getByRole('button', { name: 'Inloggen' }).click();
    await expect(page.getByRole('heading', { name: 'Overzicht' })).toBeVisible();

    await page.getByRole('button', { name: 'Uitloggen' }).click();
    await expect(page.getByRole('heading', { name: 'Inloggen' })).toBeVisible();

    await page.goBack();
    await expect(page.getByRole('heading', { name: 'Inloggen' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Overzicht' })).toHaveCount(0);
});
