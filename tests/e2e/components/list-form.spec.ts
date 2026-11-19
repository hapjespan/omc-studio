import { expect, expectNoAxeViolations, test } from '../support/guards';

test.use({ baseURL: process.env.E2E_HARNESS_URL ?? 'http://127.0.0.1:4187' });

test('Nieuw opens an empty form and saving adds the row without leaving the screen', async ({ page }) => {
    await page.goto('/?demo=List');
    const opener = page.getByRole('button', { name: 'Nieuw', exact: true });
    await opener.click();

    const panel = page.getByRole('dialog', { name: 'Nieuw', exact: true });
    await expect(panel.getByLabel('Naam')).toHaveValue('');
    await expectNoAxeViolations(page);

    await panel.getByLabel('Naam').fill('Zara Nieuw');
    await panel.getByRole('button', { name: 'Opslaan' }).click();

    await expect(panel).toBeHidden();
    await expect(page.getByRole('status')).toHaveText('13 resultaten');
    await expect(page.getByRole('button', { name: 'Zara Nieuw', exact: true })).toBeVisible();
    await expect(opener).toBeFocused();
    expect(new URL(page.url()).pathname).toBe('/');
});

test('a row opens its form in the same panel and saving updates the row', async ({ page }) => {
    await page.goto('/?demo=List');
    const rowButton = page.getByRole('button', { name: 'Anna Zorg', exact: true });
    await rowButton.click();

    const panel = page.getByRole('dialog', { name: 'Anna Zorg' });
    await expect(panel.getByLabel('Naam')).toHaveValue('Anna Zorg');
    await panel.getByLabel('Naam').fill('Anna Renamed');
    await panel.getByRole('button', { name: 'Opslaan' }).click();

    await expect(page.getByRole('button', { name: 'Anna Renamed', exact: true })).toBeVisible();
    await expect(page.getByRole('status')).toHaveText('12 resultaten');
});

test('saving with an empty required field is refused with a summary and a linked error', async ({ page }) => {
    await page.goto('/?demo=List');
    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    const panel = page.getByRole('dialog', { name: 'Nieuw', exact: true });

    await panel.getByRole('button', { name: 'Opslaan' }).click();

    await expect(panel.getByRole('link', { name: 'Naam: vul een naam in' })).toBeVisible();
    await expect(panel.getByLabel('Naam')).toHaveAttribute('aria-invalid', 'true');
    await expect(panel).toBeVisible();
});

test('Escape closes a clean form and returns focus to the row that opened it', async ({ page }) => {
    await page.goto('/?demo=List');
    const rowButton = page.getByRole('button', { name: 'Anna Zorg', exact: true });
    await rowButton.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('dialog', { name: 'Anna Zorg' })).toBeVisible();

    await page.keyboard.press('Escape');

    await expect(page.getByRole('dialog')).toBeHidden();
    await expect(rowButton).toBeFocused();
});
