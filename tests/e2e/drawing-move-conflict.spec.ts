import { expect, test } from './support/guards';
import { dragTo, layoutChanges, ACTIVITY, openDrawing, seedMoveCanvas, structureChanges, DOMAIN_TARGET } from './support/drawing';

test('a newer structure offers the choice, applies it to what still exists and asks again', async ({ page }) => {
    const canvas = seedMoveCanvas();
    await openDrawing(page, canvas);
    structureChanges(canvas.id, "$a = App\\Models\\Area::where('key', 'actors')->value('id'); App\\Models\\Element::create(['canvas_id' => " + canvas.id + ", 'area_id' => $a, 'kind' => 'domain', 'name' => 'Erbij', 'position' => 9]);");
    await dragTo(page, ACTIVITY, DOMAIN_TARGET);
    await page.keyboard.press('Enter');
    const conflict = page.getByRole('dialog', { name: 'De structuur is gewijzigd' });
    await expect(conflict).toBeVisible();
    await conflict.getByRole('button', { name: 'Opmaak toepassen op wat nog bestaat' }).click();
    const ask = page.getByRole('dialog', { name: 'Verplaatsen naar Primaire activiteiten › Verkoop?' });
    await expect(ask).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(page.locator('[data-announcer]')).toContainText('Les geven hangt nu onder Verkoop');
});

test('reloading after a newer structure drops the move and says so', async ({ page }) => {
    const canvas = seedMoveCanvas();
    await openDrawing(page, canvas);
    structureChanges(canvas.id, "App\\Models\\Element::where('canvas_id', " + canvas.id + ")->where('name', 'Verkoop')->delete();");
    await dragTo(page, ACTIVITY, DOMAIN_TARGET);
    await page.keyboard.press('Enter');
    await page.getByRole('dialog', { name: 'De structuur is gewijzigd' }).getByRole('button', { name: 'Opnieuw laden' }).click();
    await expect(page.locator('[data-announcer]')).toContainText('De verplaatsing is niet opgeslagen');
});

test('a newer Layout reloads with the message and the move is not saved', async ({ page }) => {
    const canvas = seedMoveCanvas();
    await openDrawing(page, canvas);
    layoutChanges(canvas.id);
    await dragTo(page, ACTIVITY, DOMAIN_TARGET);
    await page.keyboard.press('Enter');
    await expect(page.locator('[data-announcer]')).toContainText('Iemand anders heeft de opmaak gewijzigd');
    await expect(page.locator('[data-announcer]')).toContainText('De verplaatsing is niet opgeslagen');
});
