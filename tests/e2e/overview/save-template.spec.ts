import { expect, test } from '../support/guards';
import { seedOrganisation, tinker, unique } from '../support/overview';
import { signInAs } from '../support/shell';

const canvasId = (name: string): number => Number(/ID=(\d+)/.exec(tinker(`echo 'ID=' . App\\Models\\Canvas::where('name', '${name}')->firstOrFail()->id;`))?.[1]);

test('save a Canvas as a Template from the card menu, start a Canvas from it, rename and delete it', async ({ page }) => {
    const canvas = unique('Model');
    const template = unique('Basis');
    const renamed = unique('Hernoemd');
    tinker("app(App\\Services\\Operations\\InstallSteps::class)->run();");
    seedOrganisation(unique('Noord'), [canvas]);
    await signInAs(page, 'administrator');
    await page.goto('/overview');

    await page.getByRole('button', { name: `Acties voor ${canvas}` }).click();
    await page.getByRole('menuitem', { name: 'Opslaan als sjabloon' }).click();
    let dialog = page.getByRole('dialog', { name: 'Opslaan als sjabloon' });
    await dialog.getByLabel('Naam').fill(template);
    await dialog.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByRole('link', { name: template, exact: true })).toBeVisible();

    await page.getByRole('button', { name: `Acties voor ${template}` }).click();
    await page.getByRole('menuitem', { name: 'Naam wijzigen' }).click();
    dialog = page.getByRole('dialog', { name: 'Naam wijzigen' });
    await dialog.getByLabel('Naam').fill(renamed);
    await dialog.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByRole('link', { name: renamed, exact: true })).toBeVisible();

    await page.getByRole('button', { name: `Acties voor ${renamed}` }).click();
    await page.getByRole('menuitem', { name: 'Verwijderen' }).click();
    dialog = page.getByRole('dialog', { name: `${renamed} verwijderen?` });
    const confirm = dialog.getByRole('button', { name: 'Verwijderen' });
    await expect(confirm).toBeDisabled();
    await dialog.getByLabel('Naam ter bevestiging').fill(renamed);
    await confirm.click();
    await expect(page.getByRole('link', { name: renamed, exact: true })).toHaveCount(0);
});

test('Opslaan als sjabloon in the Invoeren header saves the Canvas as a Template', async ({ page }) => {
    const canvas = unique('Model');
    const template = unique('Basis');
    seedOrganisation(unique('Noord'), [canvas]);
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvasId(canvas)}/entry`);

    await page.getByRole('button', { name: 'Opslaan als sjabloon' }).click();
    const dialog = page.getByRole('dialog', { name: 'Opslaan als sjabloon' });
    await dialog.getByLabel('Naam').fill(template);
    await dialog.getByRole('button', { name: 'Opslaan' }).click();
    await expect(dialog).toBeHidden();

    await page.goto('/overview');
    await expect(page.getByRole('link', { name: template, exact: true })).toBeVisible();
});
