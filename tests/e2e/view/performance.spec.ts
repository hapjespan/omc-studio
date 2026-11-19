import { test } from '../support/guards';
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

        expectWithinBudget(samples, `Bekijken, ${label}`, browserName);
    });
}
