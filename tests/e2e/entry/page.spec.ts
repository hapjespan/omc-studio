import { expect, expectNoAxeViolations, test } from '../support/guards';
import { seedOrganisation, tinker, unique } from '../support/overview';
import { signInAs } from '../support/shell';

const canvasId = (name: string): number => Number(/ID=(\d+)/.exec(tinker(`echo 'ID=' . App\\Models\\Canvas::where('name', '${name}')->firstOrFail()->id;`))?.[1]);

test('Invoeren lists the eight Areas with count lines and chooses the first activity Area', async ({ page }) => {
    const name = unique('Model');
    tinker("app(App\\Services\\Operations\\InstallSteps::class)->run();");
    seedOrganisation(unique('Noord'), [name]);
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvasId(name)}/entry`);

    await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
    const panel = page.getByRole('navigation', { name: 'Areas' });
    await expect(panel.getByRole('button')).toHaveCount(8);
    await expect(panel.getByRole('button', { name: /Richten/ })).toContainText('0 domeinen · 0 activiteiten');
    await expect(panel.getByRole('button', { name: /Richten/ })).toHaveAttribute('aria-current', 'true');
    await expect(panel.getByRole('button', { name: /Actoren/ })).toContainText('0 groepen');
    await expect(panel.getByRole('button', { name: /Koers/ })).toContainText('0 teksten');
    await expectNoAxeViolations(page);
});

test('a Reader and an archived Canvas are sent to Bekijken', async ({ page }) => {
    const live = unique('Model');
    const archived = unique('Oud');
    seedOrganisation(unique('Noord'), [live], { archivedCanvases: [archived] });

    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvasId(live)}/entry`);
    await expect(page).toHaveURL(/\/canvases\/\d+\/view/);

    await page.context().clearCookies();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvasId(archived)}/entry`);
    await expect(page).toHaveURL(/\/canvases\/\d+\/view/);
});
