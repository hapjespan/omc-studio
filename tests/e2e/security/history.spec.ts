import { expect, test } from '../support/guards';

test('the browser history is encrypted with a key in session storage', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    const key = await page.evaluate(() => window.sessionStorage.getItem('historyKey'));
    expect(key, 'the Inertia history key in session storage').not.toBeNull();

    const entry = await page.evaluate(() => {
        const state = window.history.state as { page?: unknown } | null;
        return {
            kind: Object.prototype.toString.call(state?.page),
            component: (state?.page as { component?: string } | undefined)?.component ?? null,
        };
    });
    expect(entry.kind).toBe('[object ArrayBuffer]');
    expect(entry.component, 'readable page data in the Back stack').toBeNull();
});
