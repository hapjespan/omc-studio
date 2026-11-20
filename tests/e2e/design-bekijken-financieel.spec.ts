import { randomUUID } from 'node:crypto';
import type { Locator, Page } from '@playwright/test';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { tinker } from './support/finance';
import { seedOverlay } from './support/overlay';
import { signInAs } from './support/shell';

const INK = 'rgb(23, 27, 33)';
const SECONDARY = 'rgb(69, 76, 87)';

const seed = (): number => {
    const data = seedOverlay(`Ontwerp ${randomUUID().slice(0, 8)}`);
    tinker(
        `$c = App\\Models\\Canvas::find(${data.canvas}); $a = fn ($k) => App\\Models\\Area::where('key', $k)->value('id'); ` +
            `$g = App\\Models\\Element::create(['canvas_id' => $c->id, 'area_id' => $a('actors'), 'kind' => 'domain', 'name' => 'Klanten']); ` +
            `App\\Models\\Element::create(['canvas_id' => $c->id, 'area_id' => $a('actors'), 'kind' => 'activity', 'name' => 'Ouders', 'parent_id' => $g->id]); ` +
            `App\\Models\\Statement::create(['canvas_id' => $c->id, 'kind' => 'mission', 'body' => 'Elk kind een goede start', 'position' => 1]); ` +
            `App\\Models\\Statement::create(['canvas_id' => $c->id, 'kind' => 'goal', 'title' => 'Een kindprofiel.', 'body' => 'Van opvang tot groep 8.', 'position' => 1]);`,
    );

    return data.canvas;
};

const style = (locator: Locator, ...props: string[]): Promise<Record<string, string>> =>
    locator.evaluate((element, names) => Object.fromEntries(names.map((name) => [name, getComputedStyle(element).getPropertyValue(name)])), props);

