import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

const seed = (): number => seedEntryCanvas('primary_activities', [['domain', 'Opvang'], ['cluster', 'Peuters', 'Opvang'], ['activity', 'Spelen', 'Opvang'], ['domain', 'Facilitair']]);
const rowButton = (page: import('@playwright/test').Page, name: string) => page.getByTestId('entry-row').filter({ hasText: name }).getByRole('button').first();

test('"Domein" opens the field and focuses it, Escape closes it and returns focus to the button', async ({ page }) => {
    await openEntry(page, seed());
    const opener = page.getByRole('button', { name: 'Domein', exact: true });

    await expect(page.getByLabel('+ Domein')).toHaveCount(0);
    await opener.click();
    await expect(page.getByLabel('+ Domein')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.getByLabel('+ Domein')).toHaveCount(0);
    await expect(opener).toBeFocused();
});

test('warnings render in the detail panel, and a warning in the main column returns focus to its row on close', async ({ page }) => {
    await openEntry(page, seed());

    await page.getByRole('button', { name: 'Domein zonder activiteiten: Facilitair' }).click();
    const panel = page.getByRole('complementary');
    await expect(panel.getByRole('heading', { name: 'Waarschuwingen (2)' })).toBeVisible();
    await expect(page.getByRole('main').getByRole('heading', { name: 'Waarschuwingen (2)' })).toHaveCount(0);
    await panel.getByRole('button', { name: 'Sluiten' }).click();
    await expect(rowButton(page, 'Facilitair')).toBeFocused();
    await expectNoAxeViolations(page);
});

test('a warning activated inside the panel moves focus to the new title and close returns it to its row', async ({ page }) => {
    await openEntry(page, seed());

    await rowButton(page, 'Facilitair').click();
    const panel = page.getByRole('complementary');
    await panel.getByRole('button', { name: 'Leeg cluster: Peuters' }).click();
    await expect(panel.getByRole('heading', { name: 'Peuters', level: 2 })).toBeFocused();
    await panel.getByRole('button', { name: 'Sluiten' }).click();
    await expect(rowButton(page, 'Peuters')).toBeFocused();
});

test('changing the Area closes a clean panel', async ({ page }) => {
    await openEntry(page, seed());

    await rowButton(page, 'Facilitair').click();
    await expect(page.getByRole('complementary')).toBeVisible();
    await page.getByRole('navigation', { name: 'Areas' }).getByRole('button', { name: /Koers/ }).click();
    await expect(page.getByRole('complementary')).toBeHidden();
    await expect(page.getByRole('status').filter({ hasText: 'Alles opgeslagen' })).toHaveCount(0);
});
