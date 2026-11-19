import { expect, expectNoAxeViolations, test } from '../support/guards';
import { seedOrganisation, tinker, unique } from '../support/overview';
import { signInAs } from '../support/shell';

const seedTemplate = (name: string): void => {
    tinker(`App\\Models\\Canvas::factory()->template()->create(['name' => '${name}']);`);
};

test('Sjablonen shows every Template except Leeg canvas as a card that opens Invoeren', async ({ page }) => {
    const template = unique('Basis');
    tinker("app(App\\Services\\Operations\\InstallSteps::class)->run();");
    seedTemplate(template);
    seedOrganisation(unique('Noord'));
    await signInAs(page, 'administrator');
    await page.goto('/overview');

    await expect(page.getByRole('heading', { level: 2, name: 'Sjablonen' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Leeg canvas', exact: true })).toHaveCount(0);
    await expectNoAxeViolations(page, 'svg[aria-hidden="true"]');

    await page.getByRole('link', { name: template, exact: true }).click();
    await page.waitForURL(/\/canvases\/\d+\/entry/);
    await expect(page.getByRole('link', { name: 'Bekijken' })).toHaveCount(0);
});

test('a Reader does not see Sjablonen', async ({ page }) => {
    seedTemplate(unique('Basis'));
    await signInAs(page, 'reader');
    await page.goto('/overview');

    await expect(page.getByRole('heading', { level: 2, name: 'Sjablonen' })).toHaveCount(0);
});
