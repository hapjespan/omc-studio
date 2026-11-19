import { expect, expectNoAxeViolations, test } from '../support/guards';

test.use({ baseURL: process.env.E2E_HARNESS_URL ?? 'http://127.0.0.1:4187' });

test('the confirmation names the consequence in numbers', async ({ page }) => {
    await page.goto('/?demo=List');
    await page.getByRole('button', { name: 'Verwijderen Anna Zorg', exact: true }).click();

    const dialog = page.getByRole('dialog', { name: 'Anna Zorg verwijderen?' });
    await expect(dialog).toContainText('Verwijdert ook 4 jaarwaarden en 6 toewijzingssets');
    await expectNoAxeViolations(page);

    await dialog.getByRole('button', { name: 'Verwijderen', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('11 resultaten');
});

test('a row without dependants states that there is no consequence', async ({ page }) => {
    await page.goto('/?demo=List');
    await page.getByRole('button', { name: 'Verwijderen Persoon C', exact: true }).click();

    await expect(page.getByRole('dialog')).toContainText('Dit verwijdert verder niets');
});

test('cancelling keeps the row and returns focus to the delete button', async ({ page }) => {
    await page.goto('/?demo=List');
    const opener = page.getByRole('button', { name: 'Verwijderen Persoon C', exact: true });
    await opener.focus();
    await page.keyboard.press('Enter');

    await page.getByRole('button', { name: 'Annuleren' }).click();

    await expect(page.getByRole('dialog')).toBeHidden();
    await expect(page.getByRole('status')).toHaveText('12 resultaten');
    await expect(opener).toBeFocused();
});

test('the typed-name variant enables the delete button only after the exact name', async ({ page }) => {
    await page.goto('/?demo=List&typed=1');
    await page.getByRole('button', { name: 'Verwijderen Persoon C', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Persoon C verwijderen?' });
    const confirm = dialog.getByRole('button', { name: 'Verwijderen', exact: true });

    await expect(confirm).toBeDisabled();
    await dialog.getByLabel('Naam ter bevestiging').fill('persoon c');
    await expect(confirm).toBeDisabled();
    await dialog.getByLabel('Naam ter bevestiging').fill('Persoon C');
    await expect(confirm).toBeEnabled();

    await confirm.click();
    await expect(page.getByRole('status')).toHaveText('11 resultaten');
});
