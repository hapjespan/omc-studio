import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';

test('the 404 page passes the axe scan', async ({ page }) => {
    await signInAs(page, 'reader');
    const response = await page.goto('/bestaat-niet');

    expect(response?.status()).toBe(404);
    await expect(page.getByText('Pagina niet gevonden')).toBeVisible();
    await expectNoAxeViolations(page);
});

test('the 403 page passes the axe scan', async ({ page }) => {
    await signInAs(page, 'reader');
    const response = await page.goto('/admin');

    expect(response?.status()).toBe(403);
    await expectNoAxeViolations(page);
});

test('the 419 page passes the axe scan', async ({ page }) => {
    await page.goto('/sign-in');
    const refused = await page.request.post('/sign-in', { form: { email: 'a@example.test', password: 'x' }, headers: { Accept: 'text/html', 'Sec-Fetch-Site': 'cross-site' } });

    expect(refused.status()).toBe(419);
    await page.route('**/refused-419', async (route) => route.fulfill({ status: 419, headers: refused.headers(), body: await refused.text() }));
    await page.goto('/refused-419');
    await expect(page.getByRole('heading', { name: 'Pagina verlopen' })).toBeVisible();
    await expectNoAxeViolations(page);
});
