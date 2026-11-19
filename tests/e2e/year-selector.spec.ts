import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { seedCostRecord, seedOrganisation, tinker } from './support/finance';
import { signInAs } from './support/shell';

const unique = (): string => `Org ${randomUUID().slice(0, 8)}`;

test('one labelled year select switches the table to another year', async ({ page }) => {
    const organisation = seedOrganisation(unique(), [2025, 2026]);
    seedCostRecord(organisation, 'Licentie', { budgetCents: 100000, year: 2025 });
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${organisation}/cost-records?year=2026`);
    await expect(page.getByRole('row', { name: /Licentie/ })).toContainText('geen jaarwaarden');

    await page.getByLabel('Boekjaar', { exact: true }).selectOption('2025');
    await expect(page).toHaveURL(/year=2025/);
    await expect(page.getByRole('row', { name: /Licentie/ })).toContainText('€ 1.000,00');
    await expectNoAxeViolations(page);
});

test('the Kostenposten tab is shown to an Administrator with a Canvas and not to a Reader', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    const output = tinker(`$c = App\\Models\\Canvas::factory()->create(['organisation_id' => ${organisation}]); echo 'ID=' . $c->id;`);
    const canvas = Number(/ID=(\d+)/.exec(output)?.[1]);

    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas}/view`);
    await page.getByRole('navigation', { name: 'Hoofdnavigatie' }).getByRole('link', { name: 'Kostenposten' }).click();
    await expect(page).toHaveURL(new RegExp(`/canvases/${canvas}/cost-records`));
});

test('the Toewijzen tab opens the allocation page of the Canvas', async ({ page }) => {
    const organisation = seedOrganisation(unique());
    const canvas = Number(/ID=(\d+)/.exec(tinker(`$c = App\\Models\\Canvas::factory()->create(['organisation_id' => ${organisation}]); echo 'ID=' . $c->id;`))?.[1]);

    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas}/view`);
    await page.getByRole('navigation', { name: 'Hoofdnavigatie' }).getByRole('link', { name: 'Toewijzen' }).click();
    await expect(page).toHaveURL(new RegExp(`/canvases/${canvas}/allocation`));
    await expect(page.getByRole('navigation', { name: 'Hoofdnavigatie' }).getByRole('link', { name: 'Toewijzen' })).toHaveAttribute('aria-current', 'page');
});

test('a Reader sees no Kostenposten tab', async ({ page }) => {
    await signInAs(page, 'reader');
    await expect(page.getByRole('link', { name: 'Kostenposten' })).toHaveCount(0);
});
