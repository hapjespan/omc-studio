import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

test('PNG export makes no outside request and no CSP violation, with a neutral file name', async ({ page, guards }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.getByRole('button', { name: 'Exporteren' }).click();
    const download = page.waitForEvent('download');
    await page.getByRole('menuitem', { name: 'Exporteren als PNG' }).click();
    const name = (await download).suggestedFilename().toLowerCase();
    expect(name).not.toMatch(/indicia|laravel|inertia/);
    await expect(page.getByTestId('export-note')).toHaveText('PNG is gemaakt');
    guards.assertNoOutsideRequest();
    await guards.assertNoCspViolation();
});
