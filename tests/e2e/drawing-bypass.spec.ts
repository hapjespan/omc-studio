import { expect, test } from './support/guards';
import { openDrawing, tinker } from './support/drawing';
import { seedViewCanvas } from './support/view';

test('a missing Element refuses the save and the drawing is rebuilt from the stored data', async ({ page }) => {
    const canvas = seedViewCanvas();
    const layers = await openDrawing(page, canvas);
    tinker(`$a = App\\Models\\Area::where('key', 'actors')->value('id'); App\\Models\\Element::create(['canvas_id' => ${canvas.id}, 'area_id' => $a, 'kind' => 'domain', 'name' => 'Stiekem', 'position' => 9]);`);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const x = page.getByLabel('X van Onderwijs');
    await x.fill('44');
    await x.press('Enter');
    await expect(page.locator('[data-announcer]')).toContainText('De tekening is opnieuw opgebouwd uit de opgeslagen gegevens');
    await expect(page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Stiekem', exact: true })).toBeVisible();
});
