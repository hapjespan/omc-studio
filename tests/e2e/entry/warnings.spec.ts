import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('warnings are callouts with text, empty Clusters are marked "leeg", and activating a warning selects the Element', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Opvang'], ['cluster', 'Peuters', 'Opvang'], ['activity', 'Spelen', 'Opvang'], ['domain', 'Facilitair']]);
    await openEntry(page, canvas);

    await expect(page.getByRole('heading', { name: 'Waarschuwingen (2)' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Domein zonder activiteiten: Facilitair' })).toBeVisible();
    await expect(page.getByTestId('entry-row').filter({ hasText: 'Peuters' })).toContainText('leeg');

    await page.getByRole('button', { name: 'Leeg cluster: Peuters' }).click();
    await expect(page.getByRole('complementary').getByLabel('Naam')).toHaveValue('Peuters');
    await expectNoAxeViolations(page);
});
