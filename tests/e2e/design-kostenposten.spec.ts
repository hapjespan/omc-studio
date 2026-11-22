import { randomUUID } from 'node:crypto';
import type { Locator, Page } from '@playwright/test';
import { expect, test } from './support/guards';
import { seedAllocation } from './support/finance';
import { signInAs } from './support/shell';
import { seedPhp } from './support/seed';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

const INK = 'rgb(23, 27, 33)';
const MUTED = 'rgb(95, 103, 114)';
const SECONDARY = 'rgb(69, 76, 87)';
const WARNING = 'rgb(138, 82, 0)';
const HAIRLINE = 'rgb(220, 223, 226)';

const row0 = (table: Locator): Locator => table.getByRole('row', { name: /Licentie/ });

async function style(locator: Locator, ...properties: string[]): Promise<Record<string, string>> {
    return locator.first().evaluate((element, names) => {
        const computed = getComputedStyle(element);
        return Object.fromEntries(names.map((name) => [name, computed.getPropertyValue(name)]));
    }, properties);
}

async function seed(page: Page): Promise<{ canvas: number; organisation: number }> {
    const data = seedAllocation(unique(), { Licentie: 23000, Beheer: 10000 });
    seedPhp(`App\\Models\\FiscalYear::factory()->create(['organisation_id' => ${data.organisation}, 'year' => 2027]);`);
    seedPhp(`App\\Models\\YearlyFigure::whereHas('costRecord', fn ($q) => $q->where('name', 'Licentie')->where('organisation_id', ${data.organisation}))->update(['forecast_cents' => 25000]); App\\Models\\CostRecord::where('name', 'Licentie')->where('organisation_id', ${data.organisation})->update(['code' => 'KP-001']);`);
    await page.setViewportSize({ width: 1440, height: 900 });
    await signInAs(page, 'administrator');

    return data;
}

test('the Canvas variant has four cards in one row with the board typography and colours', async ({ page }) => {
    const { canvas } = await seed(page);
    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);

    const cards = page.getByRole('region', { name: 'Samenvatting van het canvas' }).locator('> div');
    await expect(cards).toHaveCount(3);
    const finalise = page.getByRole('region', { name: 'Jaar definitief maken' });
    const tops = await Promise.all([...(await cards.all()), finalise].map(async (card) => Math.round((await card.boundingBox())!.y)));
    expect(new Set(tops).size, 'one row').toBe(1);

    const heading = page.getByRole('heading', { level: 1 });
    await expect(heading).toHaveText(/^Kostenposten 2026$/);
    expect(await style(heading, 'font-family', 'font-size', 'font-weight', 'letter-spacing')).toMatchObject({ 'font-size': '22px', 'font-weight': '600', 'letter-spacing': '-0.2px' });
    expect((await style(heading, 'font-family'))['font-family']).toContain('Space Grotesk');

    const first = cards.first();
    expect(await style(first, 'border-top-color', 'border-top-left-radius', 'background-color', 'padding-left')).toMatchObject({ 'border-top-color': HAIRLINE, 'border-top-left-radius': '10px', 'background-color': 'rgb(255, 255, 255)', 'padding-left': '16px' });
    expect(await style(first.locator('p').nth(0), 'font-size', 'font-weight', 'color')).toMatchObject({ 'font-size': '11.5px', 'font-weight': '400', color: MUTED });
    const amount = first.locator('p').nth(1);
    await expect(amount).toHaveText('€ 330,00');
    expect(await style(amount, 'font-size', 'font-weight', 'letter-spacing')).toMatchObject({ 'font-size': '22px', 'font-weight': '700', 'letter-spacing': '-0.22px' });
    expect((await style(amount, 'font-family'))['font-family']).toContain('Space Grotesk');
    expect(await style(first.locator('p').nth(2), 'font-size', 'color')).toMatchObject({ 'font-size': '12px', color: SECONDARY });

    const button = finalise.getByRole('button', { name: 'Jaar definitief maken' });
    await expect(button).toBeDisabled();
    expect(await style(button, 'background-color', 'height', 'border-top-left-radius')).toMatchObject({ 'background-color': 'rgb(230, 232, 230)', height: '36px', 'border-top-left-radius': '6px' });
    await expect(button.locator('svg')).toHaveCount(1);
});

