import { arch, cpus, platform, release, totalmem } from 'node:os';
import { expect, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedPerformanceCanvas } from '../support/overlay';
import { seedBulkCanvas } from '../support/view';

const machine = (browser: string): string => `${platform()} ${release()} ${arch()}, ${cpus().length} x ${cpus()[0]?.model ?? 'cpu'}, ${Math.round(totalmem() / 2 ** 30)} GiB, ${browser}`;

const shapes: [string, number, number][] = [
    ['25 Domains with 9 Activities per Area', 25, 9],
    ['125 Domains with 1 Activity per Area', 125, 1],
];

for (const [label, domains, activities] of shapes) {
    test(`Bekijken shows 500 Elements within one second: ${label}`, async ({ page, browserName }) => {
        const canvas = seedBulkCanvas(domains, activities);
        await signInAs(page, 'reader');
        await page.goto(`/canvases/${canvas.id}/view`, { waitUntil: 'commit' });
        await page.locator('[data-artboard]').waitFor({ state: 'visible', timeout: 10000 });
        await page.locator('[data-block]').nth(499).waitFor({ state: 'attached' });
        const visibleAt = await page.evaluate(() => performance.now());

        console.log(`machine: ${machine(browserName)}; ${label}; visible after ${Math.round(visibleAt)} ms`);
        expect(await page.locator('[data-block][data-kind]:not([data-kind="area"])').count()).toBe(500);
        expect(visibleAt).toBeLessThan(1000);
    });
}

test('the financial overlay shows 500 Elements and 1000 Cost records within one second', async ({ page, browserName }) => {
    const canvas = seedPerformanceCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas}/view?overlay=financial&year=2026`, { waitUntil: 'commit' });
    await page.locator('[data-artboard]').waitFor({ state: 'visible', timeout: 10000 });
    await page.locator('[data-block]').nth(499).waitFor({ state: 'attached' });
    await page.locator('[data-financial-card]').first().waitFor({ state: 'attached' });
    await page.locator('[data-figure-panel]').waitFor({ state: 'visible' });
    const visibleAt = await page.evaluate(() => performance.now());

    console.log(`machine: ${machine(browserName)}; financial overlay 500 Elements 1000 Cost records; visible after ${Math.round(visibleAt)} ms`);
    expect(visibleAt).toBeLessThan(1000);
});
