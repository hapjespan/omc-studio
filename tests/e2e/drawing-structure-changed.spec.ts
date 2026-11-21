import { execFileSync } from 'node:child_process';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { openDrawing } from './support/drawing';
import { seedViewCanvas } from './support/view';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

function changeStructure(canvas: number): void {
    const code = `$a = App\\Models\\Area::where('key', 'primary_activities')->value('id'); App\\Models\\Element::create(['canvas_id' => ${canvas}, 'area_id' => $a, 'kind' => 'domain', 'name' => 'Erbij', 'position' => 9]); App\\Models\\Canvas::whereKey(${canvas})->increment('structure_version');`;
    execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env });
}

test('a change is saved automatically and survives a reload', async ({ page }) => {
    const canvas = seedViewCanvas();
    const layers = await openDrawing(page, canvas);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const x = page.getByLabel('X van Onderwijs');
    await x.fill('44');
    await x.press('Enter');
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    await expect(page.getByRole('button', { name: /opslaan$/i })).toHaveCount(0);
    await page.reload();
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await expect(page.getByLabel('X van Onderwijs')).toHaveValue('44');
});

test('a newer structure pauses saving and the user applies the layout to what still exists', async ({ page }) => {
    const canvas = seedViewCanvas();
    const layers = await openDrawing(page, canvas);
    changeStructure(canvas.id);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const x = page.getByLabel('X van Onderwijs');
    await x.fill('52');
    await x.press('Enter');
    const dialog = page.getByRole('dialog', { name: 'De structuur is gewijzigd' });
    await expect(dialog).toBeVisible();
    await expectNoAxeViolations(page);
    await dialog.getByRole('button', { name: 'Opmaak toepassen op wat nog bestaat' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    await expect(page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Erbij', exact: true })).toBeVisible();
    await page.reload();
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await expect(page.getByLabel('X van Onderwijs')).toHaveValue('52');
});

test('reloading from the dialog drops the change', async ({ page }) => {
    const canvas = seedViewCanvas();
    const layers = await openDrawing(page, canvas);
    changeStructure(canvas.id);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const x = page.getByLabel('X van Onderwijs');
    const start = await x.inputValue();
    await x.fill('60');
    await x.press('Enter');
    const dialog = page.getByRole('dialog', { name: 'De structuur is gewijzigd' });
    await dialog.getByRole('button', { name: 'Opnieuw laden' }).click();
    await expect(dialog).toBeHidden();
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await expect(page.getByLabel('X van Onderwijs')).toHaveValue(start);
});
