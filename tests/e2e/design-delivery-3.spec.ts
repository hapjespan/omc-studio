import { randomUUID } from 'node:crypto';
import type { Locator } from '@playwright/test';
import { expect, test } from './support/guards';
import { tinker } from './support/finance';
import { createClosingNotice } from './support/notices';
import { seedSnapshot } from './support/snapshot';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

const style = (locator: Locator, property: string): Promise<string> => locator.evaluate((element, name) => getComputedStyle(element).getPropertyValue(name), property);
const height = async (locator: Locator): Promise<number> => Math.round((await locator.boundingBox())!.height);
const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test.use({ viewport: { width: 1440, height: 900 } });

async function expectTitle(title: Locator): Promise<void> {
    expect(await style(title, 'font-family')).toContain('Space Grotesk');
    expect(await style(title, 'font-size')).toBe('20px');
    expect(await style(title, 'font-weight')).toBe('600');
}

async function expectPanel(panel: Locator, radius = '10px', filled = true): Promise<void> {
    if (filled) expect(await style(panel, 'background-color')).toBe('rgb(255, 255, 255)');
    expect(await style(panel, 'border-top-color')).toBe('rgb(220, 223, 226)');
    expect(await style(panel, 'border-top-width')).toBe('1px');
    expect(await style(panel, 'border-top-left-radius')).toBe(radius);
}

async function expectTableHeader(header: Locator): Promise<void> {
    expect(await style(header, 'font-size')).toBe('11.5px');
    expect(await style(header, 'font-weight')).toBe('600');
    expect(await style(header, 'color')).toBe('rgb(69, 76, 87)');
}

