import { expect, test } from './support/guards';
import { createCanvas, signInAs } from './support/shell';

const narrow = "script-src 'self';";
const relaxed = "script-src 'self' 'wasm-unsafe-eval';";
const images = "img-src 'self' data: blob:;";

test('a page outside Tekenen keeps the narrow policy and raises no violation', async ({ page }) => {
    const response = await page.goto('/sign-in');
    await page.waitForLoadState('networkidle');

    const policy = response?.headers()['content-security-policy'] ?? '';
    expect(policy, 'the policy of a page outside Tekenen').toContain(narrow);
    expect(policy).toContain(images);
});

test.fixme('Tekenen carries the WebAssembly relaxation and raises no violation, pending the Drawing page of T193', async ({ page }) => {
    const canvas = createCanvas();
    await signInAs(page, 'administrator');
    const response = await page.goto(`/canvases/${canvas}/drawing`);
    await page.waitForLoadState('networkidle');

    const policy = response?.headers()['content-security-policy'] ?? '';
    expect(policy, 'the policy of Tekenen').toContain(relaxed);
    expect(policy).toContain(images);
});
