import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('a list Area has Groups with Items, the Item label and an icon select, and no Modality or Cluster', async ({ page }) => {
    const canvas = seedEntryCanvas('actors', [['domain', 'Klanten'], ['activity', 'Burgers', 'Klanten']]);
    await openEntry(page, canvas, 'Actoren');

    await expect(page.getByLabel('+ Groep')).toBeVisible();
    const line = page.getByLabel('Actor toevoegen aan Klanten, Enter om op te slaan');
    await line.fill('Bedrijven');
    await line.press('Enter');
    await expect(page.getByTestId('entry-row').filter({ hasText: 'Bedrijven' })).toContainText('Actor');
    await expect(page.getByLabel('Cluster', { exact: true })).toHaveCount(0);

    await page.getByRole('button', { name: /Burgers/ }).first().click();
    const panel = page.getByRole('complementary');
    await panel.getByLabel('Icoon').click();
    await page.getByRole('option', { name: 'Mensen' }).click();
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByTestId('entry-row').filter({ hasText: 'Burgers' })).toContainText('Mensen');
    await expect(panel.getByText('Modaliteit')).toHaveCount(0);
    await expectNoAxeViolations(page);
});