test('Versies: Space Grotesk title, white table panel, docked form, pill and 36 pixel buttons', async ({ page }) => {
    const seed = seedSnapshot(unique());
    tinker(`Illuminate\\Support\\Facades\\DB::table('snapshots')->where('id', ${seed.snapshot})->update(['imported' => true]);`);
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/versions`);

    expect(await style(page.locator('body'), 'background-color')).toBe('rgb(242, 243, 241)');
    await expectTitle(page.getByRole('heading', { level: 1, name: 'Versies' }));
    const table = page.getByRole('table', { name: 'Versies' });
    await expectPanel(table.locator('..'));
    await expectTableHeader(table.getByRole('columnheader', { name: 'Boekjaar' }));
    const cell = table.getByRole('cell').first();
    expect(await style(cell, 'padding-top')).toBe('10px');
    expect(await style(cell, 'padding-left')).toBe('12px');
    expect(await style(cell, 'font-size')).toBe('13px');
    expect(await style(cell.getByRole('button'), 'font-weight')).toBe('600');

    const imported = table.getByText('Geïmporteerd').nth(1);
    await expect(imported).toBeVisible();
    expect(await style(imported, 'border-top-left-radius')).not.toBe('0px');
    expect(await style(imported, 'font-size')).toBe('11.5px');

    const create = page.getByRole('button', { name: 'Momentopname maken' });
    expect(await height(create)).toBe(36);
    expect(await style(create, 'background-color')).toBe('rgb(23, 27, 33)');
    expect(await style(create, 'border-top-left-radius')).toBe('6px');
    await create.click();

    const dock = page.getByRole('dialog', { name: 'Momentopname maken' });
    await expectPanel(dock);
    expect(await style(dock, 'padding-top')).toBe('16px');
    expect((await dock.boundingBox())!.width).toBe(360);
    expect(await height(dock.getByLabel('Naam'))).toBe(36);
    expect(await style(dock.getByLabel('Naam'), 'border-top-color')).toBe('rgb(132, 139, 149)');
    expect(await height(dock.getByRole('button', { name: 'Opslaan' }))).toBe(36);
    expect(await height(dock.getByRole('button', { name: 'Annuleren' }))).toBe(36);
    await expect(table.getByRole('link', { name: /^Openen/ }).first()).toBeVisible();
    expect(await style(dock.getByRole('heading', { level: 2 }), 'font-family')).toContain('Space Grotesk');
});

test('Volledige export en import: title, two white panels, 36 pixel controls and a file field', async ({ page }) => {
    await signInAs(page, 'administrator');
    await page.goto('/admin/exchange');

    await expectTitle(page.getByRole('heading', { level: 1, name: 'Volledige export en import' }));
    for (const name of ['Exporteren', 'Importeren']) {
        const panel = page.getByRole('region', { name });
        await expectPanel(panel);
        expect(await style(panel, 'padding-top')).toBe('24px');
        expect(await style(panel.getByRole('heading', { level: 2 }), 'font-family')).toContain('Space Grotesk');
    }
    const link = page.getByRole('link', { name: 'Alles exporteren' });
    expect(await height(link)).toBe(36);
    expect(await style(link, 'border-top-left-radius')).toBe('6px');
    expect(await style(link, 'font-weight')).toBe('500');
    const file = page.getByLabel('Bestand met volledige export');
    expect(await height(file)).toBeGreaterThanOrEqual(36);
    expect(await style(file.locator('xpath=following-sibling::div'), 'border-top-color')).toBe('rgb(132, 139, 149)');
    const check = page.getByRole('button', { name: 'Controleren' });
    expect(await height(check)).toBe(36);
    expect(await style(check, 'background-color')).toBe('rgb(230, 232, 230)');
    expect(await height(page.getByRole('button', { name: 'Importeren' }))).toBe(36);
});

test('Importeren uit JSON: white panel, 36 pixel fields and a report as panel with the table header of Kostenposten', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    const exported = await (await page.request.get(`/canvases/${canvas.id}/export/json`)).text();
    await page.goto('/overview');
    const open = page.getByRole('button', { name: 'Importeren uit JSON' });
    expect(await height(open)).toBe(36);
    await open.click();

    const panel = page.getByRole('region', { name: 'Importeren uit JSON' });
    await expectPanel(panel);
    await page.getByLabel('Naam van de nieuwe organisatie').fill(unique());
    await page.getByLabel('Naam van het canvas').fill(unique());
    expect(await height(page.getByLabel('Naam van het canvas'))).toBe(36);
    await page.getByLabel('JSON-bestand').setInputFiles({ name: 'ok.json', mimeType: 'application/json', buffer: Buffer.from(exported) });
    await page.getByRole('button', { name: 'Controleren' }).click();

    const report = panel.getByRole('region', { name: 'Samenvatting' });
    await expect(report).toBeVisible();
    const table = report.getByRole('table', { name: 'Samenvatting' });
    await expectPanel(table.locator('..'), '8px', false);
    await expectTableHeader(table.getByRole('columnheader', { name: 'Soort' }));
    const cell = table.getByRole('cell').first();
    expect(await style(cell, 'padding-top')).toBe('10px');
    expect(await style(cell, 'padding-left')).toBe('12px');
    expect(await style(report.getByRole('heading', { level: 3 }), 'font-family')).toContain('Space Grotesk');
    expect(await style(report.getByRole('heading', { level: 3 }), 'font-size')).toBe('16px');
    expect(await style(table.getByRole('row').last().getByRole('cell'), 'color')).toBe('rgb(31, 122, 77)');
});

test('CSV import on Toewijzen: panel, status pills and 36 pixel buttons in the report', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/allocation`);
    await page.getByRole('button', { name: 'Toewijzingen importeren' }).click();
    const panel = page.getByRole('region', { name: 'Toewijzingen importeren' });
    await expectPanel(panel);
    await page.getByLabel('CSV-bestand').setInputFiles({ name: 'import.csv', mimeType: 'text/csv', buffer: Buffer.from('kostenpost_code;jaar;doel_pad;percentage\nNOPE;2026;Onbekend;100,00\n') });
    await page.getByRole('button', { name: 'Controleren' }).click();

    const lines = panel.getByRole('region', { name: 'Regels' });
    await expect(lines).toBeVisible();
    const table = lines.getByRole('table');
    await expectPanel(table.locator('..'), '8px', false);
    await expectTableHeader(table.getByRole('columnheader').first());
    const pill = table.getByText('fout');
    expect(await style(pill, 'border-top-left-radius')).not.toBe('0px');
    expect(await style(pill, 'font-size')).toBe('11.5px');
    expect(await height(pill)).toBeLessThanOrEqual(24);
    expect(await height(table.getByRole('columnheader').first())).toBe(36);
    expect(await style(table.getByText(/./, { hasText: /kostenpost|code|onbekend/i }).last(), 'color')).toBe('rgb(161, 42, 30)');
    expect(await height(panel.getByRole('button', { name: 'Importeren', exact: true }))).toBe(36);
});

