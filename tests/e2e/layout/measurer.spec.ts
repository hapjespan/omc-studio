import { expect, test } from '../support/guards';
import { HARNESS_URL } from '../support/ports';

test.use({ baseURL: HARNESS_URL });

test('the loader waits for both fonts and the measured widths repeat exactly', async ({ page }) => {
    await page.goto('/?demo=Layout');
    await expect(page.locator('#result')).not.toBeEmpty();

    const { widths, loaded, requested } = JSON.parse((await page.locator('#result').textContent()) ?? '{}') as {
        widths: number[][];
        loaded: boolean[];
        requested: string[];
    };

    expect(loaded).toEqual([true, true]);
    expect(requested.some((font) => font.includes('"Inter"'))).toBe(true);
    expect(requested.some((font) => font.includes('"Space Grotesk"'))).toBe(true);
    for (const [first, second] of widths) {
        expect(first).toBeGreaterThan(0);
        expect(second).toBe(first);
    }
    expect(widths[0][0]).not.toBe(widths[1][0]);
});
