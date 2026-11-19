import { expect, expectNoAxeViolations, test } from '../support/guards';

test.use({ baseURL: process.env.E2E_HARNESS_URL ?? 'http://127.0.0.1:4187' });

test('archived rows are hidden by default', async ({ page }) => {
    await page.goto('/?demo=List');

    await expect(page.getByRole('status')).toHaveText('12 resultaten');
    await expect(page.getByRole('button', { name: 'Persoon N', exact: true })).toBeHidden();
    await expect(page.getByText('Gearchiveerd', { exact: true })).toBeHidden();
});

test('Toon gearchiveerd shows them marked with text and a Terugzetten button', async ({ page }) => {
    await page.goto('/?demo=List');

    await page.getByLabel('Toon gearchiveerd').check();

    await expect(page.getByRole('status')).toHaveText('13 resultaten');
    const row = page.getByRole('row', { name: /Persoon N/ });
    await expect(row.getByText('Gearchiveerd', { exact: true })).toBeVisible();
    await expect(row.getByRole('button', { name: 'Terugzetten Persoon N' })).toBeVisible();
    await expectNoAxeViolations(page);
});

test('Terugzetten returns the row to the default list', async ({ page }) => {
    await page.goto('/?demo=List');
    await page.getByLabel('Toon gearchiveerd').check();

    await page.getByRole('button', { name: 'Terugzetten Persoon N' }).click();
    await page.getByLabel('Toon gearchiveerd').uncheck();

    await expect(page.getByRole('status')).toHaveText('13 resultaten');
    await expect(page.getByRole('button', { name: 'Persoon N', exact: true })).toBeVisible();
    await expect(page.getByText('Gearchiveerd', { exact: true })).toBeHidden();
});
