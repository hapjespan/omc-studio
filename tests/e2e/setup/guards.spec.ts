import { expect, test } from '@playwright/test';
import { expectNoAxeViolations, installGuards, test as guarded } from '../support/guards';

const origin = 'http://app.test';
const csp = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'";

async function serve(page: import('@playwright/test').Page, body: string) {
    await page.route('**/*', (route) => {
        const url = route.request().url();
        if (url.startsWith(origin)) {
            return route.fulfill({ contentType: 'text/html', headers: { 'content-security-policy': csp }, body: `<!doctype html><html lang="nl"><head><title>Test</title></head><body><main>${body}</main></body></html>` });
        }
        return route.fulfill({ status: 200, contentType: 'text/plain', body: '' });
    });
}

test('a request outside the own origin fails the guard', async ({ page }) => {
    const guards = installGuards(page, origin);
    await serve(page, '<img src="https://cdn.example.org/x.png" alt="">');
    await page.goto(`${origin}/`);
    await page.waitForLoadState('networkidle');
    expect(() => guards.assertNoOutsideRequest()).toThrow();
});

test('an inline script fails the guard', async ({ page }) => {
    const guards = installGuards(page, origin);
    await serve(page, '<script>window.x = 1</script>');
    await page.goto(`${origin}/`);
    await expect(guards.assertNoCspViolation()).rejects.toThrow();
});

test('an axe violation fails the check', async ({ page }) => {
    await serve(page, '<img src="/a.png">');
    await page.goto(`${origin}/`);
    await expect(expectNoAxeViolations(page)).rejects.toThrow();
});

test('a clean page passes every guard', async ({ page }) => {
    const guards = installGuards(page, origin);
    await serve(page, '<h1>OMC Studio</h1>');
    await page.goto(`${origin}/`);
    guards.assertNoOutsideRequest();
    await guards.assertNoCspViolation();
    await expectNoAxeViolations(page);
});

guarded('the automatic fixture fails a test that reaches outside', async ({ page, baseURL }) => {
    guarded.fail();
    await page.route('**/*', (route) => {
        const url = route.request().url();
        if (url.startsWith(baseURL as string)) {
            return route.fulfill({ contentType: 'text/html', body: '<!doctype html><html lang="nl"><head><title>T</title></head><body><main><img src="https://cdn.example.org/x.png" alt=""></main></body></html>' });
        }
        return route.fulfill({ status: 200, contentType: 'text/plain', body: '' });
    });
    await page.goto('/');
    await page.waitForLoadState('networkidle');
});
