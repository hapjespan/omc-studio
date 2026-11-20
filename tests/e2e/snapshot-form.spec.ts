import { randomUUID } from 'node:crypto';
import { expect, test } from './support/guards';
import { seedAllocation } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('an empty name is refused with its per-field message', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/versions`);

    await page.getByRole('button', { name: 'Momentopname maken' }).click();
    const panel = page.getByRole('dialog', { name: 'Momentopname maken' });
    await panel.getByRole('button', { name: 'Opslaan' }).click();

    await expect(panel.getByRole('link', { name: 'Naam: vul een naam in van 1 tot 150 tekens' })).toBeVisible();
    await expect(panel.getByLabel('Naam')).toBeFocused();
});

test('deleting states what goes, removes the row and offers no undo', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/versions`);
    await page.getByRole('button', { name: 'Momentopname maken' }).click();
    const panel = page.getByRole('dialog', { name: 'Momentopname maken' });
    await panel.getByLabel('Naam').fill('Tijdelijk');
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await page.getByRole('button', { name: 'Tijdelijk', exact: true }).click();
    await page.getByRole('dialog', { name: 'Tijdelijk' }).getByRole('button', { name: 'Verwijderen' }).click();

    const dialog = page.getByRole('dialog', { name: 'Tijdelijk verwijderen?' });
    await expect(dialog).toContainText('Ongedaan maken kan niet');
    await dialog.getByRole('button', { name: 'Verwijderen' }).click();

    await expect(page.getByRole('row', { name: /Tijdelijk/ })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Ongedaan maken' })).toHaveCount(0);
});
