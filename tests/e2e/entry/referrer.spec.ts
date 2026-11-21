import { randomUUID } from 'node:crypto';
import { expect, test } from '../support/guards';
import { createCanvas, signInAs } from '../support/shell';

test('saving on an existing Canvas opened from its Overzicht card keeps the Administrator on Invoeren', async ({ page }) => {
    const token = randomUUID().slice(0, 8);
    const name = `Referrer ${token}`;
    const canvas = createCanvas({ organisation: `Organisatie ${token}`, canvas: name });
    expect(Number.isFinite(canvas)).toBe(true);

    await signInAs(page, 'administrator');
    await page.getByRole('link', { name, exact: true }).click();
    await page.waitForURL(`**/canvases/${canvas}/entry`);

    await page.getByRole('navigation', { name: 'Areas' }).getByRole('button', { name: /Primaire activiteiten/ }).click();
    await page.getByRole('button', { name: 'Domein', exact: true }).click();
    await page.getByLabel('+ Domein').fill(`Inkoop ${token}`);
    await page.getByLabel('+ Domein').press('Enter');

    await expect(page.getByTestId('entry-row').filter({ hasText: `Inkoop ${token}` })).toBeVisible();
    expect(new URL(page.url()).pathname).toBe(`/canvases/${canvas}/entry`);

    await page.getByRole('navigation', { name: 'Areas' }).getByRole('button', { name: /Koers/ }).click();
    const direction = page.getByRole('region', { name: 'Koers' });
    await direction.getByLabel('Missie', { exact: true }).fill(`Missie ${token}`);
    const saved = page.waitForResponse((response) => response.request().method() === 'PUT' && response.url().includes('/statements/'));
    await direction.getByRole('button', { name: 'Opslaan', exact: true }).first().click();
    expect((await saved).status()).toBeLessThan(400);
    await expect(page.getByText('Missie is opgeslagen')).toBeAttached();

    await expect(direction.getByLabel('Missie', { exact: true })).toHaveValue(`Missie ${token}`);
    expect(new URL(page.url()).pathname).toBe(`/canvases/${canvas}/entry`);
});
