import { expect, expectNoAxeViolations, test } from '../support/guards';
import { HARNESS_URL } from '../support/ports';

test.use({ baseURL: HARNESS_URL });

const style = (locator: import('@playwright/test').Locator, names: string[]) =>
    locator.evaluate((element, list) => Object.fromEntries(list.map((name) => [name, getComputedStyle(element).getPropertyValue(name)])), names);

test('the segmented control is 36 high outside, 34 inside the border so the segment is 32, with an ink active segment', async ({ page }) => {
    await page.goto('/?demo=Foundation');
    const group = page.getByRole('group', { name: 'Laag' });
    const active = group.getByRole('button', { name: 'Geen' });

    expect((await group.boundingBox())?.height).toBe(36);
    expect((await active.boundingBox())?.height).toBe(32);
    expect((await style(active, ['background-color', 'color']))['background-color']).toBe('rgb(23, 27, 33)');
    await group.getByRole('button', { name: 'Modaliteit' }).click();
    await expect(group.getByRole('button', { name: 'Modaliteit' })).toHaveAttribute('aria-pressed', 'true');
    await expect(active).toHaveAttribute('aria-pressed', 'false');
});

test('the chip select is a native select, 36 high with the edge colour and a name', async ({ page }) => {
    await page.goto('/?demo=Foundation');
    const select = page.getByRole('combobox', { name: 'Soort' });
    const chip = select.locator('xpath=..');

    expect((await chip.boundingBox())?.height).toBe(36);
    expect((await style(chip, ['border-top-color']))['border-top-color']).toBe('rgb(132, 139, 149)');
    await select.selectOption('a');
    await expect(select).toHaveValue('a');
});

test('the panel is white with a hairline, radius 10 and padding 24', async ({ page }) => {
    await page.goto('/?demo=Foundation');
    const panel = page.locator('#panel');
    const values = await style(panel, ['background-color', 'border-top-color', 'border-top-left-radius', 'padding-top']);

    expect(values).toEqual({ 'background-color': 'rgb(255, 255, 255)', 'border-top-color': 'rgb(220, 223, 226)', 'border-top-left-radius': '10px', 'padding-top': '24px' });
    const heading = await style(page.getByRole('heading', { name: 'Exporteren' }), ['font-size', 'font-family']);
    expect(heading['font-size']).toBe('16px');
    expect(heading['font-family']).toContain('Space Grotesk');
});

test('the file field has a 36 pixel box, a Dutch button, a described native input and shows the chosen name', async ({ page }) => {
    await page.goto('/?demo=Foundation');
    const input = page.getByLabel('Bestand');

    await expect(input).toHaveAccessibleDescription('Alleen JSON');
    const box = page.getByText('Kies een bestand').locator('xpath=..');
    expect((await box.boundingBox())?.height).toBe(36);
    expect((await page.getByText('Kies een bestand').boundingBox())?.height).toBe(28);
    await expect(page.getByText('Geen bestand gekozen')).toBeVisible();
    await input.setInputFiles({ name: 'export.json', mimeType: 'application/json', buffer: Buffer.from('{}') });
    await expect(page.locator('#picked')).toHaveText('export.json');
    await expect(box.getByText('export.json')).toBeVisible();
    await input.focus();
    await expect(box).toHaveCSS('outline-style', 'solid');
});

test('a button in link mode is an anchor that looks and measures like a button', async ({ page }) => {
    await page.goto('/?demo=Foundation');
    const link = page.getByRole('link', { name: 'Alles exporteren' });
    const button = page.getByRole('button', { name: 'Gewoon' });

    await expect(link).toHaveAttribute('download', 'export.json');
    expect(await style(link, ['height', 'border-top-color', 'border-radius', 'font-weight'])).toEqual(await style(button, ['height', 'border-top-color', 'border-radius', 'font-weight']));
    expect((await link.boundingBox())?.height).toBe(36);
});

