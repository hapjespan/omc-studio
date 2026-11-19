import { expect, test } from '../support/guards';
import { expectNoAxeViolations } from '../support/guards';

test.use({ baseURL: 'http://127.0.0.1:4187' });

test('a refused save shows the messages next to the fields and moves focus', async ({ page }) => {
    await page.goto('/?demo=Fields');
    await page.getByRole('button', { name: 'Opslaan' }).click();

    const budget = page.getByLabel('Budget');
    await expect(budget).toBeFocused();
    await expect(budget).toHaveAttribute('aria-invalid', 'true');
    await expect(budget).toHaveAccessibleDescription(/Met een komma.*Budget: vul een bedrag in van € 0,00 tot € 999\.999\.999,99/);
    await expect(page.getByRole('combobox', { name: 'Soort' })).toHaveAccessibleDescription('Soort: kies een soort');
});

test('the refused form passes axe', async ({ page }) => {
    await page.goto('/?demo=Fields');
    await page.getByRole('button', { name: 'Opslaan' }).click();
    await expectNoAxeViolations(page);
});

test('entered values are kept after a refusal', async ({ page }) => {
    await page.goto('/?demo=Fields');
    await page.getByLabel('Opmerking').fill('nog steeds hier');
    await page.getByRole('button', { name: 'Opslaan' }).click();

    await expect(page.getByLabel('Opmerking')).toHaveValue('nog steeds hier');
});

test('a select is operated with the keyboard', async ({ page }) => {
    await page.goto('/?demo=Fields');
    const select = page.getByRole('combobox', { name: 'Soort' });
    await select.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('listbox')).toBeVisible();
    await page.keyboard.press('ArrowDown');
    await expect(page.getByRole('option', { name: 'Tweede' })).toHaveAttribute('data-highlighted', '');
    await page.keyboard.press('Enter');

    await expect(select).toHaveText('Tweede');
});

test('a clean form passes axe', async ({ page }) => {
    await page.goto('/?demo=Fields');
    await expectNoAxeViolations(page);
});
