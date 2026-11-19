import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedViewCanvas } from '../support/view';

test('Bekijken draws the Areas and Elements on the Artboard with the Direction band on top', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);

    await expect(page.locator('[data-artboard]')).toBeVisible();
    for (const area of ['Richten', 'Beheersen', 'Actoren', 'Kanalen', 'Waardeproposities', 'Primaire activiteiten', 'Ondersteunende activiteiten', 'Koers']) {
        await expect(page.getByRole('button', { name: area, exact: true })).toBeVisible();
    }
    await expect(page.locator('[data-direction="mission"]')).toContainText('Leren voor het leven');
    const domain = page.getByRole('button', { name: 'Onderwijs', exact: true });
    await expect(domain).toHaveCSS('background-color', 'rgb(246, 230, 204)');
    await expect(page.getByRole('button', { name: /^Les geven( |$)/ })).toHaveCSS('background-color', 'rgb(255, 255, 255)');
    const artboard = await page.locator('[data-artboard]').boundingBox();
    expect(artboard?.width).toBeCloseTo(1680, 0);
    await expectNoAxeViolations(page);
});