test('the danger pill and callout use the danger text colour on the soft background', async ({ page }) => {
    await page.goto('/?demo=Foundation');
    for (const id of ['#pill', '#callout']) {
        const values = await style(page.locator(id), ['color', 'background-color']);
        expect(values).toEqual({ color: 'rgb(161, 42, 30)', 'background-color': 'rgb(253, 236, 234)' });
    }
});

test('the checkbox keeps a 16 pixel box in a 24 pixel target with radius 4 and an ink fill when checked', async ({ page }) => {
    await page.goto('/?demo=Foundation');
    const box = page.getByRole('checkbox', { name: 'Rij kiezen' });

    expect(await box.boundingBox()).toMatchObject({ width: 16, height: 16 });
    expect((await box.locator('xpath=..').boundingBox())?.width).toBe(24);
    expect((await style(box, ['border-top-left-radius']))['border-top-left-radius']).toBe('4px');
    await box.check();
    expect((await style(box, ['background-color']))['background-color']).toBe('rgb(23, 27, 33)');
});

test('an icon button measures 36 on request and 28 by default', async ({ page }) => {
    await page.goto('/?demo=Foundation');

    expect(await page.getByRole('button', { name: 'Verwijderen', exact: true }).boundingBox()).toMatchObject({ width: 36, height: 36 });
    expect(await page.getByRole('button', { name: 'Passend maken' }).boundingBox()).toMatchObject({ width: 28, height: 28 });
});

test('the action menu opens with the keyboard, names its items and reports the choice', async ({ page }) => {
    await page.goto('/?demo=Foundation');
    const trigger = page.getByRole('button', { name: 'Meer acties' });

    await trigger.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('menuitem', { name: 'Kopiëren' })).toBeVisible();
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    await expect(page.locator('#action')).toHaveText('delete');
    await expect(trigger).toBeFocused();
});

test('the over-budget and drop-zone tokens carry the contract colours', async ({ page }) => {
    await page.goto('/?demo=Foundation');

    expect((await style(page.locator('#swatch-over'), ['background-color']))['background-color']).toBe('rgb(154, 120, 17)');
    expect((await style(page.locator('#swatch-drop'), ['background-color']))['background-color']).toBe('rgb(154, 161, 170)');
});

test('the foundation passes axe', async ({ page }) => {
    await page.goto('/?demo=Foundation');
    await expectNoAxeViolations(page);
});

test('a page fills the header targets and the row exists only then', async ({ page }) => {
    await page.goto('/?demo=Foundation');
    await expect(page.locator('#page-title')).toHaveCount(0);

    await page.goto('/?demo=Foundation&header=1');
    await expect(page.locator('#page-title #page-heading')).toHaveText('Bekijken');
    await expect(page.locator('#page-actions').getByRole('button', { name: 'Exporteren' })).toBeVisible();
});

test('the opt-in wide padding gives the main 24 pixels and the default keeps 16', async ({ page }) => {
    await page.goto('/?demo=Foundation');
    expect((await style(page.locator('main'), ['padding-left']))['padding-left']).toBe('16px');

    await page.goto('/?demo=Foundation&header=1');
    expect((await style(page.locator('main'), ['padding-left']))['padding-left']).toBe('24px');
});

test('the status region shows text by default and is visually hidden but announced when a page opts in', async ({ page }) => {
    await page.goto('/?demo=Foundation');
    await page.locator('#post').click();
    const region = page.getByRole('status');
    await expect(region).toHaveText('Alles opgeslagen');
    expect((await region.boundingBox())?.height).toBeGreaterThan(5);

    await page.goto('/?demo=Foundation&quiet=1');
    await page.locator('#post').click();
    const quiet = page.getByRole('status');
    await expect(quiet).toHaveText('Alles opgeslagen');
    await expect(quiet).toHaveAttribute('aria-live', 'polite');
    const box = await quiet.boundingBox();
    expect(box?.width).toBeLessThanOrEqual(1);
    expect(box?.height).toBeLessThanOrEqual(1);
});
