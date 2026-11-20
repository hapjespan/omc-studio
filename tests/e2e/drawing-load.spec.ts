import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

test('Tekenen loads on demand under the narrow policy, within 3 seconds, sending nothing outside the own server', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    const requested: string[] = [];
    page.on('request', (request) => requested.push(request.url()));
    const started = Date.now();
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    const seconds = (Date.now() - started) / 1000;

    const origin = new URL(page.url()).origin;
    const outside = requested.filter((url) => !/^(data|blob):/.test(url) && new URL(url).origin !== origin);
    expect(outside, 'requests outside the own origin').toEqual([]);
    expect(requested.some((url) => url.endsWith('.wasm'))).toBe(true);
    expect(requested.some((url) => /ttf/.test(url))).toBe(true);
    expect(seconds, 'seconds to first use').toBeLessThan(3);
});

test('the Tekenen bundle is not part of the other pages', async ({ page }) => {
    await signInAs(page, 'reader');
    const requested: string[] = [];
    page.on('request', (request) => requested.push(request.url()));
    const scripts: string[] = [];
    page.on('response', async (response) => {
        if (response.url().endsWith('.js')) scripts.push(await response.text());
    });
    await page.goto('/overview');
    await page.waitForLoadState('networkidle');
    expect(requested.filter((url) => /edit-engine|canvaskit/.test(url))).toEqual([]);
    expect(scripts.length).toBeGreaterThan(0);
    expect(scripts.filter((source) => source.includes('reparentNode') || source.includes('createEditor'))).toEqual([]);
});
