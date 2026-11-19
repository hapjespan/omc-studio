import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('the arrows swap neighbours of the same kind and keep focus sensible', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Inkoop'], ['activity', 'Werving', 'Inkoop'], ['activity', 'Selectie', 'Inkoop'], ['cluster', 'Contracten', 'Inkoop']]);
    await openEntry(page, canvas);

    const rows = page.getByRole('list', { name: 'Structuur van Primaire activiteiten' }).getByTestId('entry-row');
    await expect(page.getByRole('button', { name: 'Werving omhoog' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Selectie omlaag' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Contracten omhoog' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Contracten omlaag' })).toBeDisabled();

    await page.getByRole('button', { name: 'Selectie omhoog' }).focus();
    await page.keyboard.press('Enter');
    await expect(rows.nth(1)).toContainText('Selectie');
    await expect(rows.nth(2)).toContainText('Werving');
    await expect(page.getByRole('button', { name: 'Selectie omhoog' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Selectie omlaag' })).toBeFocused();

    await page.getByRole('button', { name: 'Selectie omlaag' }).press('Enter');
    await expect(rows.nth(1)).toContainText('Werving');
    await expect(page.getByRole('button', { name: 'Selectie omlaag' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Selectie omhoog' })).toBeFocused();
    await expectNoAxeViolations(page);
});