const open = async (page: Page, canvas: number): Promise<void> => {
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas}/view?overlay=financial&year=2026`);
    await expect(page.locator('[data-financial-card]').first()).toBeAttached();
};

test('at zoom 100%: the header is one row with the title, modes, overlay switch, filter chips and an ink Exporteren, and the canvas starts near y 146', async ({ page }) => {
    await open(page, seed());

    const title = page.getByRole('heading', { level: 1 });
    expect(await style(title, 'font-family', 'font-size', 'font-weight')).toMatchObject({ 'font-size': '20px', 'font-weight': '600' });
    expect((await style(title, 'font-family'))['font-family']).toContain('Space Grotesk');

    const rows = await Promise.all([title, page.getByRole('group', { name: 'Overlay' }), page.getByRole('button', { name: 'Exporteren' })].map(async (locator) => (await locator.boundingBox())!.y));
    expect(Math.max(...rows) - Math.min(...rows)).toBeLessThan(40);

    const modes = page.getByRole('group', { name: 'Overlay' }).getByRole('button');
    await expect(modes).toHaveCount(3);
    expect(await style(modes.first(), 'font-size', 'font-weight', 'color')).toEqual({ 'font-size': '13px', 'font-weight': '500', color: SECONDARY });
    expect(await style(page.getByRole('button', { name: 'Financieel' }), 'background-color')).toEqual({ 'background-color': INK });
    expect(await style(page.getByRole('button', { name: 'Exporteren' }), 'background-color', 'height')).toEqual({ 'background-color': INK, height: '36px' });
    expect(await style(page.getByText('Overlay', { exact: true }).first(), 'font-size', 'color')).toEqual({ 'font-size': '12.5px', color: SECONDARY });

    for (const name of ['Soort kosten', 'Eigenaar', 'Applicatie']) expect((await style(page.getByLabel(name), 'appearance'))['appearance']).toBe('none');
    const scroller = (await page.locator('[data-scroller]').boundingBox())!;
    expect(scroller.y).toBeLessThan(180);
    await expectNoAxeViolations(page);
});

test('the A3 Artboard fits next to the side panel at 1440 pixels without clipping the Direction band or the Areas', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await open(page, seed());
    await expect(page.locator('[data-zoom]')).not.toHaveText('100%');

    const scroller = (await page.locator('[data-scroller]').boundingBox())!;
    const panel = (await page.locator('[data-figure-panel]').boundingBox())!;
    expect(scroller.x + scroller.width).toBeLessThanOrEqual(panel.x);
    for (const selector of ['[data-direction="goals"]', '[data-block="primary_activities"]', '[data-block="governing"]', '[data-block="supporting_activities"]']) {
        const box = (await page.locator(selector).boundingBox())!;
        expect(box.x + box.width, selector).toBeLessThanOrEqual(scroller.x + scroller.width + 1);
    }
    expect(await page.locator('[data-artboard]').evaluate((element) => (element as HTMLElement).offsetWidth)).toBe(1680);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('at zoom 100%: the Direction band shows a dashed Logo box, uppercase labels and the goals as a numbered list with a bold title', async ({ page }) => {
    await open(page, seed());

    expect((await style(page.locator('[data-direction-logo]'), 'border-top-style'))['border-top-style']).toBe('dashed');
    const label = page.locator('[data-direction="mission"] p').first();
    expect(await style(label, 'text-transform', 'font-size', 'font-weight', 'letter-spacing')).toMatchObject({ 'text-transform': 'uppercase', 'font-size': '11px', 'font-weight': '600' });
    await expect(page.locator('[data-direction="goals"] p').first()).toHaveText('Strategische doelen');
    const goal = page.locator('[data-direction="goals"] p').nth(1);
    await expect(goal).toContainText('1. Een kindprofiel. Van opvang tot groep 8.');
    expect((await style(goal.locator('strong'), 'font-weight'))['font-weight']).toBe('700');
    await expect(goal).not.toContainText(':');
});

test('at zoom 100%: the Area headers sit top left in uppercase and the list Areas draw their items under their group titles', async ({ page }) => {
    await open(page, seed());

    const header = page.locator('[data-block="primary_activities"] > span').first();
    expect(await style(header, 'text-transform', 'font-size', 'font-weight', 'color')).toMatchObject({ 'font-size': '13px', 'font-weight': '600', color: SECONDARY });
    await expect(header).toHaveText('PRIMAIRE ACTIVITEITEN');
    const area = (await page.locator('[data-block="primary_activities"]').boundingBox())!;
    const text = (await header.boundingBox())!;
    expect(text.y - area.y).toBeLessThan(24 * 0.9);
    expect(text.x - area.x).toBeLessThan(24 * 0.9);
    await expect(page.locator('[data-block="actors"] > span').first()).toHaveText('ACTOREN');

    const group = (await page.locator('[data-kind="domain"][data-area="actors"] > span').first().boundingBox())!;
    const item = (await page.locator('[data-kind="activity"][data-area="actors"] > span').first().boundingBox())!;
    expect(item.y).toBeGreaterThanOrEqual(group.y + group.height - 1);
});

test('at zoom 100%: a Domain card shows its title, the large amount with the Share, the bar, the forecast line and the counts in the board typography', async ({ page }) => {
    await open(page, seed());

    const card = page.locator('[data-kind="domain"]').filter({ hasText: 'Onderwijs' }).first();
    const id = (await card.getAttribute('data-block'))!;
    const layer = page.locator(`[data-overlay-card="${id}"]`);

    const title = card.locator('> span').first();
    await expect(title).toBeVisible();
    expect(await style(title, 'font-size', 'font-weight')).toEqual({ 'font-size': '14px', 'font-weight': '600' });
    expect((await style(title, 'font-family'))['font-family']).toContain('Space Grotesk');
    const amount = await style(layer.locator('[data-total-amount]'), 'font-size', 'font-weight', 'line-height');
    expect([amount['font-size'], amount['font-weight']]).toEqual(['22px', '700']);
    expect(parseFloat(amount['line-height'])).toBeCloseTo(30.8, 1);
    expect(await style(layer.locator('[data-share]'), 'font-size', 'color')).toEqual({ 'font-size': '12px', color: SECONDARY });
    expect(await style(layer.locator('[data-forecast]'), 'font-size', 'color')).toEqual({ 'font-size': '11.5px', color: SECONDARY });
    expect(await style(layer.locator('[data-counts]'), 'font-size')).toEqual({ 'font-size': '11.5px' });
    expect((await style(layer.locator('[data-bar]'), 'height', 'border-top-left-radius'))['height']).toBe('6px');
    expect(await style(card, 'border-top-width', 'border-top-color', 'border-top-left-radius')).toEqual({ 'border-top-width': '1px', 'border-top-color': 'rgb(220, 223, 226)', 'border-top-left-radius': '8px' });
    await expect(layer.locator('[data-counts]')).toHaveText('4 activiteiten · 1 cluster');
    await expect(page.locator('[data-empty]').first()).toBeVisible();
    expect((await style(page.locator('[data-empty]').first(), 'font-size', 'color'))['font-size']).toBe('12px');
});

test('the amount line of every card is drawn completely inside the card, also for a childless Domain', async ({ page }) => {
    await open(page, seed());
    const clipped = await page.locator('[data-overlay-card]').evaluateAll((layers) =>
        layers.flatMap((layer) => {
            const amount = layer.querySelector('[data-total-amount]');
            if (!amount) return [];
            const box = layer.getBoundingClientRect();
            const line = amount.getBoundingClientRect();
            return line.top < box.top - 0.5 || line.bottom > box.bottom + 0.5 || line.right > box.right + 0.5 ? [layer.getAttribute('data-overlay-card')] : [];
        }),
    );
    expect(clipped).toEqual([]);
    expect(await page.locator('[data-total-amount]').count()).toBeGreaterThan(1);
});

test('at zoom 100%: the Area total reads the amount in strong ink and the percentage in secondary text', async ({ page }) => {
    await open(page, seed());
    const total = page.locator('[data-area-total]').first();
    expect(await style(total.locator('strong'), 'color', 'font-weight')).toEqual({ color: INK, 'font-weight': '600' });
    expect((await style(total, 'color'))['color']).toBe(SECONDARY);
});

test('one side panel at a time: the totals panel gives way to the detail panel of a selected Domain', async ({ page }) => {
    await open(page, seed());
    const panel = page.locator('[data-figure-panel]');
    await expect(panel).toBeVisible();
    expect(await style(panel, 'border-top-left-radius', 'padding-top', 'background-color')).toEqual({ 'border-top-left-radius': '10px', 'padding-top': '16px', 'background-color': 'rgb(255, 255, 255)' });
    expect((await panel.boundingBox())!.width).toBe(334);

    const total = panel.locator('[data-canvas-total]');
    expect(await style(total, 'font-size', 'font-weight', 'line-height')).toEqual({ 'font-size': '30px', 'font-weight': '700', 'line-height': '42px' });
    expect((await style(total, 'font-family'))['font-family']).toContain('Space Grotesk');
    expect((await style(panel.locator('[data-closing]'), 'background-color'))['background-color']).toBe('rgb(231, 243, 236)');
    expect((await style(panel.locator('h3').first(), 'font-size', 'font-weight'))['font-size']).toBe('13px');
    await expect(panel.locator('[data-group]').nth(1)).toHaveCSS('border-top-width', '1px');

    await page.locator('[data-kind="domain"]').filter({ hasText: 'Onderwijs' }).first().click();
    await expect(page.locator('#detail-panel')).toBeVisible();
    await expect(panel).toHaveCount(0);
    await expect(page.locator('[data-block]').filter({ hasText: 'Onderwijs' }).first()).toHaveCSS('outline-width', '2px');
    await page.keyboard.press('Escape');
    await expect(panel).toBeVisible();
});

test('the legend is a single line and the breakdown popover has an ink border and radius 6', async ({ page }) => {
    await open(page, seed());
    const legend = page.locator('[data-legend="financial"]');
    expect((await legend.boundingBox())!.height).toBeLessThan(30);
    await expect(legend).toContainText('Aandeel van totaal');
    await expect(legend).toContainText('streep = budget, balk = prognose');

    await page.locator('[data-kind="domain"]').filter({ hasText: 'Onderwijs' }).first().hover();
    const popover = page.locator('[data-hover-content]');
    await expect(popover.locator('[data-breakdown]')).toBeVisible();
    expect(await style(popover, 'border-top-color', 'border-top-left-radius', 'padding-top', 'padding-left')).toEqual({ 'border-top-color': INK, 'border-top-left-radius': '6px', 'padding-top': '8px', 'padding-left': '10px' });
});
