import { expect, test } from '../support/guards';
import { expectNoAxeViolations } from '../support/guards';

test('a wrong password shows the one message in text and moves focus to the field', async ({ page }) => {
    await page.goto('/sign-in');
    await page.getByLabel('E-mailadres').fill('nobody@example.test');
    await page.getByLabel('Wachtwoord').fill('not-the-password');
    await page.getByRole('button', { name: 'Inloggen' }).click();

    await expect(page.getByRole('link', { name: 'E-mailadres of wachtwoord is onjuist' })).toBeVisible();
    await expect(page.getByLabel('E-mailadres')).toBeFocused();
    await expectNoAxeViolations(page);
});

test('the fields carry the autocomplete attributes of a password manager', async ({ page }) => {
    await page.goto('/sign-in');

    await expect(page.getByLabel('E-mailadres')).toHaveAttribute('autocomplete', 'username');
    await expect(page.getByLabel('Wachtwoord')).toHaveAttribute('autocomplete', 'current-password');
});
