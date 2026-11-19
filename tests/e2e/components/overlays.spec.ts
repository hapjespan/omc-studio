import { expect, test } from '../support/guards';
import { expectNoAxeViolations } from '../support/guards';

test.use({ baseURL: process.env.E2E_HARNESS_URL ?? 'http://127.0.0.1:4187' });

for (const [opener, title, input] of [['Open dialoog', 'Dialoog', 'dialog-text'], ['Open paneel', 'Paneel', 'panel-text']] as const) {
    test.describe(title, () => {
        test('focus moves in, Escape closes a clean overlay and focus returns to the opener', async ({ page }) => {
            await page.goto('/?demo=Overlays');
            const trigger = page.getByRole('button', { name: opener });
            await trigger.focus();
            await page.keyboard.press('Enter');

            const overlay = page.getByRole('dialog', { name: title });
            await expect(overlay).toBeVisible();
            await expect(overlay.locator(':focus')).toHaveCount(1);
            await expectNoAxeViolations(page);

            await page.keyboard.press('Escape');
            await expect(overlay).toBeHidden();
            await expect(trigger).toBeFocused();
        });

        test('Escape with unsaved input asks to discard and cancelling keeps the input', async ({ page }) => {
            await page.goto('/?demo=Overlays');
            await page.getByRole('button', { name: opener }).click();
            await page.locator(`#${input}`).fill('ongeslagen');
            await page.keyboard.press('Escape');

            await expect(page.getByText('Wijzigingen weggooien?')).toBeVisible();
            await expect(page.getByRole('button', { name: 'Verder bewerken' })).toBeFocused();
            await page.keyboard.press('Enter');
            await expect(page.getByText('Wijzigingen weggooien?')).toBeHidden();
            await expect(page.locator(`#${input}`)).toHaveValue('ongeslagen');
            await expect(page.getByRole('dialog', { name: title })).toBeVisible();
        });

        test('discarding closes the overlay and returns focus', async ({ page }) => {
            await page.goto('/?demo=Overlays');
            const trigger = page.getByRole('button', { name: opener });
            await trigger.click();
            await page.locator(`#${input}`).fill('weg');
            await page.keyboard.press('Escape');
            await page.getByRole('button', { name: 'Weggooien' }).click();

            await expect(page.getByRole('dialog', { name: title })).toBeHidden();
            await expect(trigger).toBeFocused();
        });

        test('Tab never leaves the overlay', async ({ page }) => {
            await page.goto('/?demo=Overlays');
            await page.getByRole('button', { name: opener }).click();
            for (let i = 0; i < 6; i++) {
                await page.keyboard.press('Tab');
                await expect(page.getByRole('dialog', { name: title }).locator(':focus')).toHaveCount(1);
            }
        });
    });
}
