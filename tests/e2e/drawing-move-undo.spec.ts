import { expect, test } from './support/guards';
import { dragTo, ACTIVITY, openDrawing, seedMoveCanvas, DOMAIN_TARGET } from './support/drawing';

async function moved(page: import('@playwright/test').Page) {
    await dragTo(page, ACTIVITY, DOMAIN_TARGET);
    await page.keyboard.press('Enter');
    await expect(page.locator('[data-announcer]')).toContainText('Les geven hangt nu onder Verkoop');
}

test('undo and redo of a move ask first, Annuleren leaves the history unchanged', async ({ page }) => {
    await openDrawing(page, seedMoveCanvas());
    await moved(page);
    const undo = page.getByRole('button', { name: 'Ongedaan maken' });
    const redo = page.getByRole('button', { name: 'Opnieuw', exact: true });
    await expect(undo).toBeEnabled();
    await undo.click();
    const dialog = page.getByRole('dialog', { name: 'Verplaatsen naar Primaire activiteiten › Onderwijs › Lessen?' });
    await expect(dialog).toBeVisible();
    await page.getByRole('button', { name: 'Annuleren' }).click();
    await expect(dialog).toBeHidden();
    await expect(undo).toBeEnabled();
    await expect(redo).toBeDisabled();
    await undo.click();
    await page.keyboard.press('Enter');
    await expect(page.locator('[data-announcer]')).toContainText('Les geven hangt nu onder Lessen');
    await expect(redo).toBeEnabled();
    await redo.click();
    await expect(page.getByRole('dialog', { name: 'Verplaatsen naar Primaire activiteiten › Verkoop?' })).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(page.locator('[data-announcer]')).toContainText('Les geven hangt nu onder Verkoop');
});
