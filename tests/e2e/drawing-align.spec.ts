import { expect, test } from './support/guards';
import { openDrawing, seedRow } from './support/drawing';

test('align left and distribute vertically three Activities, undone in one step', async ({ page }) => {
    const layers = await openDrawing(page, seedRow());
    const names = ['Alfa', 'Beta', 'Gamma'];
    const value = async (name: string, field: 'X' | 'Y') => {
        await layers.getByRole('button', { name, exact: true }).click();
        return Number(await page.getByLabel(`${field} van ${name}`).inputValue());
    };
    const toolbar = page.getByRole('toolbar', { name: 'Gereedschap' });
    await layers.getByRole('button', { name: 'Alfa', exact: true }).click();
    await layers.getByRole('button', { name: 'Beta', exact: true }).focus();
    await page.keyboard.press('Control+Space');
    await layers.getByRole('button', { name: 'Gamma', exact: true }).focus();
    await page.keyboard.press('Control+Space');
    await expect(layers.locator('[aria-pressed="true"]')).toHaveCount(3);

    await toolbar.getByRole('button', { name: 'Uitlijnen' }).click();
    await page.getByRole('menuitem', { name: 'Links', exact: true }).click();
    const xs = [];
    for (const name of names) xs.push(await value(name, 'X'));
    expect(new Set(xs).size).toBe(1);

    await layers.getByRole('button', { name: 'Gamma', exact: true }).click();
    const gamma = page.getByLabel('Y van Gamma');
    await gamma.fill('120');
    await gamma.press('Enter');
    await expect(gamma).toHaveValue('120');
    const before = [];
    for (const name of names) before.push(await value(name, 'Y'));
    await layers.getByRole('button', { name: 'Alfa', exact: true }).click();
    await layers.getByRole('button', { name: 'Beta', exact: true }).focus();
    await page.keyboard.press('Control+Space');
    await layers.getByRole('button', { name: 'Gamma', exact: true }).focus();
    await page.keyboard.press('Control+Space');
    await toolbar.getByRole('button', { name: 'Gelijk verdelen' }).click();
    await page.getByRole('menuitem', { name: 'Verticaal', exact: true }).click();
    await page.getByRole('button', { name: 'Ongedaan maken' }).click();
    const after = [];
    for (const name of names) after.push(await value(name, 'Y'));
    expect(after).toEqual(before);
});

test('distribute needs three and equal size needs two selected', async ({ page }) => {
    const layers = await openDrawing(page, seedRow());
    const toolbar = page.getByRole('toolbar', { name: 'Gereedschap' });
    await layers.getByRole('button', { name: 'Alfa', exact: true }).click();
    await expect(toolbar.getByRole('button', { name: 'Gelijke breedte' })).toBeDisabled();
    await layers.getByRole('button', { name: 'Beta', exact: true }).focus();
    await page.keyboard.press('Control+Space');
    await expect(toolbar.getByRole('button', { name: 'Gelijke breedte' })).toBeEnabled();
    await toolbar.getByRole('button', { name: 'Gelijk verdelen' }).click();
    await expect(page.getByRole('menuitem', { name: 'Verticaal', exact: true })).toHaveAttribute('data-disabled', '');
});
