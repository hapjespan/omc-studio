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
    for (const name of ['Toewijzen', 'Versies']) await expect(page.getByRole('link', { name })).toHaveCount(0);
});

test('Beheer lists Gebruikers, Organisaties and Logboek', async ({ page }) => {
    await signInAs(page, 'administrator');
    await page.goto('/admin');
    await expect(page.getByRole('heading', { level: 1, name: 'Beheer' })).toBeVisible();
    await expect(page.getByRole('main').getByRole('link')).toHaveText(['Gebruikers', 'Organisaties', 'Eigenaren', 'Applicaties', 'Logboek']);
    await expectNoAxeViolations(page);
});

test('a Reader requesting Beheer gets Geen toegang', async ({ page }) => {
    await signInAs(page, 'reader');
    const response = await page.goto('/admin');
    expect(response?.status()).toBe(403);
});
