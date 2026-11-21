import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import type { Page } from '@playwright/test';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { expandTarget, seedOrganisation, tinker } from './support/finance';
import { activate, tabTo, type } from './support/keyboard';
import { signInAs } from './support/shell';
import { ARTBOARD_WIDTH, PNG_SCALE } from './support/artboard';
import { pixelAt } from './support/png';

test.beforeEach(() => test.setTimeout(180000));

const unique = (prefix: string): string => `${prefix} ${randomUUID().slice(0, 8)}`;

async function addPercentage(page: Page, activity: string, value: string): Promise<void> {
    await page.getByRole('button', { name: `Toevoegen aan verdeling: ${activity}` }).click();
    const input = page.getByLabel(new RegExp(`Percentage voor .*${activity}`));
    await input.fill(value);
    await input.blur();
}

test('FR-096 with delivery 3: create, structure, cost, allocate, overlay by hand, Snapshot, JSON out and in, PNG, under the three guards', async ({ page }) => {
    const organisationName = unique('Reis');
    const canvasName = unique('Model');
    const organisation = seedOrganisation(organisationName, [2026]);
    tinker(`App\\Models\\Owner::factory()->create(['organisation_id' => ${organisation}, 'name' => 'Afdeling ICT']);`);
    await signInAs(page, 'administrator');

    await page.goto('/overview');
    await page.getByRole('button', { name: 'Nieuw canvas' }).click();
    const dialog = page.getByRole('dialog', { name: 'Nieuw canvas' });
    await dialog.getByLabel('Naam').fill(canvasName);
    await dialog.getByRole('combobox', { name: 'Organisatie' }).click();
    await page.getByRole('option', { name: organisationName }).click();
    await dialog.getByRole('button', { name: 'Aanmaken' }).click();
    await page.waitForURL(/\/canvases\/\d+\/entry/);
    const canvas = Number(/canvases\/(\d+)/.exec(page.url())?.[1]);

    await page.getByRole('navigation', { name: 'Areas' }).getByRole('button', { name: /Primaire activiteiten/ }).click();
    await page.getByRole('button', { name: 'Domein', exact: true }).click();
    const domain = page.getByLabel('+ Domein');
    await domain.fill('Inkoop');
    await domain.press('Enter');
    const line = page.getByLabel('Activiteit of cluster toevoegen aan Inkoop, Enter om op te slaan');
    for (const name of ['Offreren', 'Bestellen', 'Betalen']) {
        await line.fill(name);
        await line.press('Enter');
        await expect(page.getByTestId('entry-row').filter({ hasText: name })).toBeVisible();
    }
    await expectNoAxeViolations(page);

    await page.goto(`/canvases/${canvas}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Inkoop', exact: true }).click();
    const fill = page.getByLabel('Vulling van Inkoop', { exact: true });
    await fill.fill('#CCE5FF');
    await fill.press('Enter');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    await expectNoAxeViolations(page);

    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);
    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    const form = page.getByRole('dialog', { name: 'Nieuwe kostenpost' });
    await form.getByLabel('Naam').fill('Licentie planning');
    await form.getByLabel('Soort kosten').selectOption('supplier_contract');
    await form.getByLabel('Eigenaar').selectOption({ label: 'Afdeling ICT' });
    await form.getByRole('button', { name: 'Opslaan' }).click();
    await expect(form).toBeHidden();
    await page.getByRole('button', { name: 'Licentie planning', exact: true }).click();
    const record = page.getByRole('dialog', { name: 'Licentie planning' });
    await record.getByLabel(/^Budget/).fill('230,00');
    await record.getByLabel(/^Prognose/).fill('200,00');
    await record.getByRole('button', { name: 'Jaarwaarden opslaan' }).click();
    await expect(page.locator('tr', { hasText: 'Licentie planning' })).toContainText('€ 230,00');
    await page.keyboard.press('Escape');

    await page.goto(`/canvases/${canvas}/allocation?year=2026`);
    await page.getByRole('button', { name: /Licentie planning/ }).click();
    await expect(page.getByRole('heading', { level: 2, name: 'Licentie planning' })).toBeVisible();
    await expandTarget(page, 'Inkoop');
    await addPercentage(page, 'Offreren', '33,33');
    await addPercentage(page, 'Bestellen', '33,33');
    await addPercentage(page, 'Betalen', '33,34');
    await expect(page.getByTestId('remaining')).toHaveText('0,00%');
    await page.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByText('Verdeling opgeslagen').first()).toBeVisible();
    await expectNoAxeViolations(page);

    await page.goto(`/canvases/${canvas}/view?overlay=financial&year=2026`);
    const card = page.locator('[data-kind="domain"]').filter({ hasText: 'Inkoop' }).first();
    const layer = page.locator(`[data-overlay-card="${await card.getAttribute('data-block')}"]`);
    await expect(layer.locator('[data-total]')).toHaveText('€ 230 100,0% van totaal');
    const shown = await page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent ?? '{}').props.figures.elements);
    const budgets = Object.values<{ total: { budget: number } }>(shown).map((element) => element.total.budget);
    expect(budgets.filter((cents) => cents > 0 && cents < 23000).sort((a, b) => a - b)).toEqual([7666, 7666, 7668]);
    await expect(page.getByRole('complementary', { name: 'Totalen van het canvas' }).locator('[data-closing]')).toHaveText('Totalen sluiten: € 230,00 + € 0,00 = € 230,00');
    await expectNoAxeViolations(page);

    await page.goto(`/canvases/${canvas}/versions`);
    await activate(page, page.getByRole('button', { name: 'Momentopname maken' }));
    const panel = page.getByRole('dialog', { name: 'Momentopname maken' });
    await type(page, panel.getByLabel('Naam'), 'Voor de stuurgroep');
    await activate(page, panel.getByRole('button', { name: 'Opslaan' }));
    await expect(page.getByRole('row', { name: /Voor de stuurgroep/ })).toBeVisible();
    await expectNoAxeViolations(page);
    await activate(page, page.getByRole('link', { name: 'Voor de stuurgroep' }));
    await expect(page.getByRole('region', { name: 'Momentopname' })).toContainText('Voor de stuurgroep');
    await expect(page.locator('[data-kind="domain"]').filter({ hasText: 'Inkoop' }).first()).toBeVisible();
    await expectNoAxeViolations(page);
    await tabTo(page, page.getByLabel('Versie'));
    await page.keyboard.type('Actueel');
    await activate(page, page.getByRole('button', { name: 'Openen' }));
    await expect(page).toHaveURL(new RegExp(`/canvases/${canvas}/view`));

    const exported = await (await page.request.get(`/canvases/${canvas}/export/json`)).text();
    expect(JSON.parse(exported).canvas.elements.length).toBeGreaterThanOrEqual(4);
    await page.goto('/overview');
    await activate(page, page.getByRole('button', { name: 'Importeren uit JSON' }));
    await type(page, page.getByLabel('Naam van de nieuwe organisatie'), unique('Import'));
    await type(page, page.getByLabel('Naam van het canvas'), unique('Terug'));
    await tabTo(page, page.getByLabel('JSON-bestand'));
    await page.getByLabel('JSON-bestand').setInputFiles({ name: 'canvas.json', mimeType: 'application/json', buffer: Buffer.from(exported) });
    await activate(page, page.getByRole('button', { name: 'Controleren' }));
    await expect(page.getByRole('status').filter({ hasText: 'Controle klaar: geen fouten' })).toBeVisible();
    await activate(page, page.getByRole('button', { name: 'Importeren', exact: true }));
    await expect(page.getByRole('status').filter({ hasText: 'Canvas is geïmporteerd' })).toBeVisible();
    await page.goto('/overview');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expectNoAxeViolations(page);

    await page.goto(`/canvases/${canvas}/view?overlay=financial&year=2026`);
    await activate(page, page.getByRole('button', { name: 'Exporteren' }));
    const png = page.getByRole('menuitem', { name: 'Exporteren als PNG' });
    for (let step = 0; step < 8 && !(await png.evaluate((element) => element === document.activeElement)); step += 1) await page.keyboard.press('ArrowDown');
    await expect(png).toBeFocused();
    const download = page.waitForEvent('download');
    await page.keyboard.press('Enter');
    const file = await download;
    const bytes = readFileSync((await file.path()) as string);
    expect(bytes.readUInt32BE(16)).toBeGreaterThan(0);
    await expect(page.getByTestId('export-note')).toHaveText('PNG is gemaakt');
    await expectNoAxeViolations(page);
    await page.goto(`/canvases/${canvas}/view`);
    await page.getByRole('button', { name: 'Exporteren' }).click();
    const styledDownload = page.waitForEvent('download');
    await page.getByRole('menuitem', { name: 'Exporteren als PNG' }).click();
    const styledBytes = readFileSync(((await (await styledDownload).path()) as string));
    const artboard = (await page.locator('[data-artboard]').boundingBox())!;
    const procurement = (await page.locator('[data-kind="domain"]').filter({ hasText: 'Inkoop' }).first().boundingBox())!;
    const scale = artboard.width / ARTBOARD_WIDTH;
    const pixel = await pixelAt(page, styledBytes, Math.round(((procurement.x + procurement.width - artboard.x) / scale - 10) * PNG_SCALE), Math.round(((procurement.y + procurement.height - artboard.y) / scale - 10) * PNG_SCALE));
    for (const [index, channel] of [0xcc, 0xe5, 0xff].entries()) expect(Math.abs(pixel[index] - channel)).toBeLessThanOrEqual(2);
});
