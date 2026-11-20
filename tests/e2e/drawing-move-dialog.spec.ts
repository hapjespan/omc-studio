import { expect, test } from './support/guards';
import { dragTo, ACTIVITY, openDrawing, seedMoveCanvas, DOMAIN_TARGET } from './support/drawing';

const title = 'Verplaatsen naar Primaire activiteiten › Verkoop?';

test('a Shift-drag asks before anything is stored, Escape cancels and focus returns to the row in Lagen', async ({ page }) => {
    const layers = await openDrawing(page, seedMoveCanvas());
    await layers.getByRole('button', { name: 'Les geven', exact: true }).first().click();
    const x = await page.getByLabel('X van Les geven').inputValue();
    const y = await page.getByLabel('Y van Les geven').inputValue();
    await dragTo(page, ACTIVITY, DOMAIN_TARGET);
    const dialog = page.getByRole('dialog', { name: title });
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(layers.getByRole('button', { name: 'Les geven', exact: true }).first()).toBeFocused();
    await layers.getByRole('button', { name: 'Les geven', exact: true }).first().click();
    await expect(page.getByLabel('X van Les geven')).toHaveValue(x);
    await expect(page.getByLabel('Y van Les geven')).toHaveValue(y);
    await page.reload();
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await expect(page.getByRole('navigation', { name: 'Lagen' }).locator('li:has(> div > button:text-is("Lessen"))').getByRole('button', { name: 'Les geven', exact: true })).toBeVisible();
});

test('Enter confirms the move and it survives a reload', async ({ page }) => {
    await openDrawing(page, seedMoveCanvas());
    await dragTo(page, ACTIVITY, DOMAIN_TARGET);
    await expect(page.getByRole('dialog', { name: title })).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('dialog')).toBeHidden();
    await expect(page.locator('[data-announcer]')).toContainText('Les geven hangt nu onder Verkoop');
    await expect(page.locator('li:has(> div > button:text-is("Verkoop"))').getByRole('button', { name: 'Les geven', exact: true })).toBeVisible();
    await page.reload();
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await expect(page.locator('li', { has: page.getByRole('button', { name: 'Verkoop', exact: true }) }).last().getByRole('button', { name: 'Les geven', exact: true })).toBeVisible();
});

test('Tekenen takes no other action while the dialog is open: the drawing is inert', async ({ page }) => {
    await openDrawing(page, seedMoveCanvas());
    await dragTo(page, ACTIVITY, DOMAIN_TARGET);
    await expect(page.getByRole('dialog', { name: title })).toBeVisible();
    await expect(page.locator('[inert]')).toHaveCount(2);
    await page.getByRole('button', { name: 'Annuleren' }).click();
    await expect(page.getByRole('dialog')).toBeHidden();
});
