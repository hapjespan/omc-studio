import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('"Toon gearchiveerd" lists archived rows, restores them and shows the refusal reason', async ({ page }) => {
    const canvas = seedEntryCanvas(
        'primary_activities',
        [['domain', 'Opvang'], ['activity', 'Spelen', 'Opvang'], ['activity', 'Slapen', 'Opvang']],
        "$e['Spelen']->update(['archived_at' => now()]); $e['Opvang']->update(['archived_at' => now()]); $e['Slapen']->update(['archived_at' => now()]);",
    );
    await openEntry(page, canvas);

    await expect(page.getByTestId('entry-row')).toHaveCount(0);
    await page.getByLabel('Toon gearchiveerd').check();
    await expect(page.getByTestId('entry-row').filter({ hasText: 'Gearchiveerd' })).toHaveCount(3);

    await page.getByRole('button', { name: 'Spelen terugzetten' }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'gearchiveerd' })).toBeVisible();

    await page.getByRole('button', { name: 'Opvang terugzetten' }).click();
    await expect(page.getByTestId('entry-row').filter({ hasText: 'Opvang' })).not.toContainText('Gearchiveerd');
    await expectNoAxeViolations(page);
});
