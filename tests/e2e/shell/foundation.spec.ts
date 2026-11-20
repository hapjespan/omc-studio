import type { Locator } from '@playwright/test';
import { openEntry, seedEntryCanvas } from '../support/entry';
import { expect, expectNoAxeViolations, test } from '../support/guards';
import { createCanvas, signInAs } from '../support/shell';

const styleOf = (locator: Locator, property: string): Promise<string> => locator.evaluate((element, name) => getComputedStyle(element).getPropertyValue(name), property);

test('the page carries the fonts, the page background and the one row top bar of the contract', async ({ page }) => {
    await signInAs(page, 'administrator');

    expect(await styleOf(page.locator('body'), 'font-family')).toMatch(/^"?Inter/);
    expect(await styleOf(page.getByRole('heading', { level: 1 }), 'font-family')).toMatch(/^"?Space Grotesk/);
    expect(await styleOf(page.locator('body'), 'background-color')).toBe('rgb(242, 243, 241)');

    const bar = page.getByRole('banner');
    expect((await bar.boundingBox())?.height).toBe(56);
    await expect(bar.locator('svg').first()).toBeVisible();
    await expect(bar.getByRole('link', { name: 'OMC Studio' })).toBeVisible();
    await expect(bar.getByRole('navigation', { name: 'Hoofdnavigatie' })).toBeVisible();
    await expectNoAxeViolations(page);
});

test('the active mode segment is an ink fill and the tabs keep their ink underline', async ({ page }) => {
    const canvas = createCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas}/entry`);

    const group = page.getByRole('group', { name: 'Modus' });
    const active = group.getByRole('link', { name: 'Invoeren' });
    await expect(active).toHaveAttribute('aria-current', 'page');
    expect(await styleOf(active, 'background-color')).toBe('rgb(23, 27, 33)');
    expect((await group.boundingBox())?.height).toBe(36);

    const tab = page.getByRole('navigation', { name: 'Hoofdnavigatie' }).getByRole('link', { name: 'Canvas' });
    expect(await styleOf(tab, 'border-bottom-color')).toBe('rgb(23, 27, 33)');
});

test('a control is 36 pixels high with the border colour of the contract', async ({ page }) => {
    await signInAs(page, 'administrator');
    await page.goto('/account');

    const field = page.locator('#current_password');
    expect((await field.boundingBox())?.height).toBe(36);
    expect(await styleOf(field, 'border-top-color')).toBe('rgb(132, 139, 149)');
});

test('an icon button measures 28 by 28 and keeps its name', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [['domain', 'Inkoop'], ['activity', 'Werving', 'Inkoop'], ['activity', 'Selectie', 'Inkoop']]);
    await openEntry(page, canvas);

    const arrow = page.getByRole('button', { name: 'Selectie omhoog' });
    const box = await arrow.boundingBox();
    expect(box?.width).toBe(28);
    expect(box?.height).toBe(28);
});
