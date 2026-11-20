import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('mission, vision and goals are written, reordered, deleted with undo and cleared', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities');
    await openEntry(page, canvas, 'Koers');

    await page.getByLabel('Missie', { exact: true }).fill('Samen bouwen');
    await page.getByRole('region', { name: 'Koers' }).getByRole('button', { name: 'Opslaan', exact: true }).first().click();
    await expect(page.getByLabel('Missie', { exact: true })).toHaveValue('Samen bouwen');
    await expect(page.getByText('Nog geen visie')).toBeVisible();

    for (const title of ['Groei', 'Kwaliteit', 'Duurzaam']) {
        await page.getByLabel('Titel', { exact: true }).fill(title);
        await page.getByLabel('Uitleg').last().fill(`Uitleg ${title}`);
        await page.getByRole('button', { name: '+ strategisch doel' }).click();
        await expect(page.getByRole('listitem').filter({ hasText: title })).toBeVisible();
    }

    const goals = page.getByRole('list').last().getByRole('listitem');
    await expect(page.getByRole('button', { name: 'Groei omhoog' })).toBeDisabled();
    await page.getByRole('button', { name: 'Duurzaam omhoog' }).click();
    await expect(goals.nth(1)).toContainText('Duurzaam');

    await page.getByRole('button', { name: 'Kwaliteit verwijderen' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Verwijderen' }).click();
    await expect(goals).toHaveCount(2);
    await page.getByRole('button', { name: 'Ongedaan maken' }).click();
    await expect(goals).toHaveCount(3);
    await expectNoAxeViolations(page);

    await page.getByLabel('Missie', { exact: true }).fill('');
    await page.getByRole('region', { name: 'Koers' }).getByRole('button', { name: 'Opslaan', exact: true }).first().click();
    await expect(page.getByText('Nog geen missie')).toBeVisible();
});

test('a mission over 2000 characters is refused by name', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities');
    await openEntry(page, canvas, 'Koers');

    await page.getByLabel('Missie', { exact: true }).fill('a'.repeat(2001));
    await page.getByRole('region', { name: 'Koers' }).getByRole('button', { name: 'Opslaan', exact: true }).first().click();
    await expect(page.getByText('Missie: gebruik maximaal 2000 tekens')).toBeVisible();
});
