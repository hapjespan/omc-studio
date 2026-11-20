import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedAllocation, expandTarget } from './support/finance';
import { createClosingNotice } from './support/notices';
import { seedOverlay } from './support/overlay';
import { seedWorkspace, unique } from './support/d2';
import { signInAs } from './support/shell';

test.beforeEach(() => test.setTimeout(120000));

const HEADER = 'code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte';

test('Owners: the list and the open form', async ({ page }) => {
    const { organisation } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/admin/organisations/${organisation}/owners`);
    await expect(page.getByRole('heading', { level: 1, name: 'Eigenaren' })).toBeVisible();
    await expectNoAxeViolations(page);
    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    await expect(page.getByRole('dialog', { name: 'Nieuwe eigenaar' })).toBeVisible();
    await expectNoAxeViolations(page);
});

test('Applications: the list and the open form', async ({ page }) => {
    const { organisation } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/admin/organisations/${organisation}/applications`);
    await expect(page.getByRole('heading', { level: 1, name: 'Applicaties' })).toBeVisible();
    await expectNoAxeViolations(page);
    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    await expect(page.getByRole('dialog', { name: 'Nieuwe applicatie' })).toBeVisible();
    await expectNoAxeViolations(page);
});

test('Kostenposten of the Organisation: the list, the new form and a record with Yearly figures', async ({ page }) => {
    const { organisation } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);
    await expect(page.getByRole('button', { name: 'Licentie', exact: true })).toBeVisible();
    await expectNoAxeViolations(page);
    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    await expect(page.getByRole('dialog', { name: 'Nieuwe kostenpost' })).toBeVisible();
    await expectNoAxeViolations(page);
    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: 'Licentie', exact: true }).click();
    await expect(page.getByRole('dialog', { name: 'Licentie' }).getByLabel(/^Budget/)).toBeVisible();
    await expectNoAxeViolations(page);
});

test('Kostenposten of the Canvas: the list with cards and the Finalise card', async ({ page }) => {
    const { canvas } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);
    await expect(page.getByRole('heading', { level: 2, name: 'Jaar definitief maken' })).toBeVisible();
    await expectNoAxeViolations(page);
});

test('Import of Cost records: the form, a faulty report and a clean report', async ({ page }) => {
    const { organisation } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records/import`);
    await expect(page.getByRole('heading', { level: 1, name: 'Kostenposten importeren' })).toBeVisible();
    await expectNoAxeViolations(page);

    const file = (line: string) => ({ name: 'import.csv', mimeType: 'text/csv', buffer: Buffer.from(`${HEADER}\n${line}\n`) });
    await page.getByLabel('CSV-bestand').setInputFiles(file(`${unique('K')};Licenties;Project;;Ada;;;2026;12.50;;;;`));
    await page.getByRole('button', { name: 'Controleren' }).click();
    await expect(page.getByRole('main').getByRole('status')).toContainText('Controle klaar: 1 fouten');
    await expectNoAxeViolations(page);

    await page.getByLabel('CSV-bestand').setInputFiles(file(`${unique('K')};Licenties;Project;;Ada;;;2026;12,50;;;;`));
    await page.getByRole('button', { name: 'Controleren' }).click();
    await expect(page.getByRole('main').getByRole('status')).toContainText('Controle klaar: geen fouten');
    await expectNoAxeViolations(page);
});

test('Toewijzen: the page with a chosen record and lines, and the import panel', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/allocation?year=2026&cost_record=${seed.records.Licentie}`);
    await expandTarget(page, 'Inkoop');
    await page.getByRole('button', { name: 'Toevoegen aan verdeling: Offreren' }).click();
    await expect(page.getByLabel(/Percentage voor .*Offreren/)).toBeVisible();
    await expectNoAxeViolations(page);

    await page.getByRole('button', { name: 'Toewijzingen importeren' }).click();
    await expect(page.getByLabel('CSV-bestand')).toBeVisible();
    await expectNoAxeViolations(page);
});

test('Carry over: the panel and its result', async ({ page }) => {
    const { canvas } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);
    await page.getByRole('button', { name: 'Jaar overnemen' }).click();
    const panel = page.getByRole('dialog', { name: 'Jaar overnemen' });
    await expectNoAxeViolations(page);
    await panel.getByLabel('Bronjaar').selectOption('2026');
    await panel.getByLabel('Doeljaar').selectOption('2027');
    await panel.getByRole('button', { name: 'Overnemen' }).click();
    await expect(panel.getByRole('status')).toContainText('Overgenomen');
    await expectNoAxeViolations(page);
});

test('Boekjaren: the panel', async ({ page }) => {
    const { organisation } = seedWorkspace();
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);
    await page.getByRole('button', { name: 'Boekjaren' }).click();
    await expect(page.getByRole('dialog', { name: 'Boekjaren' })).toBeVisible();
    await expectNoAxeViolations(page);
});

for (const [overlay, label] of [['financial', 'Financieel'], ['modality', 'Modaliteit']]) {
    test(`Bekijken with the ${overlay} overlay`, async ({ page }) => {
        const seed = seedOverlay(unique('Overlay'));
        await signInAs(page, 'reader');
        await page.goto(`/canvases/${seed.canvas}/view?overlay=${overlay}&year=2026`);
        await expect(page.getByRole('button', { name: label, pressed: true })).toBeVisible();
        await expect(page.locator('[data-artboard]')).toBeVisible();
        await expectNoAxeViolations(page);
        if (overlay === 'financial') {
            await page.getByRole('button', { name: /^Onderwijs Totaal/ }).click();
            await expect(page.locator('#detail-panel').getByRole('heading', { name: 'Onderwijs' })).toBeVisible();
            await expectNoAxeViolations(page);
        }
    });
}

test('Systeemmeldingen: the list', async ({ page }) => {
    createClosingNotice(unique('Melding'), 1);
    await signInAs(page, 'administrator');
    await page.goto('/admin/system-notices');
    await expect(page.getByRole('heading', { level: 1, name: 'Systeemmeldingen' })).toBeVisible();
    await expectNoAxeViolations(page);
});
