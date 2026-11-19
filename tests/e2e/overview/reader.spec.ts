import { expect, expectNoAxeViolations, test } from '../support/guards';
import { seedOrganisation, unique } from '../support/overview';
import { signInAs } from '../support/shell';

test('a Reader sees cards that open Bekijken and no create, copy, archive or delete action', async ({ page }) => {
    const canvas = unique('Model');
    seedOrganisation(unique('Noord'), [canvas]);
    await signInAs(page, 'reader');
    await page.goto('/overview');

    await expect(page.getByRole('link', { name: canvas, exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Nieuw canvas' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: /^Acties voor/ })).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Sjablonen' })).toHaveCount(0);
    await expectNoAxeViolations(page);

    await page.getByRole('link', { name: canvas, exact: true }).click();
    await page.waitForURL(/\/canvases\/\d+\/view/);
});

test('a Reader request to a write route is refused', async ({ page }) => {
    await signInAs(page, 'reader');
    const cookies = await page.context().cookies();
    const token = decodeURIComponent(cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value ?? '');
    const response = await page.request.post('/canvases', { form: { source: 'copy', name: 'X' }, headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token } });

    expect(response.status()).toBe(403);
});
