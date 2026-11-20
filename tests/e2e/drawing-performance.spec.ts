import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { expectWithinBudget, PERFORMANCE_TAG, sample } from './support/timing';
import { seedBulkCanvas } from './support/view';

const FIRST_USE_MS = 3000;

test('Tekenen first use of a 500 Element Canvas within 3 seconds', { tag: PERFORMANCE_TAG }, async ({ page, browserName }) => {
    test.setTimeout(240_000);
    const canvas = seedBulkCanvas(25, 9);
    await signInAs(page, 'administrator');

    const samples = await sample(async () => {
        await page.goto('about:blank');
        await page.goto(`/canvases/${canvas.id}/drawing`, { waitUntil: 'commit' });
        await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });

        return page.evaluate(() => performance.now());
    }, 8);

    const sorted = [...samples].sort((left, right) => left - right);
    console.log(`first use of Tekenen, ${browserName}: ${sorted.join(' ')} ms`);
    expect(sorted[Math.ceil(0.95 * sorted.length) - 1]).toBeLessThan(FIRST_USE_MS);
});

test('one automatic save of a 500 Element Canvas within one second', { tag: PERFORMANCE_TAG }, async ({ page, browserName }) => {
    test.setTimeout(240_000);
    const canvas = seedBulkCanvas(25, 9);
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Domein 1', exact: true }).first().click();
    const x = page.getByLabel('X van Domein 1');
    let at = Number(await x.inputValue());

    const samples = await sample(async () => {
        at += 2;
        const started = Date.now();
        await x.fill(String(at));
        await x.press('Enter');
        await expect(page.locator('[data-save-state]')).toHaveText('Opslaan…', { timeout: 5000 }).catch(() => undefined);
        await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen', { timeout: 5000 });

        return Date.now() - started;
    }, 10);

    expectWithinBudget(samples, 'one automatic save in Tekenen', browserName);
});
