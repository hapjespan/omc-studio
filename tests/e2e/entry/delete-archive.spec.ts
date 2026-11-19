import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

const seed = () => seedEntryCanvas('primary_activities', [['domain', 'Inkoop'], ['cluster', 'Contracten', 'Inkoop'], ['activity', 'Offerte', 'Contracten'], ['activity', 'Werving', 'Inkoop']]);

test('Verwijderen names the children, then Ongedaan maken brings the Activity back', async ({ page }) => {
    await openEntry(page, seed());
    await page.getByRole('button', { name: /Werving/ }).first().click();
    await page.getByRole('complementary').getByRole('button', { name: 'Verwijderen' }).click();
    await expect(page.getByRole('dialog')).toContainText('Werving verwijderen?');
    await page.getByRole('dialog').getByRole('button', { name: 'Verwijderen' }).click();

    const undo = page.getByRole('button', { name: 'Ongedaan maken' });
    await expect(undo).toBeFocused();
    await expectNoAxeViolations(page);
    await undo.press('Enter');
    await expect(page.getByTestId('entry-row').filter({ hasText: 'Werving' })).toBeVisible();
});

test('a Cluster with Activities asks what happens to them and Archiveren names the subtree', async ({ page }) => {
    await openEntry(page, seed());
    await page.getByRole('button', { name: /Contracten/ }).first().click();
    const panel = page.getByRole('complementary');
    await panel.getByRole('button', { name: 'Archiveren' }).click();
    await expect(page.getByRole('dialog')).toContainText('Dit archiveert ook: Offerte');
    await page.getByRole('dialog').getByRole('button', { name: 'Annuleren' }).click();

    await panel.getByRole('button', { name: 'Verwijderen' }).click();
    await expect(page.getByRole('dialog')).toContainText('Offerte');
    await page.getByRole('dialog').getByRole('button', { name: 'Activiteiten terugzetten onder het domein' }).click();
    await expect(page.getByTestId('entry-row').filter({ hasText: 'Offerte' })).toBeVisible();
    await expect(page.getByTestId('entry-row').filter({ hasText: 'Contracten' })).toHaveCount(0);
});
