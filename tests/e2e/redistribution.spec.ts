import { expect, expectNoAxeViolations, test } from './support/guards';
import { allocationCode, openEntry, seedEntryCanvas } from './support/entry';
import { seedPhp } from './support/seed';

const structure = [['domain', 'Inkoop'], ['activity', 'Offreren', 'Inkoop'], ['activity', 'Bestellen', 'Inkoop'], ['activity', 'Betalen', 'Inkoop']] as Parameters<typeof seedEntryCanvas>[1];
const lines = (canvas: number): string => seedPhp(`echo 'LINES=' . json_encode(App\\Models\\AllocationLine::query()->join('allocation_sets', 'allocation_sets.id', '=', 'allocation_lines.allocation_set_id')->where('allocation_sets.canvas_id', ${canvas})->join('elements', 'elements.id', '=', 'allocation_lines.element_id')->join('fiscal_years', 'fiscal_years.id', '=', 'allocation_sets.fiscal_year_id')->orderBy('fiscal_years.year')->get(['fiscal_years.year', 'elements.name', 'allocation_lines.basis_points'])->map(fn ($l) => [$l->year, $l->name, $l->basis_points])->all());`);
const seed = (extra = ''): number => seedEntryCanvas('primary_activities', structure, `${allocationCode({ year: 2025, records: [['Oud', 50_000, { Offreren: 6000, Bestellen: 4000 }]] })} ${allocationCode({ year: 2026, records: [['Licentie', 100_000, { Offreren: 10_000 }], ['Hosting', 20_000, { Offreren: 3000, Bestellen: 7000 }]] })} ${extra}`);

test('Verwijderen on an allocated Activity opens Herverdelen with a choice per year and archives it in one step', async ({ page }) => {
    const canvas = seed();
    await openEntry(page, canvas);
    await page.getByRole('button', { name: /Offreren/ }).first().click();
    await page.getByRole('complementary').getByRole('button', { name: 'Verwijderen' }).click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('Offreren herverdelen');
    const first = dialog.getByRole('group', { name: /2025: 1 kostenpost/ });
    const second = dialog.getByRole('group', { name: /2026: 2 kostenposten/ });
    await expect(first).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Herverdelen en archiveren' })).toBeDisabled();

    await first.getByRole('radio', { name: 'Overzetten naar ander doel' }).check();
    await expect(first.getByRole('button', { name: 'Kiezen: Offreren' })).toHaveCount(0);
    await first.getByRole('button', { name: 'Kiezen: Betalen' }).click();
    await expect(first).toContainText('Gekozen doel: Inkoop > Betalen');
    await second.getByRole('radio', { name: 'Terug naar onbekende allocatie' }).check();
    await expectNoAxeViolations(page);

    await dialog.getByRole('button', { name: 'Herverdelen en archiveren' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(page.getByTestId('entry-row').filter({ hasText: 'Offreren' })).toHaveCount(0);
    const stored = JSON.parse(/LINES=(.*)/.exec(lines(canvas))?.[1] ?? '[]') as [number, string, number][];
    expect(stored.sort((a, b) => a[1].localeCompare(b[1]))).toEqual([[2025, 'Bestellen', 4000], [2025, 'Betalen', 6000]]);
});

test('Archiveren opens the same dialog and the Target tree and radios work with the keyboard only', async ({ page }) => {
    const canvas = seed();
    await openEntry(page, canvas);
    await page.getByRole('button', { name: /Bestellen/ }).first().click();
    await page.getByRole('complementary').getByRole('button', { name: 'Archiveren' }).click();

    const dialog = page.getByRole('dialog');
    const first = dialog.getByRole('group', { name: /2025/ });
    await first.getByRole('radio', { name: 'Overzetten naar ander doel' }).focus();
    await page.keyboard.press('Space');
    await first.getByRole('button', { name: 'Kiezen: Betalen' }).focus();
    await page.keyboard.press('Enter');
    await expect(first.getByRole('button', { name: 'Kiezen: Betalen' })).toHaveAttribute('aria-pressed', 'true');
    const second = dialog.getByRole('group', { name: /2026/ });
    await second.getByRole('radio', { name: 'Terug naar onbekende allocatie' }).focus();
    await page.keyboard.press('Space');
    await dialog.getByRole('button', { name: 'Herverdelen en archiveren' }).focus();
    await page.keyboard.press('Enter');
    await expect(dialog).toHaveCount(0);
    expect(lines(canvas)).toContain('Betalen');
});

test('a Finalised year refuses Herverdelen inside the dialog', async ({ page }) => {
    const canvas = seed("App\\Models\\FinalisedYear::create(['canvas_id' => $c->id, 'fiscal_year_id' => $f2026->id, 'finalised_at' => now()]);");
    await openEntry(page, canvas);
    await page.getByRole('button', { name: /Offreren/ }).first().click();
    await page.getByRole('complementary').getByRole('button', { name: 'Archiveren' }).click();

    const dialog = page.getByRole('dialog');
    await expect(dialog.getByRole('alert')).toContainText(/2026 is definitief.*Heropen het jaar eerst/);
    await expect(dialog.getByRole('button', { name: 'Herverdelen en archiveren' })).toBeDisabled();
    await expectNoAxeViolations(page);
});

test('stale props show an explained message with Opnieuw laden', async ({ page }) => {
    const target = seedEntryCanvas('primary_activities', structure, allocationCode({ year: 2026, records: [['Verlopen', 10_000, { Betalen: 10_000 }]] }));
    await openEntry(page, target);
    await page.getByRole('button', { name: /Betalen/ }).first().click();
    await expect(page.getByTestId('element-costs')).toContainText('toegewezen in 2026');
    seedPhp(`$ids = App\\Models\\AllocationSet::where('canvas_id', ${target})->pluck('id'); App\\Models\\AllocationLine::whereIn('allocation_set_id', $ids)->delete(); App\\Models\\AllocationSet::whereIn('id', $ids)->delete();`);

    await page.getByRole('complementary').getByRole('button', { name: 'Archiveren' }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('Er staan geen kosten meer op dit element');
    await dialog.getByRole('button', { name: 'Opnieuw laden' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(page.getByTestId('element-costs')).toContainText('Geen kosten toegewezen');
});

test('closing the dialog with choices made asks to discard them', async ({ page }) => {
    await openEntry(page, seed());
    await page.getByRole('button', { name: /Offreren/ }).first().click();
    await page.getByRole('complementary').getByRole('button', { name: 'Archiveren' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByRole('group', { name: /2025/ }).getByRole('radio', { name: 'Terug naar onbekende allocatie' }).check();
    await page.keyboard.press('Escape');
    await expect(dialog.getByRole('alertdialog')).toBeVisible();
});
