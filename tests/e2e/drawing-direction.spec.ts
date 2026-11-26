import { expect, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';
import { seedPhp } from './support/seed';

function longMission(canvas: number): void {
    const body = Array.from({ length: 60 }, (_, index) => `woord${index}`).join(' ');
    seedPhp(`App\\Models\\Statement::where('canvas_id', ${canvas})->where('kind', 'mission')->update(['body' => '${body}']);`);
}

test('a long mission is cut with an ellipsis, warned about and shown in full on focus, hover and in the panel', async ({ page }) => {
    const canvas = seedViewCanvas();
    longMission(canvas.id);
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });

    const mission = page.locator('[data-direction="mission"]');
    await expect(mission).toHaveAttribute('data-truncated', 'true');
    await expect(mission).toContainText('…');
    await expect(page.getByText('Koerstekst past niet in de koersbalk')).toBeVisible();
    await expect(mission.locator('[data-direction-full]')).toBeHidden();

    await mission.focus();
    await expect(mission.locator('[data-direction-full]')).toContainText('woord59');
    await expect(page.locator('[data-detail]')).toContainText('woord59');
    await mission.evaluate((node) => node.blur());
    await page.locator('[data-hint]').hover();
    await expect(mission.locator('[data-direction-full]')).toBeHidden();
    await mission.hover();
    await expect(mission.locator('[data-direction-full]')).toContainText('woord59');
});

test('a mission that fits raises no warning', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await expect(page.getByText('Koerstekst past niet in de koersbalk')).toHaveCount(0);
});