test('the toolbar is one row and the table has the board columns, the delta and the board type', async ({ page }) => {
    const { canvas } = await seed(page);
    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);

    const search = page.getByRole('searchbox', { name: 'Zoeken' });
    await expect(search).toHaveAttribute('placeholder', 'Zoek op naam of code');
    const selects = ['Soort', 'Eigenaar', 'Applicatie', 'Toewijzing'].map((name) => page.getByLabel(name, { exact: true }));
    const tops = await Promise.all([search, ...selects].map(async (field) => Math.round((await field.boundingBox())!.y)));
    expect(Math.max(...tops) - Math.min(...tops), 'search and filters share a row').toBeLessThanOrEqual(1);
    expect(await style(search, 'height', 'border-top-left-radius')).toMatchObject({ height: '36px', 'border-top-left-radius': '6px' });

    const table = page.getByRole('table', { name: 'Kostenposten' });
    expect(await style(table.locator('xpath=..'), 'border-top-left-radius', 'border-top-color', 'background-color')).toMatchObject({ 'border-top-left-radius': '10px', 'border-top-color': HAIRLINE, 'background-color': 'rgb(255, 255, 255)' });
    for (const name of ['Naam', 'Soort', 'Eigenaar', 'Applicatie', 'Budget', 'Prognose', 'Toegewezen']) await expect(table.getByRole('columnheader', { name })).toBeVisible();
    const checkbox = row0(table).getByRole('checkbox');
    expect(await style(checkbox, 'width', 'height')).toMatchObject({ width: '16px', height: '16px' });
    expect(await style(checkbox.locator('xpath=..'), 'width', 'height')).toMatchObject({ width: '24px', height: '24px' });
    const header = table.getByRole('columnheader', { name: 'Eigenaar' });
    expect(await style(header, 'font-size', 'font-weight', 'color', 'padding-left', 'padding-top')).toMatchObject({ 'font-size': '11.5px', 'font-weight': '600', color: SECONDARY, 'padding-left': '12px', 'padding-top': '10px' });

    const row = table.getByRole('row', { name: /Licentie/ });
    expect(await style(row.getByRole('button', { name: 'Licentie', exact: true }), 'font-size', 'font-weight', 'text-decoration-line')).toMatchObject({ 'font-size': '13px', 'font-weight': '600', 'text-decoration-line': 'none' });
    const budget = row.getByRole('cell').filter({ hasText: '€ 230,00' }).first();
    expect(await style(budget, 'text-align')).toMatchObject({ 'text-align': 'right' });
    const delta = row.getByText('+20,00');
    await expect(delta).toBeVisible();
    expect(await style(delta, 'color', 'font-size')).toMatchObject({ color: WARNING, 'font-size': '11.5px' });
    const status = row.getByText('Onbekende allocatie');
    expect(await style(status, 'color', 'font-weight', 'font-size')).toMatchObject({ color: WARNING, 'font-weight': '600', 'font-size': '12px' });
    expect(Math.round((await row.boundingBox())!.height)).toBeGreaterThanOrEqual(54);
    expect(await style(page.locator('body'), 'background-color')).toMatchObject({ 'background-color': 'rgb(242, 243, 241)' });
    expect((await style(page.locator('body'), 'font-family'))['font-family']).toContain('Inter');
});

