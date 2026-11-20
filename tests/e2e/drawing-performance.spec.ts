import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { expectWithinBudget, PERFORMANCE_TAG, sample } from './support/timing';
import { seedBulkCanvas } from './support/view';

const FIRST_USE_MS = 3000;

test('Tekenen first use of a 500 Element Canvas within 3 seconds', { tag: PERFORMANCE_TAG }, async ({ page, browserName }) => {
    test.setTimeout(480_000);
    const canvas = seedBulkCanvas(25, 9);
    await signInAs(page, 'administrator');

    const samples = await sample(async () => {
        await page.goto('about:blank');
        await page.goto(`/canvases/${canvas.id}/drawing`, { waitUntil: 'commit' });
        await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });

        return page.evaluate(() => performance.now());
    });

    expectWithinBudget(samples, 'first use of Tekenen, 500 Elements', browserName, FIRST_USE_MS);
});

test('one automatic save of a 500 Element Canvas within one second', { tag: PERFORMANCE_TAG }, async ({ page, browserName }) => {
    test.setTimeout(480_000);
    const canvas = seedBulkCanvas(25, 9);
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Domein 1', exact: true }).first().click();
    const x = page.getByLabel('X van Domein 1');
    let at = Number(await x.inputValue());

    const samples = await sample(async () => {
        at += 2;
        const saved = page.waitForResponse((response) => response.request().method() === 'PUT' && response.url().endsWith('/layout'));
        const started = Date.now();
        await x.fill(String(at));
        await x.press('Enter');
        await saved;

        return Date.now() - started;
    });

    expect(samples).toHaveLength(20);
    expectWithinBudget(samples, 'one automatic save in Tekenen, 500 Elements', browserName);
});
