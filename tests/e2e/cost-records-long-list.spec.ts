import { randomUUID } from 'node:crypto';
import { expect, test } from './support/guards';
import { seedOrganisation } from './support/finance';
import { seedPhp } from './support/seed';
import { signInAs } from './support/shell';

const TOTAL = 150;

test('a list longer than the first chunk shows every row and selects every row after the rest has loaded', async ({ page }) => {
    const organisation = seedOrganisation(`Lang ${randomUUID().slice(0, 8)}`);
    seedPhp(`$w = App\\Models\\Owner::factory()->create(['organisation_id' => ${organisation}]); App\\Models\\CostRecord::factory()->count(${TOTAL})->create(['organisation_id' => ${organisation}, 'owner_id' => $w->id])`);
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);

    await expect(page.locator('tbody tr')).toHaveCount(TOTAL, { timeout: 20000 });
    const all = page.getByRole('checkbox', { name: 'Alle rijen selecteren' });
    await expect(all).toBeEnabled();
    await all.check();
    await expect(page.getByText(`${TOTAL} geselecteerd`)).toBeVisible();
});

test('the select-all checkbox is disabled until the rest of the rows has arrived', async ({ page }) => {
    const organisation = seedOrganisation(`Lang ${randomUUID().slice(0, 8)}`);
    seedPhp(`$w = App\\Models\\Owner::factory()->create(['organisation_id' => ${organisation}]); App\\Models\\CostRecord::factory()->count(${TOTAL})->create(['organisation_id' => ${organisation}, 'owner_id' => $w->id])`);
    await signInAs(page, 'administrator');
    await page.route('**/organisations/*/cost-records*', async (route) => {
        if (route.request().headers()['x-inertia-partial-data']?.includes('moreRows')) await new Promise((resolve) => setTimeout(resolve, 1500));
        await route.continue();
    });
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);

    const all = page.getByRole('checkbox', { name: 'Alle rijen selecteren' });
    await expect(page.locator('tbody tr').first()).toBeVisible();
    await expect(all).toBeDisabled();
    await expect(page.locator('tbody tr')).toHaveCount(TOTAL, { timeout: 20000 });
    await expect(all).toBeEnabled();
});

test('a page opened without a year makes no repeated visits while the rest of the rows loads', async ({ page }) => {
    const organisation = seedOrganisation(`Zonder ${randomUUID().slice(0, 8)}`, []);
    await signInAs(page, 'administrator');
    const visits: string[] = [];
    page.on('request', (request) => {
        if (request.headers()['x-inertia']) visits.push(request.url());
    });
    await page.goto(`/organisations/${organisation}/cost-records`);
    await expect(page.getByRole('button', { name: 'Boekjaren' })).toBeVisible();
    await page.waitForTimeout(2000);

    expect(visits.length).toBeLessThanOrEqual(1);
});
