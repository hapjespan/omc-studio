import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('the independent test of Invoeren runs with the keyboard only', async ({ page }) => {
    await openEntry(page, seedEntryCanvas('primary_activities'));
    const rows = page.getByTestId('entry-row');

    await page.getByRole('button', { name: 'Domein', exact: true }).click();
    const domain = page.getByLabel('+ Domein');
    await domain.focus();
    await page.keyboard.type('Inkoop');
    await page.keyboard.press('Enter');
    const line = page.getByLabel('Activiteit of cluster toevoegen aan Inkoop, Enter om op te slaan');
    await expect(line).toBeFocused();

    for (const name of ['Werving', 'Selectie', 'Offerte']) {
        await page.keyboard.type(name);
        await page.keyboard.press('Enter');
        await expect(rows.filter({ hasText: name })).toBeVisible();
        await expect(line).toBeFocused();
    }

    await page.keyboard.press('Tab');
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.type('Contracten');
    await page.keyboard.press('Enter');
    await expect(rows.filter({ hasText: 'Cluster' })).toContainText('Contracten');

    await page.getByRole('button', { name: /Werving/ }).first().focus();
    await page.keyboard.press('Enter');
    const parent = page.getByLabel('Hangt onder');
    await parent.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('option').first()).toBeFocused();
    const cluster = page.getByRole('option', { name: /Contracten/ });
    await expect(async () => {
        await page.keyboard.press('ArrowDown');
        await expect(cluster).toBeFocused({ timeout: 500 });
    }).toPass();
    await page.keyboard.press('Enter');
    await expect(parent).toContainText('Contracten');
    await page.getByRole('button', { name: 'Opslaan', exact: true }).focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('complementary').getByText('Activiteit · Primaire activiteiten › Inkoop › Contracten › Werving')).toBeVisible();
    await page.keyboard.press('Escape');

    await page.getByRole('button', { name: 'Offerte omhoog' }).focus();
    await page.keyboard.press('Enter');
    await expect(rows.nth(1)).toContainText('Offerte');
    await expect(rows.nth(2)).toContainText('Selectie');
    await expectNoAxeViolations(page);
});
