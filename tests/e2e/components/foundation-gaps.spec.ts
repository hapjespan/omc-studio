import { expect, expectNoAxeViolations, test } from '../support/guards';
import { HARNESS_URL } from '../support/ports';

test.use({ baseURL: HARNESS_URL });

const style = (locator: import('@playwright/test').Locator, names: string[]) =>
    locator.evaluate((element, list) => Object.fromEntries(list.map((name) => [name, getComputedStyle(element).getPropertyValue(name)])), names);

test('the inline field is 84 by 36 with a hidden but accessible label, a percent suffix and a described error', async ({ page }) => {
    await page.goto('/?demo=Gaps');
    const input = page.getByLabel('Percentage regel 1');
    const box = await input.boundingBox();

    expect([box?.width, box?.height]).toEqual([84, 36]);
    await expect(input).toHaveAccessibleDescription('Samen 100%');
    await expect(page.locator('label[for=share]')).toHaveCSS('position', 'absolute');
    await expect(page.getByText('%', { exact: true }).first()).toBeVisible();
    await input.fill('55,5');
    await expect(page.locator('#share-value')).toHaveText('55,5');

    const bad = page.getByLabel('Percentage regel 2');
    await expect(bad).toHaveAttribute('aria-invalid', 'true');
    await expect(bad).toHaveAccessibleDescription('Te veel');
    expect((await style(bad, ['border-top-color']))['border-top-color']).toBe('rgb(192, 100, 90)');
    expect((await style(input, ['border-top-color']))['border-top-color']).toBe('rgb(132, 139, 149)');
    await input.focus();
    expect((await style(input, ['outline-style']))['outline-style']).toBe('solid');
});

test('the native select is 36 high with the edge border, a labelled empty option and a string model', async ({ page }) => {
    await page.goto('/?demo=Gaps');
    const select = page.getByRole('combobox', { name: 'Soort', exact: true });

    expect((await select.boundingBox())?.height).toBe(36);
    expect((await style(select, ['border-top-color']))['border-top-color']).toBe('rgb(132, 139, 149)');
    await expect(select.locator('option').first()).toHaveText('Alle soorten');
    await expect(select).toHaveValue('');
    await select.selectOption('b');
    await expect(page.locator('#kind-value')).toHaveText('[b]');
    await select.selectOption('');
    await expect(page.locator('#kind-value')).toHaveText('[]');

    const bad = page.getByRole('combobox', { name: 'Soort verplicht' });
    await expect(bad).toHaveAccessibleDescription('Kies een soort');
    await expect(bad.locator('option[value=""]')).toHaveCount(0);
    expect((await style(bad, ['border-top-color']))['border-top-color']).toBe('rgb(192, 100, 90)');
});

test('the report table is a bordered card with a caption, column and row headers, tabular right-aligned figures and an empty state', async ({ page }) => {
    await page.goto('/?demo=Gaps');
    const table = page.getByRole('table', { name: 'Samenvatting import' });
    const card = table.locator('xpath=..');

    expect(await style(card, ['border-top-color', 'border-top-left-radius'])).toEqual({ 'border-top-color': 'rgb(220, 223, 226)', 'border-top-left-radius': '8px' });
    await expect(table.getByRole('columnheader')).toHaveText(['Status', 'Aantal']);
    await expect(table.getByRole('rowheader')).toHaveText(['Nieuw', 'Fouten']);
    const figure = table.getByRole('cell', { name: '1234' });
    expect(await style(figure, ['text-align', 'font-variant-numeric'])).toEqual({ 'text-align': 'right', 'font-variant-numeric': 'tabular-nums' });
    await expect(page.getByRole('table', { name: 'Lege tabel' }).getByRole('cell')).toHaveText('Geen regels');
    await expectNoAxeViolations(page);
});
