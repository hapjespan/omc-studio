import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';

test('an Administrator without a Canvas sees Overzicht and Beheer and walks them with the keyboard', async ({ page }) => {
    await signInAs(page, 'administrator');
    const nav = page.getByRole('navigation', { name: 'Hoofdnavigatie' });
    await expect(nav.getByRole('link')).toHaveText(['Overzicht', 'Beheer']);
    await expect(nav.getByRole('link', { name: 'Overzicht' })).toHaveAttribute('aria-current', 'page');
    await expect(nav.getByRole('link', { name: 'Beheer' })).not.toHaveAttribute('aria-current', 'page');

    await nav.getByRole('link', { name: 'Beheer' }).focus();
    await page.keyboard.press('Enter');
    await expect(nav.getByRole('link', { name: 'Beheer' })).toHaveAttribute('aria-current', 'page');
    await expect(nav.getByRole('link', { name: 'Overzicht' })).not.toHaveAttribute('aria-current', 'page');
    const underline = await nav.getByRole('link', { name: 'Beheer' }).evaluate((element) => getComputedStyle(element).borderBottomWidth);
    expect(underline).toBe('2px');
    await expectNoAxeViolations(page);
});

test('a Reader sees only Overzicht without a Canvas', async ({ page }) => {
    await signInAs(page, 'reader');
    await expect(page.getByRole('navigation', { name: 'Hoofdnavigatie' }).getByRole('link')).toHaveText(['Overzicht']);
});

test('no tab of a later delivery appears', async ({ page }) => {
    await signInAs(page, 'administrator');
    for (const name of ['Kostenposten', 'Toewijzen', 'Versies']) await expect(page.getByRole('link', { name })).toHaveCount(0);
});
