import { expect, test } from './support/guards';
import { openDrawing } from './support/drawing';
import { expectWithinBudget, PERFORMANCE_TAG, sample } from './support/timing';
import { seedBulkCanvas } from './support/view';

test(`an Area fill change repaints 500 Elements within one second ${PERFORMANCE_TAG}`, { tag: PERFORMANCE_TAG }, async ({ page, browserName }) => {
    test.setTimeout(240_000);
    const layers = await openDrawing(page, seedBulkCanvas(25, 9));
    await layers.getByRole('button', { name: 'Primaire activiteiten', exact: true }).click();
    const fill = page.getByLabel('Vulling van Primaire activiteiten', { exact: true });
    await expect(fill).toBeVisible();
    let turn = 0;

    const samples = await sample(async () => {
        const colour = ['#223344', '#EEDDCC'][turn++ % 2];
        return fill.evaluate(async (input, hex) => {
            const field = input as HTMLInputElement;
            field.value = hex;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            const start = performance.now();
            field.dispatchEvent(new Event('change', { bubbles: true }));
            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            return performance.now() - start;
        }, colour);
    });

    await expect(fill).toHaveValue('#EEDDCC');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen', { timeout: 30000 });
    expectWithinBudget(samples, 'Tekenen, Area fill change with 500 Elements', browserName);
});
