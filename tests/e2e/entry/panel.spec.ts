import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('the panel saves name and description, shows "Alles opgeslagen" and refuses a bad name beside its field', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Inkoop'], ['activity', 'Werving', 'Inkoop'], ['activity', 'Selectie', 'Inkoop']]);
    await openEntry(page, canvas);

    await page.getByRole('button', { name: /Werving/ }).first().click();
    const panel = page.getByRole('complementary');
    await expect(panel.getByText('Activiteit · Primaire activiteiten › Inkoop › Werving')).toBeVisible();

    await panel.getByLabel('Naam').fill('Selectie');
    await panel.getByRole('button', { name: 'Opslaan', exact: true }).click();
    await expect(panel.getByLabel('Naam')).toHaveAccessibleDescription(/staat al een element/);

    await panel.getByLabel('Naam').fill('Werving nieuw');
    await panel.getByLabel('Omschrijving').fill('Uitleg');
    await panel.getByRole('button', { name: 'Opslaan', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Alles opgeslagen' })).toBeVisible();
    await expect(page.getByTestId('entry-row').filter({ hasText: 'Werving nieuw' })).toBeVisible();
    await expectNoAxeViolations(page);
});
