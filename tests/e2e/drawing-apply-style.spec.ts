import { execFileSync } from 'node:child_process';
import { expect, expectNoAxeViolations, test, type Page } from './support/guards';
import { openDrawing, seedRow } from './support/drawing';
import { seedViewCanvas } from './support/view';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

function seedDomains() {
    const canvas = seedViewCanvas();
    const code = `$a = App\\Models\\Area::where('key', 'primary_activities')->value('id'); foreach (['Tweede', 'Derde'] as $i => $n) { App\\Models\\Element::create(['canvas_id' => ${canvas.id}, 'area_id' => $a, 'kind' => 'domain', 'name' => $n, 'position' => 5 + $i]); }`;
    execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env });
    return canvas;
}


const boxes = async (page: Page, layers: ReturnType<Page['locator']>, names: string[]) => {
    const result: string[][] = [];
    for (const name of names) {
        await layers.getByRole('button', { name, exact: true }).click();
        result.push(await Promise.all(['X', 'Y', 'B (breedte)', 'H (hoogte)'].map((label) => page.getByLabel(`${label} van ${name}`).inputValue())));
    }
    return result;
};

const sizes = (page: Page, names: string[]) => Promise.all(names.map(async (name) => page.getByLabel(`B (breedte) van ${name}`).inputValue()));

test('the style of a Domain goes to every Domain of its Area after confirmation, places and sizes unchanged', async ({ page }) => {
    const layers = await openDrawing(page, seedDomains());
    const layer = (name: string) => layers.getByRole('button', { name, exact: true });
    await layer('Onderwijs').click();
    const fill = page.getByLabel('Vulling van Onderwijs', { exact: true });
    await fill.fill('#223344');
    await fill.press('Enter');
    await expect(page.getByRole('button', { name: 'Stijl naar alle activiteiten in dit domein' })).toHaveCount(0);
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    const width = await sizes(page, ['Onderwijs']);
    const siblings = await boxes(page, layers, ['Tweede', 'Derde']);
    await layer('Onderwijs').click();

    await page.getByRole('button', { name: 'Stijl naar alle domeinen hier' }).click();
    const dialog = page.getByRole('dialog', { name: 'Stijl van Onderwijs toepassen?' });
    await expect(dialog).toBeVisible();
    await expectNoAxeViolations(page);
    await dialog.getByRole('button', { name: 'Annuleren' }).click();
    await expect(dialog).toBeHidden();

    await layer('Tweede').click();
    await expect(page.getByLabel('Vulling van Tweede', { exact: true })).not.toHaveValue('#223344');
    await layer('Onderwijs').click();
    expect(await sizes(page, ['Onderwijs'])).toEqual(width);
    await page.getByRole('button', { name: 'Stijl naar alle domeinen hier' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Stijl toepassen' }).click();
    await expect(page.locator('[data-announcer]')).toContainText('Stijl van Onderwijs toegepast op 2 andere');
    for (const name of ['Tweede', 'Derde']) {
        await layer(name).click();
        await expect(page.getByLabel(`Vulling van ${name}`, { exact: true })).toHaveValue('#223344');
    }
    await layer('Beheer').click();
    await expect(page.getByLabel('Vulling van Beheer', { exact: true })).not.toHaveValue('#223344');
    expect(await boxes(page, layers, ['Tweede', 'Derde'])).toEqual(siblings);
    await expect(page.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');
    await page.reload();
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true }).click();
    expect(await sizes(page, ['Onderwijs'])).toEqual(width);
});

test('the style of an Activity goes to the Activities of its Domain only', async ({ page }) => {
    const canvas = seedRow();
    const layers = await openDrawing(page, canvas);
    const layer = (name: string) => layers.getByRole('button', { name, exact: true });
    await layer('Alfa').click();
    const fill = page.getByLabel('Vulling van Alfa', { exact: true });
    await fill.fill('#334455');
    await fill.press('Enter');
    await page.getByRole('button', { name: 'Stijl naar alle activiteiten in dit domein' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Stijl toepassen' }).click();
    for (const name of ['Beta', 'Gamma', 'Les geven']) {
        await layer(name).click();
        await expect(page.getByLabel(`Vulling van ${name}`, { exact: true })).toHaveValue('#334455');
    }
    await layer('Plannen').click();
    await expect(page.getByLabel('Vulling van Plannen', { exact: true })).not.toHaveValue('#334455');
});
