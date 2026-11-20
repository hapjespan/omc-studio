import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

test('every mode has its own page title and the sign-in page has one', async ({ page }) => {
    await page.goto('/sign-in');
    await expect(page).toHaveTitle(/Inloggen/);

    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    const titles: string[] = [];
    for (const [mode, label] of [['entry', 'Invoeren'], ['drawing', 'Tekenen'], ['view', 'Bekijken']]) {
        await page.goto(`/canvases/${canvas.id}/${mode}`);
        await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
        await expect(page).toHaveTitle(new RegExp(`${canvas.name} - ${label}`));
        titles.push(await page.title());
    }
    expect(new Set(titles).size).toBe(3);
});
