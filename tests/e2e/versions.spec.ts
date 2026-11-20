import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedAllocation } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('an Administrator takes a Snapshot from the Versies tab and sees it listed', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/versions`);

    await expect(page.getByRole('link', { name: 'Versies' })).toHaveAttribute('aria-current', 'page');
    await page.getByRole('button', { name: 'Momentopname maken' }).click();
    const panel = page.getByRole('dialog', { name: 'Momentopname maken' });
    await panel.getByLabel('Naam').fill('Voor de stuurgroep');
    await panel.getByLabel('Opmerking').fill('Eerste stand');
    await panel.getByRole('button', { name: 'Opslaan' }).click();

    const row = page.getByRole('row', { name: /Voor de stuurgroep/ });
    await expect(row).toContainText('2026');
    await expect(row).toContainText('Eerste stand');
    await expectNoAxeViolations(page);
});

test('a Reader sees the Versies tab and the list but no write control', async ({ page }) => {
    const seed = seedAllocation(unique(), { Licentie: 23000 });
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/versions`);

    await expect(page.getByRole('link', { name: 'Versies' })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('button', { name: 'Momentopname maken' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: /Verwijderen/ })).toHaveCount(0);
});
