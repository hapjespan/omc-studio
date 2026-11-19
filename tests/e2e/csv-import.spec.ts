import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

const file = (content: string) => ({ name: 'import.csv', mimeType: 'text/csv', buffer: Buffer.from(content) });

test('the structure import on Invoeren blocks Importeren on errors and imports a clean file', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/entry`);
    await page.getByRole('button', { name: 'Importeren uit CSV' }).click();

    await page.getByLabel('CSV-bestand').setInputFiles(file('area;domein;cluster;activiteit;omschrijving;modaliteit\nonbekend;Nieuw;;;;\n'));
    await page.getByRole('button', { name: 'Controleren' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Controle klaar: 1 fouten' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Importeren', exact: true })).toBeDisabled();
    await expectNoAxeViolations(page);

    await page.getByLabel('CSV-bestand').setInputFiles(file('area;domein;cluster;activiteit;omschrijving;modaliteit\nsteering;Nieuw domein;;;;\n'));
    await page.getByRole('button', { name: 'Controleren' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Controle klaar: geen fouten' })).toBeVisible();
    await page.getByRole('button', { name: 'Importeren', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Geïmporteerd' })).toBeVisible();
});

test('the allocation import on Toewijzen blocks Importeren on errors', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/allocation`);
    await page.getByRole('button', { name: 'Toewijzingen importeren' }).click();

    await page.getByLabel('CSV-bestand').setInputFiles(file('kostenpost_code;jaar;doel_pad;percentage\nNOPE;2026;Onbekend;100,00\n'));
    await page.getByRole('button', { name: 'Controleren' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Controle klaar' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Importeren', exact: true })).toBeDisabled();
    await expectNoAxeViolations(page);
});
