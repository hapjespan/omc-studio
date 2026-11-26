import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('Enter in "+ Domein" adds a Domain row at the end and moves focus to its add line', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Verkoop']]);
    await openEntry(page, canvas);

    await page.getByRole('button', { name: 'Domein', exact: true }).click();
    const field = page.getByLabel('+ Domein');
    await field.fill('Inkoop');
    await field.press('Enter');

    const rows = page.getByRole('list', { name: 'Structuur van Primaire activiteiten' }).getByTestId('entry-row');
    await expect(rows).toHaveCount(2);
    await expect(rows.nth(1)).toContainText('Inkoop');
    await expect(page.getByLabel(/toevoegen aan Inkoop/)).toBeFocused();
    await expect(field).toHaveValue('');
});

test('a refused name shows its message next to the field', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Verkoop']]);
    await openEntry(page, canvas);

    await page.getByRole('button', { name: 'Domein', exact: true }).click();
    const field = page.getByLabel('+ Domein');
    await field.fill('Verkoop');
    await field.press('Enter');
    await expect(page.getByText(/staat al een element/)).toBeVisible();
    await field.fill('');
    await field.press('Enter');
    await expect(field).toHaveAccessibleDescription(/naam/i);
    await expectNoAxeViolations(page);
});
