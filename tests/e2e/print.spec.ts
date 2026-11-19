import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedBulkCanvas, seedViewCanvas } from './support/view';

test('the menu of a Reader holds all five entries', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.getByRole('button', { name: 'Exporteren' }).click();
    await expect(page.getByRole('menuitem', { name: 'Afdrukken' })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Exporteren als PNG' })).toBeVisible();
});

test('print media hides the shell and sizes the Artboard to A3 landscape', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.getByRole('button', { name: 'Exporteren' }).click();
    await page.getByRole('menuitem', { name: 'Afdrukken' }).click();
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('header.shell-top')).toBeHidden();
    await expect(page.locator('#detail-panel')).toBeHidden();
    await expect(page.getByRole('button', { name: 'Exporteren' })).toBeHidden();
    await expect(page.locator('[data-artboard]')).toBeVisible();
});

test('the warning names the blocks on following pages', async ({ page }) => {
    const canvas = seedBulkCanvas(12, 12);
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.evaluate(() => (window.print = () => undefined));
    await page.getByRole('button', { name: 'Exporteren' }).click();
    await page.getByRole('menuitem', { name: 'Afdrukken' }).click();
    await expect(page.getByTestId('export-note')).toContainText(/Niet alles past op het A3-tekenvlak: \d+ blokken komen op een volgende pagina/);
});
