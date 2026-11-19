import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedViewCanvas } from '../support/view';

test('selecting an Element opens the read-only panel with its fields and Modality letter, and focus returns on close', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);

    const activity = page.getByRole('button', { name: 'Plannen', exact: true });
    await activity.click();
    const panel = page.locator('#detail-panel');
    await expect(panel.getByRole('heading', { name: 'Plannen' })).toBeVisible();
    await expect(panel).toContainText('Jaarplan opstellen');
    await expect(panel).toContainText('Richting geven');
    await expect(panel.locator('[data-modality]')).toContainText('A');
    await expect(panel.locator('[data-modality]')).toContainText('Adaptive');
    await expect(panel.locator('input, textarea, select')).toHaveCount(0);
    await expectNoAxeViolations(page);

    await panel.getByRole('button', { name: 'Sluiten' }).click();
    await expect(panel).toBeHidden();
    await expect(activity).toBeFocused();
});

test('an Area opens its panel as well', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.getByRole('button', { name: 'Koers', exact: true }).click();
    await expect(page.locator('#detail-panel')).toContainText('Leren voor het leven');
});
