import { expect, installGuards, test } from './support/guards';
import { openDrawing } from './support/drawing';
import { seedViewCanvas } from './support/view';

test('a save after another Administrator saved is refused and the stored Layout is loaded', async ({ browser, baseURL }) => {
    const canvas = seedViewCanvas();
    const first = await browser.newContext();
    const second = await browser.newContext();
    const one = await first.newPage();
    const two = await second.newPage();
    const guards = [await installGuards(one, new URL(baseURL as string).origin), await installGuards(two, new URL(baseURL as string).origin)];
    const layersOne = await openDrawing(one, canvas);
    const layersTwo = await openDrawing(two, canvas);

    await layersOne.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await one.getByLabel('X van Onderwijs').fill('60');
    await one.getByLabel('X van Onderwijs').press('Enter');
    await expect(one.locator('[data-save-state]')).toHaveText('Opmaak opgeslagen');

    await layersTwo.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await two.getByLabel('X van Onderwijs').fill('90');
    await two.getByLabel('X van Onderwijs').press('Enter');
    await expect(two.locator('[data-announcer]')).toContainText('Iemand anders heeft de opmaak gewijzigd. De tekening is opnieuw geladen');
    await expect(two.locator('[data-announcer]')).not.toContainText('verplaatsing');
    await layersTwo.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await expect(two.getByLabel('X van Onderwijs')).toHaveValue('60');

    for (const guard of guards) {
        guard.assertNoOutsideRequest();
        await guard.assertNoCspViolation();
    }

    await first.close();
    await second.close();
});
