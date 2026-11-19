import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('the Unknown allocation Domain ends Supporting activities as a system row with an explanation only', async ({ page }) => {
    const canvas = seedEntryCanvas('supporting_activities', [['domain', 'Facilitair']], 'App\\Services\\Canvas\\UnknownAllocationDomain::ensure($c);');
    await openEntry(page, canvas, 'Ondersteunende activiteiten');

    const rows = page.getByTestId('entry-row');
    await expect(rows.last()).toContainText('Onbekende allocatie');
    await expect(rows.last()).toContainText('Systeem');
    await expect(rows.last().getByRole('button')).toHaveCount(1);
    await expect(page.getByLabel(/toevoegen aan Onbekende allocatie/)).toHaveCount(0);

    await rows.last().getByRole('button').click();
    const panel = page.getByRole('complementary');
    await expect(panel).toContainText('Hier staan kostenposten die in dit canvas nog niet zijn ingedeeld');
    await expect(panel.getByRole('button', { name: /Opslaan|Archiveren|Verwijderen/ })).toHaveCount(0);
    await expectNoAxeViolations(page);
});
