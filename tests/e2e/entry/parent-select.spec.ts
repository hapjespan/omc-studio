import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('"Hangt onder" moves an Activity to the end of a Cluster and never offers the Unknown allocation Domain', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Inkoop'], ['cluster', 'Contracten', 'Inkoop'], ['activity', 'Werving', 'Inkoop']], "App\\Services\\Canvas\\UnknownAllocationDomain::ensure($c);");
    await openEntry(page, canvas);

    await page.getByRole('button', { name: /Werving/ }).first().click();
    const panel = page.getByRole('complementary');
    await panel.getByLabel('Hangt onder').focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('option', { name: 'Primaire activiteiten > Inkoop > Contracten' })).toBeVisible();
    await expect(page.getByRole('option', { name: /Onbekende allocatie/ })).toHaveCount(0);
    await page.getByRole('option', { name: 'Primaire activiteiten > Inkoop > Contracten' }).click();
    await panel.getByRole('button', { name: 'Opslaan', exact: true }).click();

    await expect(panel.getByText('Activiteit · Primaire activiteiten › Inkoop › Contracten')).toBeVisible();
    await expectNoAxeViolations(page);
});
