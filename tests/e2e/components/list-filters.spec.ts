import { expect, expectNoAxeViolations, test } from '../support/guards';

test.use({ baseURL: process.env.E2E_HARNESS_URL ?? 'http://127.0.0.1:4187' });

test('a filter narrows the rows and count, shows a chip, and removing the chip restores them', async ({ page }) => {
    await page.goto('/?demo=List');
    await expect(page.getByRole('status')).toHaveText('12 resultaten');

    await page.getByLabel('Groep').selectOption('a');
    await expect(page.getByRole('status')).toHaveText('4 resultaten');
    const chip = page.getByRole('button', { name: /Filter verwijderen: Groep Groep A/ });
    await expect(chip).toBeVisible();
    await expectNoAxeViolations(page);

    await chip.click();
    await expect(page.getByRole('status')).toHaveText('12 resultaten');
    await expect(chip).toBeHidden();
});

test('the chip is operable by keyboard', async ({ page }) => {
    await page.goto('/?demo=List');
    await page.getByLabel('Groep').selectOption('b');
    const chip = page.getByRole('button', { name: /Filter verwijderen/ });

    await chip.focus();
    await page.keyboard.press('Enter');

    await expect(page.getByRole('status')).toHaveText('12 resultaten');
});
