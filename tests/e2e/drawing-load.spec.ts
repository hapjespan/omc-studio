import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

test.fixme('Tekenen loads under the narrow policy and sends nothing outside the own server', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    const requested: string[] = [];
    page.on('request', (request) => requested.push(request.url()));
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.waitForLoadState('networkidle');

    const origin = new URL(page.url()).origin;
    const outside = requested.filter((url) => !/^(data|blob):/.test(url) && new URL(url).origin !== origin);
    expect(outside, 'requests outside the own origin').toEqual([]);
    expect(requested.some((url) => url.endsWith('.wasm'))).toBe(true);
});
