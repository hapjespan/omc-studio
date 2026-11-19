import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

for (const role of ['reader', 'administrator'] as const) {
    test(`a ${role} downloads the canvas as JSON from the Exporteren menu`, async ({ page }) => {
        const canvas = seedViewCanvas();
        await signInAs(page, role);
        await page.goto(`/canvases/${canvas.id}/view`);

        const trigger = page.getByRole('button', { name: 'Exporteren' });
        await trigger.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('menuitem', { name: 'Exporteren als JSON' })).toBeFocused();
        await expectNoAxeViolations(page);

        await page.keyboard.press('Escape');
        await expect(page.getByRole('menu')).toBeHidden();
        await expect(trigger).toBeFocused();

        await trigger.click();
        const download = page.waitForEvent('download');
        await page.getByRole('menuitem', { name: 'Exporteren als JSON' }).click();
        expect((await download).suggestedFilename()).toMatch(/\.json$/);
    });
}
