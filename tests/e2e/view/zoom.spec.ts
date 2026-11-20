import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedViewCanvas } from '../support/view';

test('Inzoomen and Uitzoomen step by 10 percent between 50 and 200 and Passend maken fits the Canvas', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    const zoom = page.locator('[data-zoom]');
    await expect(zoom).not.toHaveText('100%');
    const opening = Number((await zoom.textContent())?.replace('%', ''));
    expect(opening).toBeLessThan(100);

    await page.getByRole('button', { name: 'Inzoomen' }).click();
    await expect(zoom).toHaveText(`${Math.floor(opening / 10) * 10 + 10}%`);
    for (let value = Math.floor(opening / 10) * 10 + 10; value > 50; value -= 10) await page.getByRole('button', { name: 'Uitzoomen' }).click();
    await expect(zoom).toHaveText('50%');
    await expect(page.getByRole('button', { name: 'Uitzoomen' })).toHaveAttribute('aria-disabled', 'true');
    await expect(page.getByRole('button', { name: 'Uitzoomen' })).toBeFocused();

    for (let step = 0; step < 15; step++) await page.getByRole('button', { name: 'Inzoomen' }).click();
    await expect(zoom).toHaveText('200%');
    await expect(page.getByRole('button', { name: 'Inzoomen' })).toBeFocused();

    await page.getByRole('button', { name: 'Passend maken' }).click();
    await expect(zoom).toHaveText(`${opening}%`);
    const first = await zoom.textContent();
    await page.getByRole('button', { name: 'Passend maken' }).click();
    await expect(zoom).toHaveText(first ?? '');
    expect(Number(first?.replace('%', ''))).toBeLessThan(200);
    await expectNoAxeViolations(page);
});

test('the zoomed Canvas scrolls in both directions', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    for (let step = 0; step < 10; step++) await page.getByRole('button', { name: 'Inzoomen' }).click();
    const scroller = page.locator('[data-scroller]');
    const sizes = await scroller.evaluate((element) => ({ wide: element.scrollWidth > element.clientWidth, tall: element.scrollHeight > element.clientHeight }));
    expect(sizes).toEqual({ wide: true, tall: true });
});
