import type { Page } from '@playwright/test';
import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

const rowsOf = (page: Page) => page.getByRole('list', { name: 'Structuur van Primaire activiteiten' }).getByTestId('entry-row');

test('Enter adds an Activity at the end, empties the line and keeps focus', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Inkoop'], ['activity', 'Werving', 'Inkoop']]);
    await openEntry(page, canvas);

    const line = page.getByLabel('Activiteit of cluster toevoegen aan Inkoop, Enter om op te slaan');
    await line.fill('Selectie');
    await line.press('Enter');

    await expect(rowsOf(page)).toHaveCount(3);
    await expect(rowsOf(page).nth(2)).toContainText('Selectie');
    await expect(rowsOf(page).nth(2)).toContainText('Activiteit');
    await expect(line).toHaveValue('');
    await expect(line).toBeFocused();
    await expectNoAxeViolations(page);
});

test('with the choice "Cluster" the line adds a Cluster', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Inkoop']]);
    await openEntry(page, canvas);

    await page.getByLabel('Cluster', { exact: true }).check();
    const line = page.getByLabel('Activiteit of cluster toevoegen aan Inkoop, Enter om op te slaan');
    await line.fill('Contracten');
    await line.press('Enter');

    await expect(rowsOf(page).nth(1)).toContainText('Cluster');
    await expect(rowsOf(page).nth(1)).toContainText('Contracten');
});
