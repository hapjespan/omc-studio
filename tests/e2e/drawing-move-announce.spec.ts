import { expect, test } from './support/guards';
import { dragTo, CLUSTER, openDrawing, seedMoveCanvas, NARROW_TARGET } from './support/drawing';

test('a completed move is announced with the block and its new parent, and the narrowing', async ({ page }) => {
    await openDrawing(page, seedMoveCanvas());
    await dragTo(page, CLUSTER, NARROW_TARGET);
    await page.keyboard.press('Enter');
    const announcer = page.locator('[data-announcer]');
    await expect(announcer).toContainText('Lessen hangt nu onder Smal');
    await expect(announcer).toContainText('Lessen is smaller gemaakt tot de breedte van Smal');
    expect(await announcer.getAttribute('aria-live')).toBe('polite');
});
