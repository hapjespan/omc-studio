import { expect, test } from '../support/guards';
import { seedPerformanceCanvas } from '../support/overlay';
import { signInAs } from '../support/shell';
import { expectWithinBudget, PERFORMANCE_TAG, sample } from '../support/timing';
import { seedBulkCanvas } from '../support/view';

const shapes: [string, number, number][] = [
    ['25 Domains with 9 Activities per Area', 25, 9],
    ['125 Domains with 1 Activity per Area', 125, 1],
];

for (const [label, domains, activities] of shapes) {
    test(`Bekijken shows 500 Elements within one second: ${label}`, { tag: PERFORMANCE_TAG }, async ({ page, browserName }) => {
        test.setTimeout(240_000);
        const canvas = seedBulkCanvas(domains, activities);
        await signInAs(page, 'reader');

        const samples = await sample(async () => {
            await page.goto('about:blank');
            await page.goto(`/canvases/${canvas.id}/view`, { waitUntil: 'commit' });
            await page.locator('[data-artboard]').waitFor({ state: 'visible', timeout: 10000 });
            await page.locator('[data-block]').nth(499).waitFor({ state: 'attached' });

            return page.evaluate(() => performance.now());
        });

        expect(await page.locator('[data-block][data-kind]:not([data-kind="area"])').count()).toBe(500);
        expectWithinBudget(samples, `Bekijken, ${label}`, browserName);
    });
}

test('the financial overlay shows 500 Elements and 1000 Cost records within one second', { tag: PERFORMANCE_TAG }, async ({ page, browserName }) => {
    test.setTimeout(240_000);
    const canvas = seedPerformanceCanvas();
    await signInAs(page, 'reader');

    const samples = await sample(async () => {
        await page.goto('about:blank');
        await page.goto(`/canvases/${canvas}/view?overlay=financial&year=2026`, { waitUntil: 'commit' });
        await page.locator('[data-artboard]').waitFor({ state: 'visible', timeout: 10000 });
        await page.locator('[data-block]').nth(499).waitFor({ state: 'attached' });
        await page.locator('[data-financial-card]').first().waitFor({ state: 'attached' });
        await page.locator('[data-figure-panel]').waitFor({ state: 'visible' });

        return page.evaluate(() => performance.now());
    });

    expectWithinBudget(samples, 'Bekijken, the financial overlay with 500 Elements and 1000 Cost records', browserName);
});
