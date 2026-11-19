import { expect, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedViewCanvas } from '../support/view';

test('Tab moves between Areas, arrow keys move through Elements as a tree and Enter opens the panel', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);

    const area = page.getByRole('button', { name: 'Primaire activiteiten', exact: true });
    await area.focus();
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('button', { name: 'Onderwijs', exact: true })).toBeFocused();
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('button', { name: 'Lessen', exact: true })).toBeFocused();
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('button', { name: /^Les geven( |$)/ })).toBeFocused();
    await page.keyboard.press('ArrowLeft');
    await expect(page.getByRole('button', { name: 'Lessen', exact: true })).toBeFocused();
    await page.keyboard.press('ArrowLeft');
    await expect(page.getByRole('button', { name: 'Onderwijs', exact: true })).toBeFocused();

    await page.keyboard.press('Enter');
    await expect(page.locator('#detail-panel').getByRole('heading', { name: 'Onderwijs' })).toBeVisible();
    await page.locator('#detail-panel').getByRole('button', { name: 'Sluiten' }).click();
    await expect(page.getByRole('button', { name: 'Onderwijs', exact: true })).toBeFocused();

    await page.keyboard.press('Tab');
    await expect(page.getByRole('button', { name: 'Ondersteunende activiteiten', exact: true })).toBeFocused();
});

test('the toolbar and the Areas are separate Tab stops', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.getByRole('button', { name: 'Passend maken' }).focus();
    await page.keyboard.press('Tab');
    await expect(page.locator('[data-block][data-kind="area"]').first()).toBeFocused();
    await expect(page.locator('[data-block][tabindex="-1"]').first()).not.toBeFocused();
});
