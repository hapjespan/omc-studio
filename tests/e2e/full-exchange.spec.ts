import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';

test('an Administrator finds the page under Beheer and an import into a filled application is refused', async ({ page }) => {
    await signInAs(page, 'administrator');
    await page.goto('/admin');
    await page.getByRole('link', { name: 'Volledige export en import', exact: true }).click();

    await expect(page.getByRole('heading', { level: 1, name: 'Volledige export en import' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Alles exporteren' })).toBeEnabled();
    await expect(page.getByRole('button', { name: 'Controleren' })).toBeDisabled();
    await expectNoAxeViolations(page);

    await page.getByLabel('Bestand met volledige export').setInputFiles({ name: 'full.json', mimeType: 'application/json', buffer: Buffer.from(JSON.stringify({ format: 'omc-studio-full', format_version: 1, organisations: [], templates: [] })) });
    await page.getByRole('button', { name: 'Controleren' }).click();
    await expect(page.getByRole('main').getByRole('status')).toContainText('Volledige import kan alleen in een lege applicatie. Verwijder eerst alle organisaties');
    await expect(page.getByRole('button', { name: 'Importeren' })).toBeDisabled();
});

test('Alles exporteren asks for the full export by mouse and by keyboard and says how many damaged versions were left out', async ({ page }) => {
    await signInAs(page, 'administrator');
    await page.goto('/admin/exchange');
    const exportAll = page.getByRole('button', { name: 'Alles exporteren' });
    const asked = (): Promise<unknown> => page.waitForRequest((request) => new URL(request.url()).pathname === '/admin/export/full' && request.method() === 'GET');

    await Promise.all([asked(), exportAll.click()]);
    await expect(page.getByTestId('full-export-result')).toHaveText('Geëxporteerd: alle versies zijn opgenomen');

    await exportAll.focus();
    await Promise.all([asked(), page.keyboard.press('Enter')]);
    await expect(page.getByTestId('full-export-result')).toBeVisible();
});

test('Alles exporteren says that the export failed when the server cannot be reached', async ({ page }) => {
    await signInAs(page, 'administrator');
    await page.goto('/admin/exchange');
    await page.route('**/admin/export/full', (route) => route.abort());

    await page.getByRole('button', { name: 'Alles exporteren' }).click();

    await expect(page.getByTestId('full-export-result')).toHaveText(/niet/);
});

test('a Reader gets no access to the page', async ({ page }) => {
    await signInAs(page, 'reader');
    const response = await page.goto('/admin/exchange');
    expect(response?.status()).toBe(403);
});
