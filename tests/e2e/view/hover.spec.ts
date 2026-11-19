import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedViewCanvas } from '../support/view';

test('hover and focus content shows the full name, stays under the pointer and closes with Escape', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);

    const activity = page.getByRole('button', { name: /^Les geven( |$)/ });
    await activity.hover();
    const content = page.locator('[data-hover-content]');
    await expect(content).toContainText('Les geven');
    await expect(activity).toHaveAttribute('aria-describedby', 'view-hover');
    await content.hover();
    await page.waitForTimeout(400);
    await expect(content).toBeVisible();
    await expectNoAxeViolations(page);
    await page.keyboard.press('Escape');
    await expect(content).toHaveCount(0);

    await page.mouse.move(0, 0);
    await page.getByRole('button', { name: /^Plannen( |$)/ }).focus();
    await expect(content).toContainText('Plannen');
    await page.keyboard.press('Escape');
    await expect(content).toHaveCount(0);
});

test('the Unknown allocation card is last in Ondersteunende activiteiten with the Systeem mark', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);

    const cards = page.locator('[data-area="supporting_activities"][data-kind="domain"]');
    await expect(cards.last().locator('[data-system-mark]')).toHaveText('Systeem');
    await expect(page.locator('[data-system-mark]')).toHaveCount(1);
});
