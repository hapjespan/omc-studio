import { expect, test } from './support/guards';
import { HARNESS_URL } from './support/ports';
import { pictureDifferences, readPicture } from './support/pictureCompare';

test.use({ baseURL: HARNESS_URL });

test('Bekijken draws every block within 1 pixel of the layout engine, with the same text lines', async ({ page }) => {
    await page.goto('/?demo=Picture');
    await page.waitForSelector('[data-block]');
    await page.evaluate(() => document.fonts.ready);

    const { engine, measured } = await readPicture(page);
    expect(engine.length).toBeGreaterThan(10);
    expect(engine.some((block) => block.textLines.length > 1)).toBe(true);
    expect(pictureDifferences(engine, measured)).toEqual([]);
});
