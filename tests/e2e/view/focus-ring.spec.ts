import { expect, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedViewCanvas } from '../support/view';

const renderedRing = (page: import('@playwright/test').Page): Promise<{ width: number; offset: number }> =>
    page.locator('[data-block="steering"]').evaluate((block) => {
        const style = getComputedStyle(block);
        const scale = block.getBoundingClientRect().width / (block as HTMLElement).offsetWidth;
        return { width: parseFloat(style.outlineWidth) * scale, offset: parseFloat(style.outlineOffset) * scale };
    });

test('the focus ring on the Bekijken canvas is at least 2 CSS px on screen at every zoom step', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await expect(page.locator('[data-block="steering"]')).toBeVisible();
    const seen = new Set<string>();

    const measure = async (): Promise<void> => {
        await page.locator('[data-block="steering"]').focus();
        await expect(page.locator('[data-block="steering"]')).toBeFocused();
        await expect(page.locator('[data-block="steering"]')).toHaveCSS('outline-style', 'solid');
        const ring = await renderedRing(page);
        const zoom = await page.locator('[data-zoom]').textContent();
        seen.add(zoom ?? '');
        expect(ring.width, `width at ${zoom}`).toBeGreaterThanOrEqual(1.99);
        expect(ring.offset, `offset at ${zoom}`).toBeGreaterThanOrEqual(1.99);
    };

    await page.keyboard.press('Shift');
    await measure();
    for (const button of ['Uitzoomen', 'Inzoomen'] as const) {
        const control = page.getByRole('button', { name: button });
        while ((await control.getAttribute('aria-disabled')) !== 'true') {
            await control.focus();
            await page.keyboard.press('Enter');
            await measure();
        }
    }
    expect(seen.size).toBeGreaterThan(10);
});

test('the focus ring stays at least 2 CSS px on screen after Passend maken when the fit is below 50 percent', async ({ page }) => {
    await page.setViewportSize({ width: 600, height: 400 });
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await expect(page.locator('[data-block="steering"]')).toBeVisible();
    await page.keyboard.press('Shift');
    await page.getByRole('button', { name: 'Passend maken' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('[data-zoom]')).toHaveText(/\b([1-4]?\d)\s*%/);

    await page.locator('[data-block="steering"]').focus();
    await expect(page.locator('[data-block="steering"]')).toHaveCSS('outline-style', 'solid');
    const ring = await renderedRing(page);
    expect(ring.width).toBeGreaterThanOrEqual(1.99);
    expect(ring.offset).toBeGreaterThanOrEqual(1.99);
});
