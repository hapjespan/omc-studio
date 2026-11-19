import { expect, test, type Page } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { openProof } from './support';

const OWN = 'http://127.0.0.1:4173';

async function watch(page: Page) {
    const outside: string[] = [];
    const own: string[] = [];
    const violations: string[] = [];
    await page.context().route('**/*', (route) => {
        const url = route.request().url();
        if (url.startsWith(OWN) || url.startsWith('data:') || url.startsWith('blob:')) return route.continue();
        outside.push(url);
        return route.abort();
    });
    page.on('request', (request) => {
        if (request.url().startsWith(OWN)) own.push(new URL(request.url()).pathname);
    });
    page.on('websocket', (socket) => outside.push(`websocket ${socket.url()}`));
    page.on('console', (message) => {
        if (/Content Security Policy|Refused to/.test(message.text())) violations.push(message.text().slice(0, 200));
    });
    return { outside, own, violations };
}

test('P6: loading and using the drawing sends nothing outside the own server, under the application policy', async ({ page }, info) => {
    const seen = await watch(page);
    await page.goto('/');
    await page.waitForFunction(() => document.documentElement.dataset.ready === 'true' || document.documentElement.dataset.error, null, { timeout: 30000 });
    expect(await page.evaluate(() => document.documentElement.dataset.error ?? '')).toBe('');
    await page.evaluate(() => (window as any).proof.editor.select(['act-3']));
    await page.mouse.move(290, 802);
    await page.mouse.down();
    await page.mouse.move(400, 820, { steps: 8 });
    await page.mouse.up();
    await page.mouse.wheel(0, -300);
    await page.mouse.wheel(0, 300);
    await page.mouse.dblclick(300, 400);
    await page.keyboard.press('Control+Z');
    await page.keyboard.press('Control+Shift+Z');
    await page.evaluate(() => (window as any).proof.editor.zoomToFit());
    await page.waitForTimeout(3000);

    const policy = await page.evaluate(async () => (await fetch('/')).headers.get('content-security-policy'));
    mkdirSync('../../specs/001-omc-studio-v1/wp-e0-evidence', { recursive: true });
    writeFileSync(`../../specs/001-omc-studio-v1/wp-e0-evidence/p6-network-${info.project.name}.json`, JSON.stringify({ policy, ownRequests: [...new Set(seen.own)].sort(), outside: seen.outside, violations: seen.violations }, null, 2));
    expect(seen.outside).toEqual([]);
    expect(seen.violations).toEqual([]);
    expect(policy).toContain("script-src 'self' 'wasm-unsafe-eval'");
    expect(policy).toContain("connect-src 'self'");
    expect(seen.own.length).toBeGreaterThan(5);
});

test('P6 observation: with the font switches left at their defaults, an unbundled font still sends nothing in this setup', async ({ page }, info) => {
    const seen = await watch(page);
    await page.goto('/?control-remote-font');
    await page.waitForFunction(() => document.documentElement.dataset.ready === 'true' || document.documentElement.dataset.error, null, { timeout: 30000 });
    await page.waitForTimeout(3000);
    writeFileSync(`../../specs/001-omc-studio-v1/wp-e0-evidence/p6-observation-${info.project.name}.json`, JSON.stringify({ attempted: seen.outside, blockedByPolicy: seen.violations }, null, 2));
    expect(seen.outside).toEqual([]);
    expect(seen.violations).toEqual([]);
});
