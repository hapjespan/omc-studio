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