test('the form is a docked panel beside a table that stays usable, with two-column fields', async ({ page }) => {
    const { canvas } = await seed(page);
    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);

    const opener = page.getByRole('button', { name: 'Licentie', exact: true });
    await opener.click();
    const panel = page.getByRole('dialog', { name: 'Licentie' });
    await expect(panel).toBeVisible();
    await expect(panel.getByRole('heading', { name: 'Licentie' })).toBeFocused();
    await expect(panel.getByText(/^Kostenpost bewerken/)).toBeVisible();

    const table = (await page.getByRole('table', { name: 'Kostenposten' }).boundingBox())!;
    const box = (await panel.boundingBox())!;
    expect(box.x, 'panel right of the table').toBeGreaterThanOrEqual(table.x + table.width);
    expect(Math.round(box.width)).toBe(360);
    expect(await style(panel, 'background-color', 'border-top-left-radius', 'border-top-color', 'padding-left')).toMatchObject({ 'background-color': 'rgb(255, 255, 255)', 'border-top-left-radius': '10px', 'border-top-color': HAIRLINE, 'padding-left': '16px' });
    await expect(page.getByRole('searchbox', { name: 'Zoeken' })).toBeEnabled();
    await page.getByRole('searchbox', { name: 'Zoeken' }).fill('Beheer');
    await expect(page.getByRole('row', { name: /Licentie/ })).toHaveCount(0);
    await expect(panel).toBeVisible();

    const same = async (a: Locator, b: Locator): Promise<number> => Math.abs((await a.boundingBox())!.y - (await b.boundingBox())!.y);
    expect(await same(panel.getByLabel('Code'), panel.getByLabel('Soort kosten'))).toBeLessThanOrEqual(1);
    expect(await same(panel.getByLabel(/^Budget/), panel.getByLabel(/^Prognose/))).toBeLessThanOrEqual(1);
    expect(await same(panel.getByLabel(/^Run/), panel.getByLabel(/^Change/))).toBeLessThanOrEqual(1);
    expect(await same(panel.getByLabel(/^FTE/), panel.getByLabel(/^Peildatum/))).toBeLessThanOrEqual(1);
    expect(await style(panel.getByLabel('Naam'), 'height', 'border-top-color', 'border-top-left-radius')).toMatchObject({ height: '36px', 'border-top-color': 'rgb(132, 139, 149)', 'border-top-left-radius': '6px' });
    expect((await panel.getByLabel('Naam').boundingBox())!.width).toBeGreaterThan((await panel.getByLabel('Code').boundingBox())!.width * 1.8);
    expect(await style(panel.getByRole('heading', { name: /^Jaarwaarden/ }), 'font-size', 'font-weight')).toMatchObject({ 'font-size': '13px', 'font-weight': '600' });

    expect(await style(panel.getByRole('button', { name: 'Opslaan', exact: true }), 'background-color', 'color', 'height')).toMatchObject({ 'background-color': INK, color: 'rgb(255, 255, 255)', height: '36px' });
    await expect(panel.getByRole('button', { name: 'Annuleren' })).toBeVisible();
    expect(await style(panel.getByRole('button', { name: 'Verwijderen', exact: true }), 'color', 'border-top-color')).toMatchObject({ color: 'rgb(161, 42, 30)', 'border-top-color': 'rgb(192, 100, 90)' });
    await expect(panel.getByRole('button', { name: 'Archiveren' })).toBeVisible();

    await panel.getByLabel('Naam').press('Escape');
    await expect(panel).toBeHidden();
});

test('the selected row carries the ink bar and the new button is ink with a plus icon', async ({ page }) => {
    const { canvas } = await seed(page);
    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);

    const create = page.getByRole('button', { name: 'Nieuw', exact: true });
    expect(await style(create, 'background-color', 'color', 'height')).toMatchObject({ 'background-color': INK, color: 'rgb(255, 255, 255)', height: '36px' });
    await expect(create.locator('svg')).toHaveCount(1);

    await page.getByRole('button', { name: 'Licentie', exact: true }).click();
    const row = page.getByRole('row', { name: /Licentie/ });
    expect(await style(row, 'background-color', 'box-shadow')).toMatchObject({ 'background-color': 'rgb(246, 247, 245)' });
    expect((await style(row, 'box-shadow'))['box-shadow']).toContain('rgb(23, 27, 33)');
});

