import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('Adaptive with a rationale shows the mark, and Geen asks to confirm before clearing both', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Inkoop'], ['activity', 'Werving', 'Inkoop']]);
    await openEntry(page, canvas);

    await page.getByRole('button', { name: /Werving/ }).first().click();
    const panel = page.getByRole('complementary');
    await expect(panel.getByLabel('Onderbouwing')).toHaveCount(0);

    await panel.getByRole('button', { name: /Adaptive/ }).click();
    await panel.getByLabel('Onderbouwing').fill('Verandert vaak');
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Alles opgeslagen' })).toBeVisible();
    await expect(page.getByTestId('entry-row').filter({ hasText: 'Werving' }).getByTestId('modality-mark')).toHaveText(/A/);

    await panel.getByRole('button', { name: 'Geen' }).click();
    await expect(panel.getByRole('alert').filter({ hasText: 'onderbouwing' })).toBeVisible();
    await panel.getByRole('button', { name: 'Nee, laat staan' }).click();
    await expect(panel.getByLabel('Onderbouwing')).toHaveValue('Verandert vaak');

    await panel.getByRole('button', { name: 'Geen' }).click();
    await panel.getByRole('button', { name: 'Ja, verwijder de onderbouwing' }).click();
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByTestId('entry-row').filter({ hasText: 'Werving' }).getByTestId('modality-mark')).toHaveCount(0);
    await expect(panel.getByLabel('Onderbouwing')).toHaveCount(0);
    await expectNoAxeViolations(page);
});

test('a list area element has no modality field', async ({ page }) => {
    const canvas = seedEntryCanvas('actors', [['domain', 'Klanten']]);
    await openEntry(page, canvas, 'Actoren');

    await page.getByRole('button', { name: /Klanten/ }).first().click();
    const panel = page.getByRole('complementary');
    await expect(panel.getByRole('group', { name: 'Modaliteit' })).toHaveCount(0);
});
