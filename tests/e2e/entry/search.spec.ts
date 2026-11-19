import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('"Zoek in deze area" keeps matching rows with their parents', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Opvang'], ['activity', 'Spelen', 'Opvang'], ['activity', 'Slapen', 'Opvang'], ['domain', 'Inkoop']]);
    await openEntry(page, canvas);

    await page.getByLabel('Zoek in deze area').fill('SPEL');
    const rows = page.getByTestId('entry-row');
    await expect(rows).toHaveCount(2);
    await expect(rows.nth(0)).toContainText('Opvang');
    await expect(rows.nth(1)).toContainText('Spelen');
    await expectNoAxeViolations(page);
});
