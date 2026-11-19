import { expect, expectNoAxeViolations, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

const fill = "for ($i = 0; $i < 25; $i++) { $d = App\\Models\\Element::factory()->create(['canvas_id' => $c->id, 'area_id' => $a->id, 'kind' => 'domain', 'name' => 'Domein ' . $i, 'position' => $i]); for ($j = 0; $j < 19; $j++) { App\\Models\\Element::factory()->create(['canvas_id' => $c->id, 'area_id' => $a->id, 'kind' => 'activity', 'parent_id' => $d->id, 'name' => 'Activiteit ' . $i . '-' . $j, 'position' => $j]); } }";

test('Invoeren opens and an Element is added within one second on a Canvas of 500 Elements', async ({ page }) => {
    const canvas = seedEntryCanvas('primary_activities', [], fill);
    await openEntry(page, canvas);
    await page.goto('about:blank');

    const opened = Date.now();
    await page.goto(`/canvases/${canvas}/entry`);
    await expect(page.getByTestId('entry-row').first()).toBeVisible();
    expect(Date.now() - opened).toBeLessThan(1000);
    await expect(page.getByTestId('entry-row')).toHaveCount(500);

    const line = page.getByLabel('Activiteit of cluster toevoegen aan Domein 0, Enter om op te slaan');
    await line.fill('Nieuw');
    const added = Date.now();
    await line.press('Enter');
    await expect(page.getByTestId('entry-row').filter({ hasText: 'Nieuw' })).toBeVisible();
    expect(Date.now() - added).toBeLessThan(1000);
    await expectNoAxeViolations(page);
});
