import { expect, test } from './support/guards';
import { openDrawing } from './support/drawing';
import { seedViewCanvas } from './support/view';

test('a refused save returns the drawing to the stored Layout, keeps the typed value marked invalid with focus and announces the reason', async ({ page }) => {
    const layers = await openDrawing(page, seedViewCanvas());
    await page.route('**/layout', (route) => (route.request().method() === 'PUT' ? route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ message: 'refused', errors: { rule: ['Opmaak Onderwijs: plek en grootte zijn ongeldig'], codes: ['invalid_layout'] } }) }) : route.continue()));
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const x = page.getByLabel('X van Onderwijs');
    const start = await x.inputValue();
    await x.fill('44');
    await x.press('Enter');
    await expect(page.locator('[data-announcer]')).toContainText('Opmaak Onderwijs: plek en grootte zijn ongeldig');
    await expect(x).toHaveValue('44');
    await expect(x).toHaveAttribute('aria-invalid', 'true');
    await expect(x).toBeFocused();
    await expect(page.getByText('Opmaak Onderwijs: plek en grootte zijn ongeldig').first()).toBeVisible();
    await layers.getByRole('button', { name: 'Strategie', exact: true }).click();
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await expect(page.getByLabel('X van Onderwijs')).toHaveValue(start);
});
