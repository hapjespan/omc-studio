import { randomUUID } from 'node:crypto';
import { expect, test } from './support/guards';
import { seedAllocation, expandTarget } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('Set leegmaken asks confirmation, shows Onbekende allocatie and offers Ongedaan maken with focus', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/allocation?year=2026&cost_record=${seed.records.Licentie}`);
    await expandTarget(page, 'Inkoop');

    await page.getByRole('button', { name: 'Toevoegen aan verdeling: Offreren' }).click();
    await page.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByRole('button', { name: /Licentie/ })).toContainText('Toegewezen');

    await page.getByRole('button', { name: 'Set leegmaken' }).click();
    const dialog = page.getByRole('dialog', { name: 'Set leegmaken?' });
    await dialog.getByRole('button', { name: 'Leegmaken' }).click();

    await expect(page.getByRole('button', { name: /Licentie/ })).toContainText('Onbekende allocatie');
    const undo = page.getByRole('button', { name: 'Ongedaan maken' });
    await expect(undo).toBeFocused();
    await undo.click();
    await expect(page.getByRole('button', { name: /Licentie/ })).toContainText('Toegewezen');
});
