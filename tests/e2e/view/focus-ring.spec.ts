import { expect, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedViewCanvas } from '../support/view';

const renderedRing = (page: import('@playwright/test').Page): Promise<{ width: number; offset: number }> =>
    page.locator('[data-block="steering"]').evaluate((block) => {
        const style = getComputedStyle(block);
        const scale = block.getBoundingClientRect().width / (block as HTMLElement).offsetWidth;
        return { width: parseFloat(style.outlineWidth) * scale, offset: parseFloat(style.outlineOffset) * scale };
    });

test('the focus ring on the Bekijken canvas stays 2 CSS px on screen at every zoom', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await expect(page.locator('[data-block="steering"]')).toBeVisible();

    for (const button of ['Uitzoomen', 'Inzoomen'] as const) {
        const control = page.getByRole('button', { name: button });
        while ((await control.getAttribute('aria-disabled')) !== 'true') await control.click();
        await page.locator('[data-block="steering"]').focus();
        await page.keyboard.press('Shift');
        await expect(page.locator('[data-block="steering"]')).toBeFocused();
        const ring = await renderedRing(page);
        expect(ring.width, `${button} width`).toBeCloseTo(2, 1);
        expect(ring.offset, `${button} offset`).toBeCloseTo(2, 1);
    }
});
