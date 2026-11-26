import { expect, test } from '../support/guards';
import { createCanvas, signInAs } from '../support/shell';

test('an Administrator opening a Canvas is sent to Invoeren and a Reader to Bekijken', async ({ page }) => {
    const canvas = createCanvas();

    await signInAs(page, 'administrator');
    const administrator = await page.request.get(`/canvases/${canvas}`, { maxRedirects: 0 });
    expect(administrator.status()).toBe(302);
    expect(administrator.headers()['location']).toMatch(new RegExp(`/canvases/${canvas}/entry$`));
});

test('a Reader opening a Canvas is sent to Bekijken', async ({ page }) => {
    const canvas = createCanvas();

    await signInAs(page, 'reader');
    const reader = await page.request.get(`/canvases/${canvas}`, { maxRedirects: 0 });
    expect(reader.status()).toBe(302);
    expect(reader.headers()['location']).toMatch(new RegExp(`/canvases/${canvas}/view$`));
});
