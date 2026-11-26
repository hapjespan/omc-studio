import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedAllocation, expandTarget } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('the tree offers Targets with an add button, marks the ones in the set and filters by search', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/allocation?year=2026&cost_record=${seed.records.Licentie}`);
    await expandTarget(page, 'Inkoop');

    const tree = page.getByRole('region', { name: 'Doelen op het canvas' });
    await expect(tree.getByText('Inkoop').first()).toBeVisible();
    await page.getByRole('button', { name: 'Toevoegen aan verdeling: Offreren' }).click();
    await expect(tree.getByText('In set')).toHaveCount(1);
    await expect(page.getByLabel(/Percentage voor Inkoop > Offreren/)).toHaveValue('100,00');

    await page.getByLabel('Doelen zoeken').fill('betalen');
    await expect(tree.getByText('Offreren')).toHaveCount(0);
    await expect(tree.getByText('Betalen')).toBeVisible();
    await expectNoAxeViolations(page);
});

test('dragging a Target into the set is an addition, with the button as the single-pointer alternative', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/allocation?year=2026&cost_record=${seed.records.Licentie}`);
    await expandTarget(page, 'Inkoop');

    await page.getByRole('region', { name: 'Doelen op het canvas' }).getByText('Bestellen').dragTo(page.getByLabel('Verdeling, hier neerzetten om toe te voegen'));
    await expect(page.getByLabel(/Percentage voor Inkoop > Bestellen/)).toBeVisible();
});
