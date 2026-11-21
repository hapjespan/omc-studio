import { randomUUID } from 'node:crypto';
import { expect, test } from './support/guards';
import { seedAllocation } from './support/finance';
import { signInAs } from './support/shell';

test('the disabled Opslaan and Set leegmaken buttons of an unallocated Cost record say why', async ({ page }) => {
    const seed = seedAllocation(`Hints ${randomUUID().slice(0, 8)}`, { Licentie: 100_000 });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${seed.canvas}/allocation?year=2026&cost_record=${seed.records.Licentie}`);

    const save = page.getByRole('button', { name: 'Opslaan', exact: true });
    const clear = page.getByRole('button', { name: 'Set leegmaken' });
    await expect(save).toBeDisabled();
    await expect(clear).toBeDisabled();
    await expect(page.getByText('Er zijn geen wijzigingen om op te slaan')).toBeVisible();
    await expect(page.getByText('Er is geen verdeling om leeg te maken')).toBeVisible();
    await expect(save).toHaveAccessibleDescription('Er zijn geen wijzigingen om op te slaan');
    await expect(clear).toHaveAccessibleDescription('Er is geen verdeling om leeg te maken');
});
