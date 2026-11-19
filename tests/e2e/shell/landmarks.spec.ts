import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';

test('the skip link comes first and moves focus to the main content', async ({ page }) => {
    await signInAs(page, 'administrator');
    await page.keyboard.press('Tab');
    const skip = page.getByRole('link', { name: 'Naar hoofdinhoud' });
    await expect(skip).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main')).toBeFocused();
});

test('every screen has the landmarks, one level-1 heading and a unique title', async ({ page }) => {
    await signInAs(page, 'administrator');
    await expect(page.getByRole('banner')).toHaveCount(1);
    await expect(page.getByRole('main')).toHaveCount(1);
    await expect(page.locator('#left-panel')).toBeHidden();
    await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
    await expect(page).toHaveTitle('Overzicht - OMC Studio');
    await expectNoAxeViolations(page);

    await page.goto('/account');
    await expect(page).toHaveTitle('Mijn account - OMC Studio');
    await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
    await expectNoAxeViolations(page);
});
