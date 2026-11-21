import { expect, test } from './support/guards';
import { openDrawing } from './support/drawing';
import { seedViewCanvas } from './support/view';

test('a failed save shows the retry control, announces the failure and guards leaving', async ({ page, context }) => {
    const layers = await openDrawing(page, seedViewCanvas());
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await context.setOffline(true);
    await page.getByLabel('X van Onderwijs').fill('50');
    await page.getByLabel('X van Onderwijs').press('Enter');
    await expect(page.locator('[data-save-state]')).toContainText('Opmaak niet opgeslagen');
    await expect(page.locator('[data-announcer]')).toContainText('Opmaak niet opgeslagen');
    await page.getByRole('link', { name: 'Bekijken' }).click();
    const leave = page.getByRole('dialog', { name: 'Wijziging niet opgeslagen' });
    await expect(leave).toBeVisible();
    await context.setOffline(false);
    await leave.getByRole('button', { name: 'Opnieuw proberen' }).click();
    await page.waitForURL(/\/view$/);
});

test('discarding the change leaves Tekenen', async ({ page, context }) => {
    const layers = await openDrawing(page, seedViewCanvas());
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await context.setOffline(true);
    await page.getByLabel('X van Onderwijs').fill('50');
    await page.getByLabel('X van Onderwijs').press('Enter');
    await expect(page.locator('[data-save-state]')).toContainText('Opmaak niet opgeslagen');
    await context.setOffline(false);
    await page.getByRole('link', { name: 'Bekijken' }).click();
    await page.getByRole('dialog', { name: 'Wijziging niet opgeslagen' }).getByRole('button', { name: 'Wijziging weggooien' }).click();
    await page.waitForURL(/\/view$/);
});

test('the success of an automatic save is shown but not announced', async ({ page }) => {
    const layers = await openDrawing(page, seedViewCanvas());
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await page.getByLabel('X van Onderwijs').fill('50');
    await page.getByLabel('X van Onderwijs').press('Enter');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    expect(await page.locator('[data-save-state]').getAttribute('role')).toBeNull();
    await expect(page.locator('[data-announcer]')).not.toContainText('opgeslagen');
});
