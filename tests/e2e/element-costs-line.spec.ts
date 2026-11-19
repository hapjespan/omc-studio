import { expect, expectNoAxeViolations, test } from './support/guards';
import { allocationCode, openEntry, seedEntryCanvas } from './support/entry';

const structure = [['domain', 'Inkoop'], ['activity', 'Offreren', 'Inkoop'], ['activity', 'Bestellen', 'Inkoop']] as Parameters<typeof seedEntryCanvas>[1];

test('an allocated Activity and Domain name the amount, the count and that Herverdelen is needed', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', structure, allocationCode({ year: 2026, records: [['Licentie', 100_000, { Offreren: 3000, Bestellen: 7000 }], ['Hosting', 20_000, { Offreren: 10_000 }]] }));
    await openEntry(page, canvas);

    await page.getByRole('button', { name: /Bestellen/ }).first().click();
    const panel = page.getByRole('complementary');
    await expect(panel.getByTestId('element-costs')).toContainText('€ 700,00 toegewezen in 2026, verdeeld over 1 kostenpost. Herverdelen is nodig voor archiveren of verwijderen.');

    await page.getByRole('button', { name: /Inkoop/ }).first().click();
    await expect(panel.getByTestId('element-costs')).toContainText('€ 1.200,00 toegewezen in 2026, verdeeld over 2 kostenposten.');
    await expectNoAxeViolations(page);
});

test('lines in another year only are named in the costs line', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', structure, `${allocationCode({ year: 2026, records: [['Licentie', 100_000, { Bestellen: 10_000 }]] })} ${allocationCode({ year: 2025, records: [['Oud', 50_000, { Offreren: 10_000 }]] })}`);
    await openEntry(page, canvas);

    await page.getByRole('button', { name: /Offreren/ }).first().click();
    const panel = page.getByRole('complementary');
    await expect(panel.getByTestId('element-costs')).toContainText('Geen kosten toegewezen in 2026, wel in 2025.');
    await expectNoAxeViolations(page);
});

test('an Element without lines shows the direct-deletion line', async ({ page }) => {
    const empty = seedEntryCanvas('primary_activities', structure, allocationCode({ year: 2026, records: [['Licentie', 100_000, { Bestellen: 10_000 }]] }));
    await openEntry(page, empty);
    await page.getByRole('button', { name: /Offreren/ }).first().click();
    const panel = page.getByRole('complementary');
    await expect(panel.getByTestId('element-costs')).toHaveText('Geen kosten toegewezen in 2026. Verwijderen kan direct.');
    await expectNoAxeViolations(page);
});
