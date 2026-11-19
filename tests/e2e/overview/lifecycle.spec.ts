import { expect, test } from '../support/guards';
import { seedOrganisation, stubEntry, tinker, unique } from '../support/overview';
import { signInAs } from '../support/shell';

test('create from Leeg canvas, copy within and across Organisations, archive, restore and delete', async ({ page }) => {
    const source = unique('Noord');
    const target = unique('Zuid');
    const canvas = unique('Operating model');
    const within = unique('Kopie');
    const across = unique('Elders');
    tinker("app(App\\Services\\Operations\\InstallSteps::class)->run();");
    seedOrganisation(source);
    seedOrganisation(target);
    await signInAs(page, 'administrator');
    const entry = stubEntry(page);
    await page.goto('/overview');

    await page.getByRole('button', { name: 'Nieuw canvas' }).click();
    let dialog = page.getByRole('dialog', { name: 'Nieuw canvas' });
    await dialog.getByLabel('Naam').fill(canvas);
    await dialog.getByRole('combobox', { name: 'Organisatie' }).click();
    await page.getByRole('option', { name: source }).click();
    await dialog.getByRole('button', { name: 'Aanmaken' }).click();
    await entry.next();

    await page.goto('/overview');
    const menu = async (name: string, item: string): Promise<void> => {
        await page.getByRole('button', { name: `Acties voor ${name}` }).click();
        await page.getByRole('menuitem', { name: item }).click();
    };

    await menu(canvas, 'Kopiëren');
    dialog = page.getByRole('dialog', { name: 'Canvas kopiëren' });
    await dialog.getByLabel('Naam').fill(within);
    await dialog.getByRole('button', { name: 'Kopiëren' }).click();
    await entry.next();
    await page.goto('/overview');

    await menu(canvas, 'Kopiëren');
    dialog = page.getByRole('dialog', { name: 'Canvas kopiëren' });
    await dialog.getByLabel('Naam').fill(across);
    await dialog.getByRole('combobox', { name: 'Organisatie' }).click();
    await page.getByRole('option', { name: target }).click();
    await dialog.getByRole('button', { name: 'Kopiëren' }).click();
    await entry.next();
    await page.goto('/overview');
    await expect(page.getByRole('link', { name: within, exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: across, exact: true })).toBeVisible();

    await menu(within, 'Archiveren');
    await page.getByRole('dialog', { name: `${within} archiveren?` }).getByRole('button', { name: 'Archiveren' }).click();
    await expect(page.getByRole('link', { name: within, exact: true })).toHaveCount(0);
    await page.getByLabel('Toon gearchiveerd').check();
    await expect(page.getByRole('link', { name: within, exact: true })).toBeVisible();
    await menu(within, 'Terugzetten');
    await page.getByLabel('Toon gearchiveerd').uncheck();
    await expect(page.getByRole('link', { name: within, exact: true })).toBeVisible();

    await menu(within, 'Verwijderen');
    dialog = page.getByRole('dialog', { name: `${within} verwijderen?` });
    await dialog.getByLabel('Naam ter bevestiging').fill(within);
    await dialog.getByRole('button', { name: 'Verwijderen' }).click();
    await expect(page.getByRole('link', { name: within, exact: true })).toHaveCount(0);
    await expect(page.getByRole('link', { name: canvas, exact: true })).toBeVisible();
});
