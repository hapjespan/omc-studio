import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedBulkCanvas, seedViewCanvas } from '../support/view';

for (const role of ['administrator', 'reader'] as const) {
    test(`a ${role} sees the Rand A3 edge, the hatched overflow and the notice before the canvas`, async ({ page }) => {
        const canvas = seedBulkCanvas(40, 2);
        await signInAs(page, role);
        await page.goto(`/canvases/${canvas.id}/view`);

        const notice = page.locator('[data-overflow-notice]');
        await expect(notice).toHaveText(/^Niet alles past op het A3-tekenvlak: \d+ blokken vallen erbuiten$/);
        await expect(page.locator('[data-artboard-edge]')).toContainText('Rand A3');
        await expect(page.locator('[data-overflow-area]')).toHaveCount(1);
        const order = await page.evaluate(() => {
            const all = [...document.querySelectorAll('[data-overflow-notice], [data-artboard]')];
            return all.map((node) => (node.hasAttribute('data-artboard') ? 'artboard' : 'notice'));
        });
        expect(order).toEqual(['notice', 'artboard']);
        await expect(page.getByRole('button', { name: 'Domein 40', exact: true })).toHaveCount(2);
        await expectNoAxeViolations(page);
    });
}

test('a Canvas that fits shows the edge without notice or hatch', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await expect(page.locator('[data-artboard-edge]')).toBeVisible();
    await expect(page.locator('[data-overflow-notice]')).toHaveCount(0);
    await expect(page.locator('[data-overflow-area]')).toHaveCount(0);
});
