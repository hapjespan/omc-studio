import { expect, expectNoAxeViolations, test } from '../support/guards';

test.use({ baseURL: 'http://127.0.0.1:4187' });

test('selecting a row opens the detail panel with focus inside and closing returns focus to the row', async ({ page }) => {
    await page.goto('/?demo=Shell');
    const row = page.getByRole('button', { name: 'Tweede rij' });
    await row.focus();
    await page.keyboard.press('Enter');

    const panel = page.getByRole('complementary', { name: 'Tweede rij' });
    await expect(panel).toBeVisible();
    await expect(page.getByRole('heading', { level: 2, name: 'Tweede rij' })).toBeFocused();
    await expectNoAxeViolations(page);

    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(row).toBeFocused();
});

test('the Sluiten button closes the panel and returns focus', async ({ page }) => {
    await page.goto('/?demo=Shell');
    const row = page.getByRole('button', { name: 'Eerste rij' });
    await row.click();
    await page.getByRole('button', { name: 'Sluiten' }).click();
    await expect(page.getByRole('complementary', { name: 'Eerste rij' })).toBeHidden();
    await expect(row).toBeFocused();
});

test('Escape with unsaved input asks to discard and keeps the panel open until confirmed', async ({ page }) => {
    await page.goto('/?demo=Shell');
    const row = page.getByRole('button', { name: 'Eerste rij' });
    await row.click();
    await page.locator('#note').fill('wijziging');
    await page.keyboard.press('Escape');

    await expect(page.getByRole('alertdialog')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Verder bewerken' })).toBeFocused();
    await expect(page.locator('#detail-panel')).toBeVisible();

    await page.getByRole('button', { name: 'Weggooien' }).click();
    await expect(page.locator('#detail-panel')).toBeHidden();
    await expect(row).toBeFocused();
});
