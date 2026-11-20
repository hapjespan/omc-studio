import { expect, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

test.use({ viewport: { width: 320, height: 640 } });

test('Invoeren fits at 320 px without horizontal scrolling and keeps the Area list and the work area apart', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Onderwijs'], ['activity', 'Lessen', 'Onderwijs']]);
    await openEntry(page, canvas);
    await expect(page.getByRole('heading', { level: 2, name: 'Primaire activiteiten' })).toBeVisible();

    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
    const list = await page.getByRole('navigation', { name: 'Areas' }).boundingBox();
    const work = await page.getByRole('region', { name: 'Primaire activiteiten' }).boundingBox();
    expect(list!.x + list!.width).toBeLessThanOrEqual(320);
    expect(work!.x + work!.width).toBeLessThanOrEqual(320);
    expect(work!.y).toBeGreaterThanOrEqual(list!.y + list!.height);
});
