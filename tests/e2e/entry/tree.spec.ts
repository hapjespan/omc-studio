import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('the tree shows kind, name and Modality letter with Activities before Clusters, in reading order', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Inkoop'], ['cluster', 'Contracten', 'Inkoop'], ['activity', 'Werving', 'Inkoop'], ['activity', 'Offerte', 'Contracten']], "$e['Werving']->update(['modality_id' => App\\Models\\Modality::where('key', 'adaptive')->value('id')]);");
    await openEntry(page, canvas);

    const tree = page.getByRole('list', { name: 'Structuur van Primaire activiteiten' });
    const rows = tree.getByRole('button');
    await expect(rows).toHaveCount(4);
    await expect(rows.nth(0)).toContainText('Domein');
    await expect(rows.nth(1)).toContainText('Activiteit');
    await expect(rows.nth(1)).toContainText('Werving');
    await expect(rows.nth(1)).toContainText('A');
    await expect(rows.nth(2)).toContainText('Cluster');
    await expect(rows.nth(3)).toContainText('Offerte');

    await rows.nth(0).focus();
    await page.keyboard.press('Tab');
    await expect(rows.nth(1)).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(rows.nth(2)).toBeFocused();
    await expectNoAxeViolations(page);
});
