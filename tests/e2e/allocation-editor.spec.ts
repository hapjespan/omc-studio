import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedAllocation } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('three lines of 33,33 / 33,33 / 33,34 leave 0,00% and enable Opslaan; 99,99% and 100,01% do not', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/allocation?year=2026&cost_record=${seed.records.Licentie}`);

    for (const name of ['Offreren', 'Bestellen', 'Betalen']) await page.getByRole('button', { name: `Toevoegen aan verdeling: ${name}` }).click();
    const fill = async (name: string, value: string): Promise<void> => {
        const input = page.getByLabel(new RegExp(`Percentage voor .*${name}`));
        await input.fill(value);
        await input.blur();
    };
    await fill('Offreren', '33,33');
    await fill('Bestellen', '33,33');
    await fill('Betalen', '33,33');
    await expect(page.getByTestId('remaining')).toHaveText('0,01%');
    await expect(page.getByRole('button', { name: 'Opslaan' })).toBeDisabled();

    await fill('Betalen', '33,34');
    await expect(page.getByTestId('remaining')).toHaveText('0,00%');
    await expect(page.getByRole('status').filter({ hasText: 'Nog te verdelen: 0,00%' })).toBeAttached();
    await expect(page.getByRole('button', { name: 'Opslaan' })).toBeEnabled();

    await fill('Betalen', '33,35');
    await expect(page.getByTestId('remaining')).toHaveText('-0,01%');
    await expect(page.getByRole('button', { name: 'Opslaan' })).toBeDisabled();
    await expectNoAxeViolations(page);

    await fill('Betalen', '33,34');
    await page.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByText('Verdeling opgeslagen').first()).toBeAttached();
});
