import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

test('a faulty file disables Importeren, a clean export imports into a new organisation', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    const exported = await (await page.request.get(`/canvases/${canvas.id}/export/json`)).text();

    await page.goto('/overview');
    await page.getByRole('button', { name: 'Importeren uit JSON' }).click();
    await page.getByLabel('Naam van de nieuwe organisatie').fill(`Org ${randomUUID().slice(0, 8)}`);
    await page.getByLabel('Naam van het canvas').fill(`Import ${randomUUID().slice(0, 8)}`);

    await page.getByLabel('JSON-bestand').setInputFiles({ name: 'bad.json', mimeType: 'application/json', buffer: Buffer.from('{}') });
    await page.getByRole('button', { name: 'Controleren' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'kies een JSON-export' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Importeren', exact: true })).toBeDisabled();
    await expectNoAxeViolations(page);

    await page.getByLabel('JSON-bestand').setInputFiles({ name: 'ok.json', mimeType: 'application/json', buffer: Buffer.from(exported) });
    await page.getByRole('button', { name: 'Controleren' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Controle klaar: geen fouten' })).toBeVisible();
    await page.getByRole('button', { name: 'Importeren', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Canvas is geïmporteerd' })).toBeVisible();
});

test('a Reader finds no import', async ({ page }) => {
    await signInAs(page, 'reader');
    await page.goto('/overview');
    await expect(page.getByRole('button', { name: 'Importeren uit JSON' })).toHaveCount(0);
});
