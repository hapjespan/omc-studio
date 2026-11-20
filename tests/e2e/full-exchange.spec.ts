import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';

test('an Administrator finds the page under Beheer and an import into a filled application is refused', async ({ page }) => {
    await signInAs(page, 'administrator');
    await page.goto('/admin');
    await page.getByRole('link', { name: 'Volledige export en import', exact: true }).click();

    await expect(page.getByRole('heading', { level: 1, name: 'Volledige export en import' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Alles exporteren' })).toHaveAttribute('href', '/admin/export/full');
    await expect(page.getByRole('button', { name: 'Controleren' })).toBeDisabled();
    await expectNoAxeViolations(page);

    await page.getByLabel('Bestand met volledige export').setInputFiles({ name: 'full.json', mimeType: 'application/json', buffer: Buffer.from(JSON.stringify({ format: 'omc-studio-full', format_version: 1, organisations: [], templates: [] })) });
    await page.getByRole('button', { name: 'Controleren' }).click();
    await expect(page.getByRole('main').getByRole('status')).toContainText('Volledige import kan alleen in een lege applicatie. Verwijder eerst alle organisaties');
    await expect(page.getByRole('button', { name: 'Importeren' })).toBeDisabled();
});

test('a Reader gets no access to the page', async ({ page }) => {
    await signInAs(page, 'reader');
    const response = await page.goto('/admin/exchange');
    expect(response?.status()).toBe(403);
});
