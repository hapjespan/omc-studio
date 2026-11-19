import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedOrganisation } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('an Administrator reaches Kostenposten from an Organisation without Canvases', async ({ page }) => {
    const name = unique();
    const organisation = seedOrganisation(name);
    await signInAs(page, 'administrator');
    await page.goto('/overview');

    const section = page.getByRole('region', { name });
    await expect(section.getByText('Deze organisatie heeft nog geen canvassen')).toBeVisible();
    await expectNoAxeViolations(page);
    await section.getByRole('link', { name: 'Kostenposten' }).click();
    await expect(page).toHaveURL(new RegExp(`/organisations/${organisation}/cost-records`));
});

test('a Reader sees no Kostenposten link', async ({ page }) => {
    const name = unique();
    seedOrganisation(name);
    await signInAs(page, 'reader');
    await page.goto('/overview');

    await expect(page.getByRole('region', { name })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kostenposten' })).toHaveCount(0);
});
