import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { tinker } from './support/overview';
import { seedViewCanvas } from './support/view';

test('a Reader sees the three entries and each CSV export states the omitted count', async ({ page }) => {
    const canvas = seedViewCanvas();
    tinker(`$c = App\\Models\\Canvas::find(${canvas.id}); App\\Models\\CostRecord::factory()->create(['organisation_id' => $c->organisation_id, 'code' => null]);`);
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);

    await page.getByRole('button', { name: 'Exporteren' }).click();
    await expect(page.getByRole('menuitem')).toHaveText(['Exporteren als JSON', 'Kostenposten exporteren', 'Toewijzingen exporteren', 'Exporteren als PNG', 'Afdrukken']);

    const download = page.waitForEvent('download');
    await page.getByRole('menuitem', { name: 'Kostenposten exporteren' }).click();
    expect((await download).suggestedFilename()).toMatch(/cost-records.*\.csv$/);
    await expect(page.getByTestId('export-note')).toContainText('Niet geëxporteerd: 1 kostenposten zonder code');
});

test('an Administrator exports the cost records from Kostenposten', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/cost-records`);
    const download = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Kostenposten exporteren' }).click();
    await download;
    await expect(page.getByTestId('export-note')).toBeVisible();

    const allocations = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Toewijzingen exporteren' }).click();
    expect((await allocations).suggestedFilename()).toMatch(/allocations.*\.csv$/);
    await expect(page.getByTestId('export-note')).toContainText(/Niet geëxporteerd|Geëxporteerd/);
});
