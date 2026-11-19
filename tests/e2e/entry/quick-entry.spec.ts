import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test('the preview marks lines new or existing and Opslaan saves them all', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Inkoop']]);
    await openEntry(page, canvas);

    await page.getByRole('button', { name: 'Snel invoeren' }).click();
    const field = page.getByLabel('Regels');
    await expect(field).toBeFocused();
    await expect(field).toHaveAccessibleDescription(/Escape en daarna op Tab/);
    await field.fill('Inkoop\n  Werving\n    Offreren\nVerkoop');

    const preview = page.getByRole('list', { name: 'Voorbeeld' }).getByTestId('quick-entry-line');
    await expect(preview).toHaveCount(4);
    await expect(preview.nth(0)).toContainText('bestaat al');
    await expect(preview.nth(1)).toContainText('nieuw');
    await expectNoAxeViolations(page);

    await page.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByRole('button', { name: 'Snel invoeren' })).toBeFocused();
    const rows = page.getByRole('list', { name: 'Structuur van Primaire activiteiten' }).getByTestId('entry-row');
    await expect(rows.filter({ hasText: 'Verkoop' })).toHaveCount(1);
    await expect(rows.filter({ hasText: 'Offreren' })).toHaveCount(1);
});

test('line errors are listed with their line number, link to the line and disable Opslaan', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities');
    await openEntry(page, canvas);

    await page.getByRole('button', { name: 'Snel invoeren' }).click();
    await page.getByLabel('Regels').fill('Inkoop\nVerkoop\n    Diep');

    const error = page.getByRole('link', { name: 'Regel 3: inspringing past niet bij de regel erboven' });
    await expect(error).toBeVisible();
    await expect(page.getByRole('button', { name: 'Opslaan' })).toBeDisabled();

    await error.click();
    await expect(page.getByLabel('Regels')).toBeFocused();
    expect(await page.getByLabel('Regels').evaluate((element: HTMLTextAreaElement) => element.value.slice(element.selectionStart, element.selectionEnd))).toBe('    Diep');
    await expectNoAxeViolations(page);
});

test('Tab indents in the text area and Escape then Tab leaves it', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities');
    await openEntry(page, canvas);

    await page.getByRole('button', { name: 'Snel invoeren' }).click();
    const field = page.getByLabel('Regels');
    await field.fill('Inkoop\n');
    await field.press('Tab');
    await field.pressSequentially('Offreren');
    await expect(field).toHaveValue('Inkoop\n  Offreren');

    await field.press('Escape');
    await field.press('Tab');
    await expect(field).not.toBeFocused();
});

test('a list Area refuses two indents', async ({ page }) => {
    const canvas = seedEntryCanvas('actors');
    await openEntry(page, canvas, 'Actoren');

    await page.getByRole('button', { name: 'Snel invoeren' }).click();
    await page.getByLabel('Regels').fill('Leveranciers\n  Drukkerij\n    Papier');

    await expect(page.getByText('Regel 3: in deze area bestaan geen clusters')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Opslaan' })).toBeDisabled();
});

test('Tab with a selection indents the selected lines and keeps the text, also after reopening', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities');
    await openEntry(page, canvas);

    await page.getByRole('button', { name: 'Snel invoeren' }).click();
    await page.getByRole('button', { name: 'Sluiten' }).click();
    await page.getByRole('button', { name: 'Snel invoeren' }).click();
    const field = page.getByLabel('Regels');
    await expect(field).toBeFocused();
    await field.fill('Inkoop\nOfferte\nBestellen');
    await field.evaluate((element: HTMLTextAreaElement) => element.setSelectionRange(7, 24));
    await field.press('Tab');
    await expect(field).toHaveValue('Inkoop\n  Offerte\n  Bestellen');
});
