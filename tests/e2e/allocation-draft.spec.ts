import { randomUUID } from 'node:crypto';
import { expect, test } from './support/guards';
import { seedAllocation } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('unsaved changes show a Concept, keep Opslaan disabled, can be discarded and ask before leaving', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000, Beheer: 5000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/allocation?year=2026&cost_record=${seed.records.Licentie}`);

    await page.getByRole('button', { name: 'Toevoegen aan verdeling: Offreren' }).click();
    const input = page.getByLabel(/Percentage voor Inkoop > Offreren/);
    await input.fill('40');
    await input.blur();
    await expect(page.getByTestId('draft')).toHaveText('Concept 40,00%');
    await expect(page.getByRole('button', { name: 'Opslaan' })).toBeDisabled();
    await expect(page.getByText('Opslaan kan zodra er 0,00% te verdelen is. Een kostenpost is altijd 0% of 100% toegewezen.')).toBeVisible();

    await page.getByRole('button', { name: /Beheer/ }).click();
    const dialog = page.getByRole('dialog', { name: 'Wijzigingen weggooien?' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Blijven' }).click();
    await expect(page.getByTestId('draft')).toBeVisible();

    await page.getByRole('button', { name: 'Wijzigingen weggooien' }).click();
    await expect(page.getByTestId('draft')).toHaveCount(0);
    await expect(page.getByLabel(/Percentage voor/)).toHaveCount(0);
});
