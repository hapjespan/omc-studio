import { expect, expectNoAxeViolations, test } from '../support/guards';
import { seedOrganisation, unique } from '../support/overview';
import { signInAs } from '../support/shell';

test('each Organisation is a section with its active Canvas cards and archived ones appear on request', async ({ page }) => {
    const first = unique('Noord');
    const second = unique('Zuid');
    const active = unique('Model');
    const archived = unique('Oud');
    seedOrganisation(first, [active], { archivedCanvases: [archived] });
    seedOrganisation(second);
    await signInAs(page, 'administrator');
    await page.goto('/overview');

    await expect(page.getByRole('heading', { level: 1, name: 'Overzicht' })).toBeVisible();
    await expect(page).toHaveTitle(/Overzicht/);
    await expect(page.getByRole('heading', { level: 2, name: first })).toBeVisible();
    await expect(page.getByRole('heading', { level: 2, name: second })).toBeVisible();
    await expect(page.getByRole('link', { name: active, exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: archived, exact: true })).toHaveCount(0);
    await expect(page.getByText(/Laatst gewijzigd \d{2}-\d{2}-\d{4}|Laatst gewijzigd/).first()).toBeVisible();
    await expectNoAxeViolations(page, 'svg[aria-hidden="true"]');
});

test('Toon gearchiveerd adds the archived cards', async ({ page }) => {
    const archived = unique('Oud');
    seedOrganisation(unique('Noord'), [unique('Model')], { archivedCanvases: [archived] });
    await signInAs(page, 'administrator');
    await page.goto('/overview');

    await page.getByLabel('Toon gearchiveerd').check();
    await expect(page.getByRole('link', { name: archived, exact: true })).toBeVisible();
});

test('an archived Organisation is hidden and the root shows the same page', async ({ page }) => {
    const hidden = unique('Verborgen');
    const shown = unique('Zichtbaar');
    seedOrganisation(hidden);
    seedOrganisation(shown);
    const { seedPhp } = await import('../support/seed');
    seedPhp(`App\\Models\\Organisation::where('name', '${hidden}')->update(['archived_at' => now()]);`);
    await signInAs(page, 'reader');
    await page.goto('/');

    await expect(page.getByRole('heading', { level: 2, name: shown })).toBeVisible();
    await expect(page.getByRole('heading', { level: 2, name: hidden })).toHaveCount(0);
});
