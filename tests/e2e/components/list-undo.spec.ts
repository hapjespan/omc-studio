import { expect, expectNoAxeViolations, test } from '../support/guards';

test.use({ baseURL: process.env.E2E_HARNESS_URL ?? 'http://127.0.0.1:4187' });

async function deleteRow(page: import('@playwright/test').Page, name: string): Promise<void> {
    await page.getByRole('button', { name: `Verwijderen ${name}`, exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Verwijderen', exact: true }).click();
}

test('the message offers Ongedaan maken with focus on the button, and activating it restores the row', async ({ page }) => {
    await page.goto('/?demo=List&undo=1');
    await deleteRow(page, 'Persoon C');

    const undo = page.getByRole('button', { name: 'Ongedaan maken' });
    await expect(page.getByText('Persoon C verwijderd')).toBeVisible();
    await expect(undo).toBeFocused();
    await expectNoAxeViolations(page);

    await undo.click();

    await expect(page.getByRole('button', { name: 'Persoon C', exact: true })).toBeVisible();
    await expect(page.getByRole('searchbox', { name: 'Zoeken' })).toBeFocused();
});

test('the countdown runs ten seconds, pauses on hover and focus, and focus goes to the search field when it closes', async ({ page }) => {
    await page.clock.install();
    await page.goto('/?demo=List&undo=1');
    await deleteRow(page, 'Persoon C');
    const undo = page.getByRole('button', { name: 'Ongedaan maken' });

    await page.clock.runFor(9_000);
    await expect(undo).toBeVisible();

    await undo.hover();
    await page.clock.runFor(30_000);
    await expect(undo).toBeVisible();

    await page.mouse.move(0, 0);
    await undo.blur();
    await page.clock.runFor(10_000);

    await expect(undo).toBeHidden();
    await expect(page.getByRole('searchbox', { name: 'Zoeken' })).toBeFocused();
});

test('focus on the button alone also pauses the countdown', async ({ page }) => {
    await page.clock.install();
    await page.goto('/?demo=List&undo=1');
    await deleteRow(page, 'Persoon C');

    await page.clock.runFor(60_000);

    await expect(page.getByRole('button', { name: 'Ongedaan maken' })).toBeVisible();
});

test('a restore that now breaks a rule restores nothing and names the rule', async ({ page }) => {
    await page.goto('/?demo=List&undo=1&conflict=1');
    await deleteRow(page, 'Persoon C');

    await page.getByRole('button', { name: 'Ongedaan maken' }).click();

    await expect(page.getByRole('alert')).toHaveText('Ongedaan maken kan niet meer');
    await expect(page.getByRole('button', { name: 'Persoon C', exact: true })).toBeHidden();
});