test('Voorbeelddata: title, white panel and a danger button with the contract outline', async ({ page }) => {
    tinker("App\\Models\\Organisation::where('is_sample', true)->exists() || (Illuminate\\Support\\Facades\\DB::table('application_state')->where('key', 'sample_data_removed')->delete() && app(App\\Services\\Operations\\SampleData::class)->seed());");
    await signInAs(page, 'administrator');
    await page.goto('/admin/sample-data');

    await expectTitle(page.getByRole('heading', { level: 1, name: 'Voorbeelddata' }));
    const button = page.getByRole('button', { name: 'Voorbeelddata verwijderen' });
    await expectPanel(button.locator('..'));
    expect(await style(button.locator('..'), 'padding-top')).toBe('24px');
    expect(await height(button)).toBe(36);
    expect(await style(button, 'color')).toBe('rgb(161, 42, 30)');
    expect(await style(button, 'border-top-color')).toBe('rgb(192, 100, 90)');
});

test('Systeemmeldingen: title, filter panel, notice panels and a 28 pixel Gezien target', async ({ page }) => {
    createClosingNotice(unique(), 125);
    await signInAs(page, 'administrator');
    await page.goto('/admin/system-notices');

    await expectTitle(page.getByRole('heading', { level: 1, name: 'Systeemmeldingen' }));
    const filters = page.locator('form').filter({ has: page.getByLabel('Soort') });
    await expectPanel(filters);
    expect(await style(filters, 'padding-top')).toBe('16px');
    expect(await height(page.getByLabel('Soort'))).toBe(36);
    expect(await height(page.getByRole('button', { name: 'Filteren' }))).toBe(36);
    const notice = page.locator('main ol > li').first();
    await expectPanel(notice);
    expect(await style(notice, 'padding-top')).toBe('16px');
    expect(await style(notice.getByText('Soort', { exact: true }), 'color')).toBe('rgb(69, 76, 87)');
    const seen = notice.getByRole('button', { name: /gezien/i });
    expect(await height(seen)).toBeGreaterThanOrEqual(24);
    expect(await style(seen, 'border-top-left-radius')).toBe('6px');
});

test('Momentopname banner: surface card with the hairline border, radius 8 and Space Grotesk text', async ({ page }) => {
    const seed = seedSnapshot(unique());
    await signInAs(page, 'administrator');
    await page.goto(`/snapshots/${seed.snapshot}/view`);

    const banner = page.locator('[data-snapshot-banner]');
    await expect(banner).toBeVisible();
    expect(await style(banner, 'background-color')).toBe('rgb(246, 247, 245)');
    expect(await style(banner, 'border-top-color')).toBe('rgb(220, 223, 226)');
    expect(await style(banner, 'border-top-left-radius')).toBe('8px');
    expect(await style(banner.locator('p').first(), 'font-family')).toContain('Space Grotesk');
    expect(await style(banner.locator('p').first(), 'font-size')).toBe('14px');
});

test('Exporteren menu: hairline popover with the contract shadow, 32 pixel items and a 28 pixel overlay switch', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/view?overlay=financial`);

    const toggle = page.getByLabel('Met overlay');
    await expect(toggle).toBeVisible();
    expect(await height(toggle.locator('..'))).toBe(28);
    await page.getByRole('button', { name: 'Exporteren' }).click();
    const menu = page.getByRole('menu', { name: 'Exporteren' });
    await expect(menu).toBeVisible();
    expect(await style(menu, 'border-top-color')).toBe('rgb(220, 223, 226)');
    expect(await style(menu, 'box-shadow')).toContain('rgba(0, 0, 0, 0.12) 0px 1px 3px');
    const item = menu.getByRole('menuitem').first();
    expect(await height(item)).toBe(32);
    expect(await style(item, 'border-top-left-radius')).toBe('4px');
});
