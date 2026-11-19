import { expect, test } from '../support/guards';
import { seedOrganisation, tinker, unique } from '../support/overview';
import { signInAs } from '../support/shell';

const open = async (page: import('@playwright/test').Page, canvas: string, item: string): Promise<void> => {
    await page.getByRole('button', { name: `Acties voor ${canvas}` }).click();
    await page.getByRole('menuitem', { name: item }).click();
};

test('Nieuw canvas starts from Leeg canvas, refuses a name in use and opens Invoeren', async ({ page }) => {
    const organisation = unique('Noord');
    const existing = unique('Bestaand');
    tinker("app(App\\Services\\Operations\\InstallSteps::class)->run();");
    seedOrganisation(organisation, [existing]);
    await signInAs(page, 'administrator');
    await page.goto('/overview');

    await page.getByRole('button', { name: 'Nieuw canvas' }).click();
    const dialog = page.getByRole('dialog', { name: 'Nieuw canvas' });
    await dialog.getByLabel('Naam').fill(` ${existing.toUpperCase()} `);
    await dialog.getByRole('combobox', { name: 'Organisatie' }).click();
    await page.getByRole('option', { name: organisation }).click();
    await expect(dialog.getByRole('combobox', { name: 'Start vanuit' })).toContainText('Sjabloon: Leeg canvas');
    await dialog.getByRole('button', { name: 'Aanmaken' }).click();
    await expect(dialog.getByRole('link', { name: 'Naam: dit canvas bestaat al in deze organisatie' })).toBeVisible();
    await expect(dialog.getByLabel('Naam')).toHaveAttribute('aria-invalid', 'true');

    const fresh = unique('Operating model');
    await dialog.getByLabel('Naam').fill(fresh);
    await dialog.getByRole('button', { name: 'Aanmaken' }).click();
    await page.waitForURL(/\/canvases\/\d+\/entry/);
});

test('rename changes the card name', async ({ page }) => {
    const canvas = unique('Oud');
    const renamed = unique('Nieuw');
    seedOrganisation(unique('Noord'), [canvas]);
    await signInAs(page, 'administrator');
    await page.goto('/overview');

    await open(page, canvas, 'Naam wijzigen');
    const dialog = page.getByRole('dialog', { name: 'Naam wijzigen' });
    await dialog.getByLabel('Naam').fill(renamed);
    await dialog.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByRole('link', { name: renamed, exact: true })).toBeVisible();
});

test('copy within the Organisation needs a free name and archive hides the card', async ({ page }) => {
    const canvas = unique('Model');
    const copy = unique('Kopie');
    seedOrganisation(unique('Noord'), [canvas]);
    await signInAs(page, 'administrator');
    await page.goto('/overview');

    await open(page, canvas, 'Kopiëren');
    const dialog = page.getByRole('dialog', { name: 'Canvas kopiëren' });
    await dialog.getByLabel('Naam').fill(copy);
    await dialog.getByRole('button', { name: 'Kopiëren' }).click();
    await page.waitForURL(/\/canvases\/\d+\/entry/);
    await page.goto('/overview');
    await expect(page.getByRole('link', { name: copy, exact: true })).toBeVisible();

    await open(page, copy, 'Archiveren');
    await page.getByRole('dialog', { name: `${copy} archiveren?` }).getByRole('button', { name: 'Archiveren' }).click();
    await expect(page.getByRole('link', { name: copy, exact: true })).toHaveCount(0);
});

test('Verwijderen shows the counts and enables the button only after the exact name', async ({ page }) => {
    const canvas = unique('Model');
    seedOrganisation(unique('Noord'), [canvas]);
    await signInAs(page, 'administrator');
    await page.goto('/overview');

    await open(page, canvas, 'Verwijderen');
    const dialog = page.getByRole('dialog', { name: `${canvas} verwijderen?` });
    await expect(dialog).toContainText('0 elementen');
    const confirm = dialog.getByRole('button', { name: 'Verwijderen' });
    await expect(confirm).toBeDisabled();
    await dialog.getByLabel('Naam ter bevestiging').fill(canvas.toLowerCase());
    await expect(confirm).toBeDisabled();
    await dialog.getByLabel('Naam ter bevestiging').fill(canvas);
    await expect(confirm).toBeEnabled();
    await confirm.click();
    await expect(page.getByRole('link', { name: canvas, exact: true })).toHaveCount(0);
});
