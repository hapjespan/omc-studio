import { expect, test } from '../support/guards';
import { expectNoAxeViolations } from '../support/guards';

test.use({ baseURL: process.env.E2E_HARNESS_URL ?? 'http://127.0.0.1:4187' });

test('a posted message lands in the polite live region and focus stays', async ({ page }) => {
    await page.goto('/?demo=Status');
    const button = page.locator('#post');
    await button.focus();
    await button.click();

    const region = page.getByRole('status');
    await expect(region).toHaveText('Alles opgeslagen');
    await expect(region).toHaveAttribute('aria-live', 'polite');
    await expect(button).toBeFocused();
    await expectNoAxeViolations(page);
});

test('the focus ring is two pixels, dark and light, on every Default colour', async ({ page }) => {
    await page.goto('/?demo=Status');
    const swatches = page.locator('.swatch');
    const count = await swatches.count();
    expect(count).toBeGreaterThan(5);

    for (let i = 0; i < count; i++) {
        await swatches.nth(i).focus();
        await page.keyboard.press('Shift+Tab');
        await page.keyboard.press('Tab');
        const ring = await swatches.nth(i).evaluate((element) => {
            const style = getComputedStyle(element);
            return { width: style.outlineWidth, colour: style.outlineColor, shadow: style.boxShadow };
        });
        expect(ring.width).toBe('2px');
        expect(ring.colour).toBe('rgb(23, 27, 33)');
        expect(ring.shadow).toContain('rgb(255, 255, 255)');
    }
});
