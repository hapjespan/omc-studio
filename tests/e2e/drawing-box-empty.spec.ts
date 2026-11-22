import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

test('an empty X value is refused with a message that names the field and keeps focus', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true }).click();

    const x = page.getByLabel('X van Onderwijs');
    const before = await x.inputValue();
    await x.fill('');
    await x.press('Enter');

    await expect(page.locator('#box-x-error')).toHaveText('X van Onderwijs: vul een getal in');
    await expect(page.getByRole('status').filter({ hasText: 'X van Onderwijs: vul een getal in' }).first()).toBeAttached();
    await expect(x).toBeFocused();
    await expect(x).toHaveAttribute('aria-invalid', 'true');
    await expect(x).toHaveValue(before);

    await x.pressSequentially('1');
    await expect(page.locator('#box-x-error')).toHaveCount(0);
    await expect(x).not.toHaveAttribute('aria-invalid', 'true');

    await x.fill(String(Number(before) + 2));
    await x.press('Enter');
    await expect(page.locator('#box-x-error')).toHaveCount(0);
});
