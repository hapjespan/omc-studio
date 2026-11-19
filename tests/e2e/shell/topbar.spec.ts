import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';

test('the application name links to the overview', async ({ page }) => {
    await signInAs(page, 'administrator');
    await page.goto('/account');
    await page.getByRole('link', { name: 'OMC Studio' }).click();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Overzicht' })).toBeVisible();
});

test('the role menu opens with the keyboard and holds Mijn account and Uitloggen', async ({ page }) => {
    await signInAs(page, 'reader');
    const trigger = page.getByRole('button', { name: 'Lezer' });
    await trigger.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('menuitem')).toHaveText(['Mijn account', 'Uitloggen']);
    await expectNoAxeViolations(page);

    await page.keyboard.press('Escape');
    await expect(page.getByRole('menuitem')).toHaveCount(0);
    await expect(trigger).toBeFocused();

    await page.keyboard.press('Enter');
    await page.getByRole('menuitem', { name: 'Mijn account' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Mijn account' })).toBeVisible();
});

test('the Administrator is shown as the role', async ({ page }) => {
    await signInAs(page, 'administrator');
    await expect(page.getByRole('button', { name: 'Administrator' })).toBeVisible();
});
