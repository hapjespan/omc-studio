import { randomUUID } from 'node:crypto';
import { expect, test } from './support/guards';
import { seedAllocation } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('rest to a line, even split and the split over the activities of a Domain', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 }, [
        { domain: 'Inkoop', activities: ['Offreren', 'Bestellen', 'Betalen'] },
        { domain: 'Leeg', activities: [] },
    ]);
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/allocation?year=2026&cost_record=${seed.records.Licentie}`);

    await page.getByRole('button', { name: 'Toevoegen aan verdeling: Offreren' }).click();
    await page.getByRole('button', { name: 'Toevoegen aan verdeling: Bestellen' }).click();
    const first = page.getByLabel(/Percentage voor Inkoop > Offreren/);
    await first.fill('50');
    await first.blur();
    const second = page.getByLabel(/Percentage voor Inkoop > Bestellen/);
    await second.fill('30');
    await second.blur();
    await page.getByRole('button', { name: /Rest naar deze regel: Inkoop > Bestellen/ }).click();
    await expect(second).toHaveValue('50,00');
    await expect(page.getByTestId('remaining')).toHaveText('0,00%');

    await page.getByRole('button', { name: 'Gelijk verdelen over 2 doelen' }).click();
    await expect(first).toHaveValue('50,00');

    await page.getByRole('button', { name: 'Verdelen over activiteiten van een domein' }).click();
    await page.getByLabel('Domein').click();
    await page.getByRole('option', { name: 'Leeg' }).click();
    await expect(page.getByText('Dit domein heeft geen activiteiten')).toBeVisible();
    await page.getByLabel('Domein').click();
    await page.getByRole('option', { name: 'Inkoop' }).click();
    await page.getByRole('button', { name: 'Verdelen over activiteiten van een domein' }).last().click();
    await expect(page.getByLabel(/Percentage voor Inkoop > Offreren/)).toHaveValue('33,34');
    await expect(page.getByLabel(/Percentage voor Inkoop > Betalen/)).toHaveValue('33,33');
});
