import { randomUUID } from 'node:crypto';
import type { Page } from '@playwright/test';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedOrganisation, tinker, expandTarget } from './support/finance';
import { seedPerformanceCanvas } from './support/overlay';
import { signInAs } from './support/shell';

test.beforeEach(() => test.setTimeout(120000));

const unique = (prefix: string): string => `${prefix} ${randomUUID().slice(0, 8)}`;

async function addPercentage(page: Page, activity: string, value: string): Promise<void> {
    await page.getByRole('button', { name: `Toevoegen aan verdeling: ${activity}` }).click();
    const input = page.getByLabel(new RegExp(`Percentage voor .*${activity}`));
    await input.fill(value);
    await input.blur();
}

test('FR-096: a Canvas is created, structured, costed, allocated, checked, finalised and reopened', async ({ page }) => {
    const organisationName = unique('Flow');
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
    const domain = page.getByLabel('+ Domein');
    await domain.fill('Inkoop');
    await domain.press('Enter');
    const line = page.getByLabel('Activiteit of cluster toevoegen aan Inkoop, Enter om op te slaan');
    for (const name of ['Offreren', 'Bestellen', 'Betalen']) {
        await line.fill(name);
        await line.press('Enter');
        await expect(page.getByTestId('entry-row').filter({ hasText: name })).toBeVisible();
    }

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
    for (const [name, amount] of [['Offreren', '€ 76,66'], ['Bestellen', '€ 76,66'], ['Betalen', '€ 76,68']]) {
        await expect(page.getByRole('treeitem', { name: new RegExp(`^${name}`) })).toContainText(amount);
    }

    await page.goto(`/canvases/${canvas}/view?overlay=financial&year=2026`);
    const card = page.locator('[data-kind="domain"]').filter({ hasText: 'Inkoop' }).first();
    const layer = page.locator(`[data-overlay-card="${await card.getAttribute('data-block')}"]`);
    await expect(layer.locator('[data-total]')).toHaveText('€ 230 100,0% van totaal');
    await expect(layer.locator('[data-forecast]')).toHaveText('Prognose € 200 · 87% van budget');
    await expect(layer.locator('[data-counts]')).toHaveText('3 activiteiten');
    const totals = page.getByRole('complementary', { name: 'Totalen van het canvas' });
    await expect(totals).toContainText('€ 230');
    await expect(totals.locator('[data-closing]')).toHaveText('Totalen sluiten: € 230,00 + € 0,00 = € 230,00');
    await card.hover();
    await expect(page.locator('[data-hover-content]')).toContainText('Totaal € 230');
    await expectNoAxeViolations(page);

    await page.goto(`/canvases/${canvas}/cost-records?year=2026`);
    await expect(page.getByText('Alle kostenposten zijn toegewezen. Het jaar kan definitief worden.')).toBeVisible();
    await page.getByRole('button', { name: 'Jaar definitief maken' }).click();
    await expect(page.getByText('Het jaar 2026 is definitief.')).toBeVisible();
    await expectNoAxeViolations(page);

    await page.getByRole('button', { name: 'Boekjaren' }).click();
    const years = page.getByRole('dialog', { name: 'Boekjaren' });
    await expect(years.getByText(/Definitief sinds/)).toBeVisible();
    await years.getByRole('button', { name: `Heropenen 2026 ${canvasName}` }).click();
    await page.getByRole('dialog', { name: `Jaar 2026 heropenen in canvas ${canvasName}?` }).getByRole('button', { name: 'Heropenen' }).click();
    await expect(years.getByText('Open', { exact: true })).toBeVisible();
});

const rendered = async (page: Page): Promise<void> => {
    await page.locator('[data-artboard] [data-block]').first().waitFor({ state: 'visible', timeout: 10000 });
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => resolve(true))));
};

const firstRow = (selector: string) => async (page: Page): Promise<void> => {
    await page.locator(selector).first().waitFor({ state: 'visible', timeout: 10000 });
};

const screens: [string, (canvas: number, organisation: number) => string, (page: Page) => Promise<void>][] = [
    ['Bekijken with the financial overlay', (canvas) => `/canvases/${canvas}/view?overlay=financial&year=2026`, rendered],
    ['Bekijken with the modality overlay', (canvas) => `/canvases/${canvas}/view?overlay=modality&year=2026`, rendered],
    ['Invoeren', (canvas) => `/canvases/${canvas}/entry`, firstRow('nav[aria-label="Areas"] button')],
    ['Kostenposten of the Canvas', (canvas) => `/canvases/${canvas}/cost-records?year=2026`, firstRow('tbody tr')],
    ['Kostenposten of the Organisation', (_canvas, organisation) => `/organisations/${organisation}/cost-records?year=2026`, firstRow('tbody tr')],
    ['Toewijzen', (canvas) => `/canvases/${canvas}/allocation?year=2026`, firstRow('main section li button')],
    ['Systeemmeldingen', () => '/admin/system-notices', firstRow('main h1')],
];

test.describe('P-1: screens answer within one second on 500 Elements and 1000 Cost records', { annotation: { type: 'note', description: 'Timing needs an unloaded server: run this block alone with --workers=1 (npx playwright test tests/e2e/d2-flow.spec.ts -g "P-1" --workers=1).' } }, () => {
    test.describe.configure({ mode: 'serial' });

    let canvas = 0;
    let organisation = 0;

    test.beforeAll(() => {
        canvas = seedPerformanceCanvas();
        organisation = Number(/ID=(\d+)/.exec(tinker(`echo 'ID=' . App\\Models\\Canvas::find(${canvas})->organisation_id;`))?.[1]);
    });

    for (const [label, path, ready] of screens) {
        test(label, async ({ page }) => {
            await signInAs(page, 'administrator');
            const timings: number[] = [];
            for (let attempt = 0; attempt < 20; attempt += 1) {
                const started = Date.now();
                await page.goto(path(canvas, organisation), { waitUntil: 'commit' });
                await ready(page);
                timings.push(Date.now() - started);
            }
            timings.sort((a, b) => a - b);
            console.log(`${label}: ${timings.join(', ')} ms`);
            const p95 = timings[Math.ceil(timings.length * 0.95) - 1];
            expect(p95, `${label}: p95 of ${timings.length}`).toBeLessThan(1000);
        });
    }
});
