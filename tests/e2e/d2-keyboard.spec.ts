import { expect, test } from './support/guards';
import { seedAllocation } from './support/finance';
import { createClosingNotice } from './support/notices';
import { seedOverlay } from './support/overlay';
import { seedWorkspace, unique } from './support/d2';
import { activate, choose, tabTo, type } from './support/keyboard';
import { signInAs } from './support/shell';

test('Owners: create by keyboard alone and close the form with Escape', async ({ page }) => {
    const { organisation } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/admin/organisations/${organisation}/owners`);

    await activate(page, page.getByRole('button', { name: 'Nieuw', exact: true }));
    const panel = page.getByRole('dialog', { name: 'Nieuwe eigenaar' });
    await expect(panel).toBeVisible();
    await type(page, panel.getByLabel('Naam'), 'Afdeling HR');
    await activate(page, panel.getByRole('button', { name: 'Opslaan' }));
    await expect(page.getByRole('button', { name: 'Afdeling HR', exact: true })).toBeVisible();
});

test('Applications: create by keyboard alone', async ({ page }) => {
    const { organisation } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/admin/organisations/${organisation}/applications`);

    await activate(page, page.getByRole('button', { name: 'Nieuw', exact: true }));
    const panel = page.getByRole('dialog', { name: 'Nieuwe applicatie' });
    await type(page, panel.getByLabel('Naam'), 'Roostersysteem');
    await type(page, panel.getByLabel('Leverancier'), 'Leverancier X');
    await activate(page, panel.getByRole('button', { name: 'Opslaan' }));
    await expect(page.getByRole('button', { name: 'Roostersysteem', exact: true })).toBeVisible();
});

test('Kostenposten of the Organisation: create a record and save its Yearly figures by keyboard alone', async ({ page }) => {
    const { organisation } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);

    await activate(page, page.getByRole('button', { name: 'Nieuw', exact: true }));
    const form = page.getByRole('dialog', { name: 'Nieuwe kostenpost' });
    await type(page, form.getByLabel('Naam'), 'Onderhoud');
    await choose(page, form.getByLabel('Soort kosten'), 'Project');
    await choose(page, form.getByLabel('Eigenaar'), 'Afdeling');
    await activate(page, form.getByRole('button', { name: 'Opslaan' }));
    await expect(form).toBeHidden();

    await activate(page, page.getByRole('button', { name: 'Onderhoud', exact: true }));
    const record = page.getByRole('dialog', { name: 'Onderhoud' });
    await type(page, record.getByLabel(/^Budget/), '1.234,56');
    await activate(page, record.getByRole('button', { name: 'Jaarwaarden opslaan' }));
    await expect(page.locator('tr', { hasText: 'Onderhoud' })).toContainText('€ 1.234,56');
});

test('Kostenposten of the Canvas: search, open a record, carry over and finalise by keyboard alone', async ({ page }) => {
    const { canvas } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);

    await type(page, page.getByRole('searchbox').first(), 'Lic');
    await activate(page, page.getByRole('button', { name: 'Licentie', exact: true }));
    await expect(page.getByRole('dialog', { name: 'Licentie' })).toBeVisible();
    await page.keyboard.press('Escape');
    await activate(page, page.getByRole('button', { name: 'Jaar overnemen' }));
    await expect(page.getByRole('dialog', { name: 'Jaar overnemen' })).toBeVisible();
    await page.keyboard.press('Escape');

    const ready = seedAllocation(unique(), {});
    await page.goto(`/canvases/${ready.canvas}/cost-records?year=2026`);
    await activate(page, page.getByRole('button', { name: 'Jaar definitief maken' }));
    await expect(page.getByText('Het jaar 2026 is definitief.')).toBeVisible();
});

