import { expect, expectNoAxeViolations, test } from '../support/guards';
import { HARNESS_URL } from '../support/ports';

test.use({ baseURL: HARNESS_URL });

const overflow = (page: import('@playwright/test').Page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

test.describe('at 320 CSS pixels', () => {
    test.use({ viewport: { width: 320, height: 640 } });

    test('the top bar with a long breadcrumb, a fiscal year and the role menu does not scroll horizontally', async ({ page }) => {
        await page.goto('/?demo=TopBar');
        await expect(page.getByRole('button', { name: 'Menu' })).toBeVisible();
        await expect(page.getByText('Boekjaar 2026')).toBeVisible();
        await expect(page.getByText('Voorbeelddata')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Administrator' })).toBeVisible();

        expect(await overflow(page)).toBeLessThanOrEqual(0);
        const bar = await page.locator('header').boundingBox();
        for (const locator of [page.getByRole('button', { name: 'Menu' }), page.getByRole('button', { name: 'Administrator' }), page.getByText('Boekjaar 2026')]) {
            const box = await locator.boundingBox();
            expect(box!.x).toBeGreaterThanOrEqual(0);
            expect(box!.x + box!.width).toBeLessThanOrEqual(bar!.x + bar!.width);
        }
    });

    test('the Menu button comes before the tabs in tab order and the bar passes axe', async ({ page }) => {
        await page.goto('/?demo=TopBar');
        const menu = page.getByRole('button', { name: 'Menu' });
        await menu.focus();
        await page.keyboard.press('Enter');
        await page.keyboard.press('Tab');
        await expect(page.getByRole('navigation', { name: 'Hoofdnavigatie' }).getByRole('link').first()).toBeFocused();
        expect(await overflow(page)).toBeLessThanOrEqual(0);
        await expectNoAxeViolations(page);
    });
});
