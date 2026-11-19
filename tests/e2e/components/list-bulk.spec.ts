import { expect, expectNoAxeViolations, test } from '../support/guards';

test.use({ baseURL: process.env.E2E_HARNESS_URL ?? 'http://127.0.0.1:4187' });

test('checkboxes are labelled with the row name and select-all selects every row', async ({ page }) => {
    await page.goto('/?demo=List');
    await expect(page.getByRole('checkbox', { name: 'Selecteer Anna Zorg' })).toBeVisible();
    await expectNoAxeViolations(page);

    await page.getByRole('checkbox', { name: 'Alle rijen selecteren' }).check();

    await expect(page.getByText('12 geselecteerd')).toBeVisible();
    await expect(page.getByRole('checkbox', { name: 'Selecteer Persoon C' })).toBeChecked();
});

test('each row follows its own rules and the result names every skipped row', async ({ page }) => {
    await page.goto('/?demo=List');
    for (const name of ['Selecteer Persoon C', 'Selecteer Persoon D', 'Selecteer Persoon E']) await page.getByRole('checkbox', { name }).check();

    await page.getByRole('button', { name: 'Verwijderen', exact: true }).click();

    await expect(page.getByText('2 verwijderd, 1 overgeslagen')).toBeVisible();
    await expect(page.getByText('Persoon D: Is nog in gebruik')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Persoon C', exact: true })).toBeHidden();
    await expect(page.getByRole('button', { name: 'Persoon D', exact: true })).toBeVisible();
});

test('one undo restores every row the bulk deletion removed', async ({ page }) => {
    await page.goto('/?demo=List&undo=1');
    for (const name of ['Selecteer Persoon C', 'Selecteer Persoon D', 'Selecteer Persoon E']) await page.getByRole('checkbox', { name }).check();
    await page.getByRole('button', { name: 'Verwijderen', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Persoon C', exact: true })).toBeHidden();

    await page.getByRole('button', { name: 'Ongedaan maken' }).click();

    await expect(page.getByRole('button', { name: 'Persoon C', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Persoon E', exact: true })).toBeVisible();
});
