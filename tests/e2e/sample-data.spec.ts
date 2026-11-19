import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';

test('the sample Organisation is removed after one confirmation and the page says it will not return', async ({ page }) => {
    await signInAs(page, 'administrator');
    await page.goto('/admin');
    await page.getByRole('link', { name: 'Voorbeelddata' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Voorbeelddata' })).toBeVisible();
    await expectNoAxeViolations(page);

    await page.getByRole('button', { name: 'Voorbeelddata verwijderen' }).click();
    const dialog = page.getByRole('dialog', { name: 'Voorbeelddata verwijderen?' });
    await expect(dialog).toBeVisible();
    await expectNoAxeViolations(page);
    await dialog.getByRole('button', { name: 'Voorbeelddata verwijderen' }).click();

    await expect(page.getByText('De voorbeelddata is verwijderd en komt niet terug.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Voorbeelddata verwijderen' })).toBeHidden();
});

test('a Reader has no access to the sample data page', async ({ page }) => {
    await signInAs(page, 'reader');
    const response = await page.goto('/admin/sample-data');
    expect(response?.status()).toBe(403);
});
