import { expect, expectNoAxeViolations, test } from '../support/guards';

test.use({ baseURL: process.env.E2E_HARNESS_URL ?? 'http://127.0.0.1:4187' });

test('searching ignores case and announces the count', async ({ page }) => {
    await page.goto('/?demo=List');
    await expect(page.getByRole('status')).toHaveText('12 resultaten');

    await page.getByRole('searchbox', { name: 'Zoeken' }).fill('ZORG');

    await expect(page.getByRole('status')).toHaveText('1 resultaat');
    await expect(page.getByRole('row')).toHaveCount(2);
    await expectNoAxeViolations(page);
});

test('a header sorts ascending, then descending, and announces the direction', async ({ page }) => {
    await page.goto('/?demo=List');
    const header = page.getByRole('columnheader', { name: 'Naam' });
    const button = header.getByRole('button');

    await button.click();
    await expect(header).toHaveAttribute('aria-sort', 'ascending');
    await expect(button).toContainText('oplopend gesorteerd');
    await expect(page.getByRole('row').nth(1)).toContainText('Anna Zorg');

    await button.click();
    await expect(header).toHaveAttribute('aria-sort', 'descending');
    await expect(button).toContainText('aflopend gesorteerd');
    await expect(page.getByRole('row').nth(1)).not.toContainText('Anna Zorg');
});

test('sortable headers are reachable and operable by keyboard', async ({ page }) => {
    await page.goto('/?demo=List');
    const button = page.getByRole('columnheader', { name: 'E-mailadres' }).getByRole('button');

    await button.focus();
    await page.keyboard.press('Enter');

    await expect(page.getByRole('columnheader', { name: /E-mailadres/ })).toHaveAttribute('aria-sort', 'ascending');
    await expect(button).toBeFocused();
});
