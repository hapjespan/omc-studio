import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';

const horizontalOverflow = (page: import('@playwright/test').Page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

test.describe('at 320 CSS pixels', () => {
    test.use({ viewport: { width: 320, height: 640 } });

    test('the tabs collapse behind a Menu button that opens them without horizontal scrolling', async ({ page }) => {
        await signInAs(page, 'administrator');
        const menu = page.getByRole('button', { name: 'Menu' });
        const tabs = page.getByRole('navigation', { name: 'Hoofdnavigatie' });

        await expect(menu).toBeVisible();
        await expect(menu).toHaveAttribute('aria-expanded', 'false');
        await expect(tabs).toBeHidden();
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);

        await menu.focus();
        await page.keyboard.press('Enter');
        await expect(menu).toHaveAttribute('aria-expanded', 'true');
        await expect(tabs.getByRole('link')).toHaveText(['Overzicht', 'Beheer']);
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
        await expectNoAxeViolations(page);
    });

    test('at 200% text the shell still does not scroll horizontally', async ({ page }) => {
        await signInAs(page, 'administrator');
        await page.evaluate(() => (document.documentElement.style.fontSize = '200%'));
        await page.getByRole('button', { name: 'Menu' }).click();
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);

        await page.goto('/account');
        await page.evaluate(() => (document.documentElement.style.fontSize = '200%'));
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
    });

    test.describe('on the harness', () => {
    test.use({ baseURL: process.env.E2E_HARNESS_URL ?? 'http://127.0.0.1:4187' });

    test('the detail panel covers the main content instead of sitting next to it', async ({ page }) => {
        await page.goto('/?demo=Shell');
        await page.getByRole('button', { name: 'Eerste rij' }).click();

        const panel = await page.locator('#detail-panel').boundingBox();
        expect(panel?.x).toBeLessThanOrEqual(1);
        expect(panel?.width).toBeGreaterThanOrEqual(318);
        await expect(page.locator('#main')).toBeHidden();
        await expect(page.getByRole('button', { name: 'Eerste rij' })).toBeHidden();
    });
    });
});

test('at a wide screen the tabs show without the Menu button', async ({ page }) => {
    await signInAs(page, 'administrator');
    await expect(page.getByRole('button', { name: 'Menu' })).toBeHidden();
    await expect(page.getByRole('navigation', { name: 'Hoofdnavigatie' })).toBeVisible();
});
