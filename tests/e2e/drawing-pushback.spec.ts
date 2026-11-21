import { expect, test } from './support/guards';
import { openDrawing } from './support/drawing';

test('a block moved outside its parent is pushed back and the user is told which', async ({ page }) => {
    const layers = await openDrawing(page);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const x = page.getByLabel('X van Onderwijs');
    await x.fill('-60');
    await x.press('Enter');
    await expect(x).toHaveValue('0');
    await expect(page.locator('[data-announcer]')).toContainText('Teruggezet binnen de omgeving: Onderwijs');
});

test('an Area is kept off the Direction band', async ({ page }) => {
    const layers = await openDrawing(page);
    await layers.getByRole('button', { name: 'Primaire activiteiten', exact: true }).click();
    const y = page.getByLabel('Y van Primaire activiteiten');
    await y.fill('20');
    await y.press('Enter');
    await expect(y).toHaveValue('180');
    await expect(page.locator('[data-announcer]')).toContainText('Primaire activiteiten');
});

test('a child dragged below its parent makes the parent grow', async ({ page }) => {
    const layers = await openDrawing(page);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    const height = page.getByLabel('H (hoogte) van Onderwijs');
    const before = Number(await height.inputValue());
    await layers.getByRole('button', { name: 'Les geven', exact: true }).click();
    const y = page.getByLabel('Y van Les geven');
    await y.fill('600');
    await y.press('Enter');
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await expect.poll(async () => Number(await height.inputValue())).toBeGreaterThan(before + 100);
});