test('unsaved edits are never dropped silently: switching rows, Nieuw, Annuleren and a year change ask first', async ({ page }) => {
    const { canvas } = await seed(page);
    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);

    await page.getByRole('button', { name: 'Licentie', exact: true }).click();
    const panel = page.getByRole('dialog', { name: 'Licentie' });
    await panel.getByLabel('Naam').fill('Licentie nieuw');
    const ask = page.getByRole('alertdialog', { name: 'Wijzigingen weggooien?' });

    await page.getByRole('button', { name: 'Beheer', exact: true }).click();
    await expect(ask).toBeVisible();
    await expect(ask.getByRole('button', { name: 'Verder bewerken' })).toBeFocused();
    await ask.getByRole('button', { name: 'Verder bewerken' }).click();
    await expect(page.getByRole('button', { name: 'Beheer', exact: true })).toBeFocused();
    await expect(panel.getByLabel('Naam')).toHaveValue('Licentie nieuw');
    await page.getByRole('button', { name: 'Beheer', exact: true }).click();
    await ask.getByRole('button', { name: 'Weggooien' }).click();
    const adminDialog = page.getByRole('dialog', { name: 'Beheer' });
    await expect(adminDialog.getByLabel('Naam')).toHaveValue('Beheer');

    await adminDialog.getByLabel('Naam').fill('Beheer nieuw');
    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    await expect(ask).toBeVisible();
    await ask.getByRole('button', { name: 'Weggooien' }).click();
    await expect(page.getByRole('dialog', { name: 'Nieuwe kostenpost' })).toBeVisible();

    await page.getByRole('dialog', { name: 'Nieuwe kostenpost' }).getByLabel('Naam').fill('Concept');
    await page.getByRole('dialog', { name: 'Nieuwe kostenpost' }).getByRole('button', { name: 'Annuleren' }).click();
    await expect(ask).toBeVisible();
    await ask.getByRole('button', { name: 'Weggooien' }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);

    await page.getByRole('button', { name: 'Licentie', exact: true }).click();
    await page.getByRole('dialog', { name: 'Licentie' }).getByLabel(/^Budget/).fill('999,00');
    await page.getByLabel('Boekjaar').selectOption('2027');
    await expect(ask).toBeVisible();
    await ask.getByRole('button', { name: 'Verder bewerken' }).click();
    await expect(page.getByLabel('Boekjaar')).toHaveValue('2026');
    expect(page.url()).toContain('year=2026');
    await page.getByLabel('Boekjaar').selectOption('2027');
    await ask.getByRole('button', { name: 'Weggooien' }).click();
    await expect(page).toHaveURL(/year=2027/);
});

test('Escape returns focus to the row that opened the panel, also after switching rows', async ({ page }) => {
    const { canvas } = await seed(page);
    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);

    const licentie = page.getByRole('button', { name: 'Licentie', exact: true });
    await licentie.click();
    await page.getByRole('button', { name: 'Beheer', exact: true }).click();
    const panel = page.getByRole('dialog', { name: 'Beheer' });
    await panel.getByLabel('Naam').press('Escape');
    await expect(panel).toBeHidden();
    await expect(licentie).toBeFocused();
});

test('a bulk deletion of the row in the panel closes the panel', async ({ page }) => {
    const { canvas } = await seed(page);
    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);

    await page.getByRole('button', { name: 'Licentie', exact: true }).click();
    const panel = page.getByRole('dialog', { name: 'Licentie' });
    await expect(panel).toBeVisible();
    await page.getByRole('checkbox', { name: 'Selecteer Licentie' }).check();
    await page.getByRole('button', { name: 'Verwijderen', exact: true }).first().click();
    await expect(panel).toBeHidden();
});
