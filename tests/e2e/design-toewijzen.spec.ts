import { randomUUID } from 'node:crypto';
import type { Locator } from '@playwright/test';
import { expect, test } from './support/guards';
import { seedAllocation, expandTarget } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

const style = (locator: Locator, property: string): Promise<string> => locator.evaluate((element, name) => getComputedStyle(element).getPropertyValue(name), property);

test.use({ viewport: { width: 1440, height: 900 } });

test('Toewijzen follows board 3: white panels on the grey page, header card, amber block, line cards, target tree', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 5400000, Beheer: 5000 }, [
        { domain: 'Inkoop', activities: ['Offreren', 'Bestellen', 'Betalen'] },
        { domain: 'Leeg', activities: [] },
    ]);
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/allocation?year=2026&cost_record=${seed.records.Licentie}`);
    await expandTarget(page, 'Inkoop');

    await page.getByRole('button', { name: 'Toevoegen aan verdeling: Offreren' }).click();
    await expect(page.getByLabel(/Percentage voor Inkoop > Offreren/)).toBeFocused();
    await page.getByRole('button', { name: 'Toevoegen aan verdeling: Bestellen' }).click();
    await expect(page.getByLabel(/Percentage voor Inkoop > Bestellen/)).toBeFocused();
    const first = page.getByLabel(/Percentage voor Inkoop > Offreren/);
    await first.fill('25');
    await first.blur();
    await page.getByLabel(/Percentage voor Inkoop > Bestellen/).fill('40');
    await page.getByLabel(/Percentage voor Inkoop > Bestellen/).blur();

    expect(await style(page.locator('body'), 'background-color')).toBe('rgb(242, 243, 241)');
    expect(await style(page.locator('body'), 'font-family')).toContain('Inter');

    const records = page.getByRole('region', { name: /^Kostenposten 2026/ });
    const middle = page.getByRole('region', { name: 'Licentie' });
    const targets = page.getByRole('region', { name: 'Doelen op het canvas' });
    expect(Math.round((await records.boundingBox())!.width)).toBe(346);
    expect(Math.round((await targets.boundingBox())!.width)).toBe(380);
    for (const panel of [targets, middle.locator('> div').first()]) {
        expect(await style(panel, 'background-color')).toBe('rgb(255, 255, 255)');
        expect(await style(panel, 'border-top-color')).toBe('rgb(220, 223, 226)');
        expect(await style(panel, 'border-top-left-radius')).toBe('10px');
    }

    const title = middle.getByRole('heading', { level: 2, name: 'Licentie' });
    expect(await style(title, 'font-size')).toBe('24px');
    expect(await style(title, 'font-family')).toContain('Space Grotesk');
    const value = page.getByTestId('remaining');
    await expect(value).toHaveText('35,00%');
    expect(await style(value, 'font-size')).toBe('30px');
    expect(await style(value, 'font-weight')).toBe('700');
    expect(await style(value, 'color')).toBe('rgb(138, 82, 0)');
    expect(await style(value.locator('..'), 'background-color')).toBe('rgb(255, 243, 214)');
    expect(await style(value.locator('..'), 'border-top-left-radius')).toBe('8px');

    const bar = page.getByTestId('line-bar');
    expect((await bar.boundingBox())!.height).toBe(10);
    await expect(bar.locator('span')).toHaveCount(3);
    expect(await style(bar.locator('span').last(), 'border-top-style')).toBe('dashed');
    expect(await style(bar.locator('span').first(), 'background-color')).toBe('rgb(23, 27, 33)');
    expect(await style(bar.locator('span').nth(1), 'background-color')).toBe('rgb(95, 103, 114)');

    const card = middle.getByRole('listitem').first();
    expect(await style(card, 'border-top-left-radius')).toBe('8px');
    expect(await style(card, 'padding-top')).toBe('12px');
    expect(await style(card.locator('span[aria-hidden="true"]').first(), 'background-color')).toBe('rgb(23, 27, 33)');
    const input = page.getByLabel(/Percentage voor Inkoop > Offreren/);
    const box = (await input.boundingBox())!;
    expect([Math.round(box.width), Math.round(box.height)]).toEqual([84, 36]);
    expect(await style(input, 'text-align')).toBe('right');
    expect(await style(input, 'font-weight')).toBe('700');

    const zone = page.getByText('Klik rechts op een doel of sleep het hierheen om een regel toe te voegen').locator('..');
    expect(await style(zone, 'border-top-style')).toBe('dashed');
    expect(Math.round((await zone.boundingBox())!.height)).toBeGreaterThanOrEqual(48);

    const save = page.getByRole('button', { name: 'Opslaan' });
    await expect(save).toBeDisabled();
    expect(await style(save, 'background-color')).toBe('rgb(230, 232, 230)');
    expect(await style(page.getByRole('button', { name: 'Set leegmaken' }), 'font-weight')).toBe('500');
    expect(await style(page.getByRole('button', { name: 'Wijzigingen weggooien' }), 'font-size')).toBe('13px');

    const selected = page.getByRole('button', { name: /^Licentie/ });
    expect(await style(selected, 'border-top-width')).toBe('2px');
    expect(await style(selected, 'border-top-color')).toBe('rgb(23, 27, 33)');
    expect(await style(selected, 'border-top-left-radius')).toBe('8px');
    await expect(selected).toContainText('Concept 65,00%');

    await expect(targets.getByText('Primaire activiteiten')).toBeVisible();
    expect(await style(targets.getByText('Primaire activiteiten'), 'text-transform')).toBe('uppercase');
    const add = page.getByRole('button', { name: 'Toevoegen aan verdeling: Betalen' });
    const addBox = (await add.boundingBox())!;
    expect([Math.round(addBox.width), Math.round(addBox.height)]).toEqual([28, 28]);
    const row = targets.getByRole('treeitem', { name: /^Betalen/ });
    expect((await row.boundingBox())!.height).toBeLessThanOrEqual(48);
    await expect(targets.getByText('In set')).toHaveCount(2);
    expect(await style(targets.getByText('In set').first(), 'color')).toBe('rgb(31, 122, 77)');
    await expect(targets.getByText('geen').first()).toBeVisible();
    await expect(targets.getByText('leeg', { exact: false }).first()).toBeVisible();
    const domain = targets.getByRole('treeitem').filter({ hasText: 'Inkoop' }).first().locator('> div').first();
    expect(await style(domain, 'background-color')).toBe('rgb(246, 247, 245)');
    const fold = targets.getByRole('button', { name: 'Inklappen: Inkoop' });
    await expect(fold).toHaveAttribute('aria-expanded', 'true');
    await fold.click();
    await expect(targets.getByText('Betalen')).toHaveCount(0);
    await expect(targets.getByRole('button', { name: 'Uitklappen: Inkoop' })).toHaveAttribute('aria-expanded', 'false');
    await expect(targets.getByText('Bedragen zijn opgeslagen toewijzingen.')).toBeVisible();
});

test('line cards keep their name and stay inside the card at 320 pixels', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 5400000 });
    await page.setViewportSize({ width: 320, height: 800 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/allocation?year=2026&cost_record=${seed.records.Licentie}`);
    await expandTarget(page, 'Inkoop');
    await page.getByRole('button', { name: 'Toevoegen aan verdeling: Offreren' }).click();

    const card = page.getByRole('listitem').filter({ has: page.getByLabel(/Percentage voor Inkoop > Offreren/) });
    await expect(card).toBeVisible();
    expect(await card.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
    expect((await card.getByText('Offreren', { exact: true }).boundingBox())!.width).toBeGreaterThan(60);
    expect(await page.locator('main').evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
});
