import { expect, test } from './support/guards';
import { CLUSTER, ACTIVITY, openDrawing, screenOf, seedMoveCanvas, shiftDrag, NARROW_TARGET, DOMAIN_TARGET, type Spot } from './support/drawing';

async function drag(page: import('@playwright/test').Page, from: Spot, to: Spot, check: () => Promise<void>) {
    await shiftDrag(page, await screenOf(page, from), await screenOf(page, to), check);
}

test('the drag label names a valid target', async ({ page }) => {
    await openDrawing(page, seedMoveCanvas());
    await drag(page, ACTIVITY, DOMAIN_TARGET, async () => {
        await expect(page.locator('[data-drag-label]')).toHaveText('Naar Verkoop');
    });
});

test('the drag label says when the block gets narrower', async ({ page }) => {
    await openDrawing(page, seedMoveCanvas());
    await drag(page, CLUSTER, NARROW_TARGET, async () => {
        await expect(page.locator('[data-drag-label]')).toHaveText('Naar Smal (wordt smaller)');
    });
});

test('the drag label says why a target is refused, as text, and the refusal is announced at release', async ({ page }) => {
    await openDrawing(page, seedMoveCanvas());
    await drag(page, ACTIVITY, NARROW_TARGET, async () => {
        await expect(page.locator('[data-drag-label]')).toHaveText('Kan hier niet: Naam: er staat al een element met deze naam op deze plek');
        await expect(page.locator('[data-drag-label]')).toHaveAttribute('data-valid', 'false');
    });
    await expect(page.locator('[data-drag-label]')).toHaveCount(0);
    await expect(page.locator('[data-announcer]')).toContainText('Kan hier niet');
});

test('the label is not a live region', async ({ page }) => {
    await openDrawing(page, seedMoveCanvas());
    await drag(page, ACTIVITY, DOMAIN_TARGET, async () => {
        const label = page.locator('[data-drag-label]');
        await expect(label).toBeVisible();
        expect(await label.getAttribute('aria-live')).toBeNull();
        expect(await label.getAttribute('role')).toBeNull();
    });
});