test('Import of Cost records: check and import by keyboard alone', async ({ page }) => {
    const { organisation } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records/import`);

    const header = 'code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte';
    await tabTo(page, page.getByLabel('CSV-bestand'));
    await page.getByLabel('CSV-bestand').setInputFiles({ name: 'import.csv', mimeType: 'text/csv', buffer: Buffer.from(`${header}\n${unique('K')};Licenties;Project;;Ada;;;2026;12,50;;;;\n`) });
    await activate(page, page.getByRole('button', { name: 'Controleren' }));
    await expect(page.getByRole('main').getByRole('status')).toContainText('Controle klaar: geen fouten');
    await activate(page, page.getByRole('button', { name: 'Importeren' }));
    await expect(page.getByRole('main').getByRole('status')).toContainText('Geïmporteerd: 1 nieuw');
});

test('Toewijzen: choose a record, add Targets, enter percentages and save by keyboard alone', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/allocation?year=2026`);

    await activate(page, page.getByRole('button', { name: /Licentie/ }));
    await activate(page, page.getByRole('button', { name: 'Toevoegen aan verdeling: Offreren' }));
    await activate(page, page.getByRole('button', { name: 'Toevoegen aan verdeling: Bestellen' }));
    await activate(page, page.getByRole('button', { name: 'Toevoegen aan verdeling: Betalen' }));
    for (const [name, value] of [['Offreren', '33,33'], ['Bestellen', '33,33'], ['Betalen', '33,34']]) {
        const input = page.getByLabel(new RegExp(`Percentage voor .*${name}`));
        await tabTo(page, input);
        await page.keyboard.press('Control+A');
        await page.keyboard.type(value);
        await page.keyboard.press('Tab');
    }
    await expect(page.getByTestId('remaining')).toHaveText('0,00%');
    await activate(page, page.getByRole('button', { name: 'Opslaan' }));
    await expect(page.getByText('Verdeling opgeslagen').first()).toBeAttached();
});

test('Carry over: run by keyboard alone and return to the opener with Escape', async ({ page }) => {
    const { canvas } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);

    const opener = page.getByRole('button', { name: 'Jaar overnemen' });
    await activate(page, opener);
    const panel = page.getByRole('dialog', { name: 'Jaar overnemen' });
    await choose(page, panel.getByLabel('Bronjaar'), '2026');
    await choose(page, panel.getByLabel('Doeljaar'), '2027');
    await tabTo(page, panel.getByLabel('Ook jaarwaarden overnemen'));
    await page.keyboard.press('Space');
    await activate(page, panel.getByRole('button', { name: 'Overnemen' }));
    await expect(panel.getByRole('status')).toContainText('Overgenomen: 1');
    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(opener).toBeFocused();
});

test('Boekjaren: add and delete a year by keyboard alone and return to the opener with Escape', async ({ page }) => {
    const { organisation } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);

    const opener = page.getByRole('button', { name: 'Boekjaren' });
    await activate(page, opener);
    const panel = page.getByRole('dialog', { name: 'Boekjaren' });
    await type(page, panel.getByLabel('Jaar'), '2028');
    await page.keyboard.press('Enter');
    await expect(panel.getByText('2028', { exact: true })).toBeVisible();
    await activate(page, panel.getByRole('button', { name: 'Verwijderen 2028' }));
    const confirm = page.getByRole('dialog', { name: '2028 verwijderen?' });
    await activate(page, confirm.getByRole('button', { name: 'Verwijderen' }));
    await expect(panel.getByText('2028', { exact: true })).toHaveCount(0);
    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(opener).toBeFocused();
});

test('Overlays: switch the overlay, filter, and open a card with its figures by keyboard alone', async ({ page }) => {
    const seed = seedOverlay(unique('Overlay'));
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?year=2026`);

    await activate(page, page.getByRole('button', { name: 'Financieel' }));
    await expect(page.getByRole('button', { name: 'Financieel', pressed: true })).toBeVisible();
    await choose(page, page.getByLabel('Eigenaar'), 'Afdeling ICT');
    await expect(page.locator('[data-active-filters]')).toHaveText('Actieve filters: Eigenaar: Afdeling ICT');
    await activate(page, page.getByRole('button', { name: 'Modaliteit', exact: true }));
    await expect(page.getByRole('button', { name: 'Modaliteit', pressed: true })).toBeVisible();
    await activate(page, page.getByRole('button', { name: 'Geen', exact: true }));
    await expect(page.getByRole('button', { name: 'Geen', pressed: true })).toBeVisible();
    await activate(page, page.getByRole('button', { name: 'Financieel' }));

    await tabTo(page, page.getByRole('button', { name: /^Primaire activiteiten\./ }));
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('button', { name: /^Onderwijs Totaal/ })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#detail-panel').getByRole('heading', { name: 'Onderwijs' })).toBeVisible();
    await expect(page.locator('#detail-panel')).toContainText('€ 1.000');
});

test('Systeemmeldingen: filter on Canvas by keyboard alone', async ({ page }) => {
    const canvas = unique('Melding');
    createClosingNotice(canvas, 1);
    await signInAs(page, 'administrator');
    await page.goto('/admin/system-notices');

    await choose(page, page.getByLabel('Canvas'), canvas);
    await activate(page, page.getByRole('button', { name: 'Filteren' }));
    await expect(page.getByTestId('notice-count')).toHaveText('1 melding');
});
