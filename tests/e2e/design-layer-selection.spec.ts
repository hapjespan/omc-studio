import { expect, test } from './support/guards';
import { openDrawing } from './support/drawing';

const channel = (value: number): number => (value / 255 <= 0.03928 ? value / 255 / 12.92 : ((value / 255 + 0.055) / 1.055) ** 2.4);
const luminance = (rgb: string): number => {
    const [red, green, blue] = (rgb.match(/\d+/g) ?? []).map(Number);
    return 0.2126 * channel(red) + 0.7152 * channel(green) + 0.0722 * channel(blue);
};
const contrast = (one: string, other: string): number => {
    const [high, low] = [luminance(one), luminance(other)].sort((a, b) => b - a);
    return (high + 0.05) / (low + 0.05);
};

test('the selected layer row carries a 2 px ink border that holds 3:1 against the row background', async ({ page }) => {
    const layers = await openDrawing(page);
    const row = layers.getByRole('button', { name: 'Onderwijs', exact: true });
    await row.click();
    await expect(row).toHaveAttribute('aria-pressed', 'true');
    const measured = await row.evaluate((element) => {
        const style = getComputedStyle(element);
        const shadow = style.boxShadow;
        return { width: style.borderTopWidth, border: style.borderTopColor, shadow, background: style.backgroundColor };
    });
    const ink = 'rgb(23, 27, 33)';
    expect(measured.border).toBe(ink);
    expect(Number.parseFloat(measured.width) + (measured.shadow.includes(ink) ? 1 : 0)).toBe(2);
    expect(contrast(measured.border, measured.background)).toBeGreaterThanOrEqual(3);
});
