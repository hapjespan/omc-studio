import { randomUUID } from 'node:crypto';
import { expect, test } from './support/guards';
import { seedAllocation } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('the mode switch is absent on Toewijzen, Kostenposten and Versies', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'administrator');
    for (const path of ['allocation?year=2026', 'cost-records?year=2026', 'versions']) {
        await page.goto(`/canvases/${seed.canvas}/${path}`);
        await expect(page.getByRole('main')).toBeVisible();
        await expect(page.getByRole('group', { name: 'Modus' })).toHaveCount(0);
    }
});

test('the mode switch shows exactly one active segment on Invoeren, Tekenen and Bekijken', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'administrator');
    for (const [path, name] of [['entry', 'Invoeren'], ['drawing', 'Tekenen'], ['view', 'Bekijken']]) {
        await page.goto(`/canvases/${seed.canvas}/${path}`);
        const group = page.getByRole('group', { name: 'Modus' });
        await expect(group).toHaveCount(1);
        await expect(group.locator('[aria-current="page"]')).toHaveCount(1);
        await expect(group.getByRole('link', { name })).toHaveAttribute('aria-current', 'page');
    }
});

test('a Snapshot view keeps the mode switch with Bekijken active', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/versions`);
    await page.getByRole('button', { name: 'Momentopname maken' }).click();
    const panel = page.getByRole('dialog', { name: 'Momentopname maken' });
    await panel.getByLabel('Naam').fill('Stand');
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByRole('row', { name: /Stand/ })).toBeVisible();
    await page.getByRole('row', { name: /Stand/ }).getByRole('link').first().click();
    await page.waitForURL(/\/snapshots\/\d+\/view/);
    const group = page.getByRole('group', { name: 'Modus' });
    await expect(group).toHaveCount(1);
    await expect(group.getByRole('link', { name: 'Bekijken' })).toHaveAttribute('aria-current', 'page');
});

test('Tekenen and Bekijken keep the artboard at the same screen position and scale', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/view`);
    const group = page.getByRole('group', { name: 'Modus' });
    const artboard = page.locator('[data-artboard]');
    const viewPosition = () => artboard.evaluate((el) => {
        const { x, y, width } = el.getBoundingClientRect();
        return [x, y, width / (el as HTMLElement).offsetWidth];
    });
    const drawingPosition = () => page.locator('[data-view]').evaluate((el) => {
        const bounds = el.querySelector('canvas')!.getBoundingClientRect();
        const view = JSON.parse(el.getAttribute('data-view')!);
        return [bounds.x + view.x, bounds.y + view.y, view.zoom];
    });
    const matches = (actual: number[], expected: number[]) => actual.every((value, i) => Math.abs(value - expected[i]) < (i === 2 ? 0.001 : 1));
    await page.getByRole('button', { name: 'Inzoomen' }).click();
    await page.locator('[data-scroller]').evaluate((el) => el.scrollTo(110, 80));
    const before = await viewPosition();
    await group.getByRole('link', { name: 'Tekenen' }).click();
    await expect(page.locator('[data-view]')).toHaveAttribute('data-view', /zoom/);
    await expect.poll(async () => matches(await drawingPosition(), before)).toBe(true);

    await page.getByRole('button', { name: 'Beeld naar rechts' }).click();
    await expect.poll(async () => (await drawingPosition())[0]).not.toBe(before[0]);
    await group.getByRole('link', { name: 'Bekijken' }).scrollIntoViewIfNeeded();
    const after = await drawingPosition();
    await group.getByRole('link', { name: 'Bekijken' }).click();
    await expect.poll(async () => matches(await viewPosition(), after)).toBe(true);
});
