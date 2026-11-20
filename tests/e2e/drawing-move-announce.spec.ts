import { expect, test } from './support/guards';
import { dragTo, LESSEN, openDrawing, seedMoveCanvas, SMAL } from './support/drawing';

test('a completed move is announced with the block and its new parent, and the narrowing', async ({ page }) => {
    await openDrawing(page, seedMoveCanvas());
    await dragTo(page, LESSEN, SMAL);
    await page.keyboard.press('Enter');
    const announcer = page.locator('[data-announcer]');
    await expect(announcer).toContainText('Lessen hangt nu onder Smal');
    await expect(announcer).toContainText('Lessen is smaller gemaakt tot de breedte van Smal');
    expect(await announcer.getAttribute('aria-live')).toBe('polite');
});
