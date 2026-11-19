import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedBulkCanvas, seedViewCanvas } from './support/view';

test('the menu of a Reader holds the PNG and print entries', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.getByRole('button', { name: 'Exporteren' }).click();
    await expect(page.getByRole('menuitem', { name: 'Afdrukken' })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Exporteren als PNG' })).toBeVisible();
});

test('print media puts the Artboard at the top of the first A3 page and hides the shell', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.evaluate(() => (window.print = () => undefined));
    await page.getByRole('button', { name: 'Exporteren' }).click();
    await page.getByRole('menuitem', { name: 'Afdrukken' }).click();
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('header.shell-top')).toBeHidden();
    await expect(page.locator('#detail-panel')).toBeHidden();
    await expect(page.getByRole('button', { name: 'Exporteren' })).toBeHidden();
    await expect(page.getByRole('heading', { level: 1 })).toBeHidden();
    const box = await page.locator('[data-artboard]').boundingBox();
    expect(box?.y).toBeCloseTo(0, 0);
    expect(box?.height).toBeGreaterThan(1100);
    expect(box?.height).toBeLessThan(1130);
});

test('print media leaves other pages alone', async ({ page }) => {
    await signInAs(page, 'administrator');
    await page.goto('/overview');
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('header.shell-top')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
});

test('the warning offers Toch afdrukken and prints only from that button', async ({ page }) => {
    const canvas = seedBulkCanvas(12, 12);
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.evaluate(() => {
        (window as unknown as { printed: number }).printed = 0;
        window.print = () => ((window as unknown as { printed: number }).printed += 1);
    });
    await page.getByRole('button', { name: 'Exporteren' }).click();
    await page.getByRole('menuitem', { name: 'Afdrukken' }).click();
    await expect(page.getByTestId('export-note')).toContainText(/Niet alles past op het A3-tekenvlak: \d+ blokken komen op een volgende pagina/);
    expect(await page.evaluate(() => (window as unknown as { printed: number }).printed)).toBe(0);
    await page.getByRole('button', { name: 'Toch afdrukken' }).click();
    expect(await page.evaluate(() => (window as unknown as { printed: number }).printed)).toBe(1);
});

test('without overflow the print dialog opens straight away', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.evaluate(() => {
        (window as unknown as { printed: number }).printed = 0;
        window.print = () => ((window as unknown as { printed: number }).printed += 1);
    });
    await page.getByRole('button', { name: 'Exporteren' }).click();
    await page.getByRole('menuitem', { name: 'Afdrukken' }).click();
    expect(await page.evaluate(() => (window as unknown as { printed: number }).printed)).toBe(1);
});
