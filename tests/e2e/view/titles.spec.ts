import { expect, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedListCanvas, seedViewCanvas } from '../support/view';

const firstLineOffset = (page: import('@playwright/test').Page, id: string): Promise<{ top: number }> =>
    page.locator(`[data-block="${id}"]`).evaluate((block) => {
        const line = block.querySelector('[data-line]') as HTMLElement;
        const frame = block.getBoundingClientRect();
        const rect = line.getBoundingClientRect();
        return { top: (rect.top - frame.top) / (frame.height / (block as HTMLElement).offsetHeight) };
    });

test('Area headers and Domain and Cluster titles sit at the top of their blocks, also when the block has children', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await expect(page.locator('[data-block="steering"]')).toBeVisible();

    for (const id of ['steering', 'primary_activities', 'supporting_activities', 'actors']) {
        expect((await firstLineOffset(page, id)).top, `Area ${id}`).toBeLessThan(14);
    }
    for (const name of ['Strategie', 'Onderwijs', 'Lessen']) {
        const block = page.locator('[data-block]').filter({ has: page.locator(`[data-line]:text-is("${name}")`) }).last();
        const id = (await block.getAttribute('data-block')) as string;
        expect((await firstLineOffset(page, id)).top, name).toBeLessThan(14);
    }
});

test('a list Group title sits above its Items instead of on top of the first Item', async ({ page }) => {
    const canvas = seedListCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    const group = page.locator('[data-block]', { has: page.locator('[data-line]:text-is("Klanten")') }).last();
    const item = page.locator('[data-block]', { has: page.locator('[data-line]:text-is("Ouders")') }).last();
    await expect(item).toBeVisible();
    const title = await group.locator('[data-line]').first().boundingBox();
    const first = await item.boundingBox();
    expect(title!.y + title!.height).toBeLessThanOrEqual(first!.y + 1);
});
