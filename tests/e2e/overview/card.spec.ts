import { expect, expectNoAxeViolations, test } from '../support/guards';
import { seedOrganisation, unique } from '../support/overview';
import { signInAs } from '../support/shell';

test('a card is one link named after the Canvas with its actions in a menu button', async ({ page }) => {
    const canvas = unique('Model');
    seedOrganisation(unique('Noord'), [canvas]);
    await signInAs(page, 'administrator');
    await page.goto('/overview');

    const link = page.getByRole('link', { name: canvas, exact: true });
    await expect(link).toHaveCount(1);
    await expect(link.locator('svg')).toHaveAttribute('aria-hidden', 'true');
    const trigger = page.getByRole('button', { name: `Acties voor ${canvas}` });
    await expect(trigger).toBeVisible();
    await expectNoAxeViolations(page);
});

for (const key of ['Enter', 'Space']) {
    test(`the menu opens with ${key} and closes with Escape returning focus`, async ({ page }) => {
        const canvas = unique('Model');
        seedOrganisation(unique('Noord'), [canvas]);
        await signInAs(page, 'administrator');
        await page.goto('/overview');

        const trigger = page.getByRole('button', { name: `Acties voor ${canvas}` });
        await trigger.focus();
        await page.keyboard.press(key);
        await expect(page.getByRole('menuitem', { name: 'Kopiëren' })).toBeVisible();
        await expect(page.getByRole('menuitem', { name: 'Naam wijzigen' })).toBeVisible();
        await expect(page.getByRole('menuitem', { name: 'Archiveren' })).toBeVisible();
        await expect(page.getByRole('menuitem', { name: 'Verwijderen' })).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(page.getByRole('menuitem', { name: 'Kopiëren' })).toHaveCount(0);
        await expect(trigger).toBeFocused();
    });
}

test('an archived card offers Terugzetten instead of Archiveren', async ({ page }) => {
    const canvas = unique('Oud');
    seedOrganisation(unique('Noord'), [], { archivedCanvases: [canvas] });
    await signInAs(page, 'administrator');
    await page.goto('/overview?archived=1');

    await page.getByRole('button', { name: `Acties voor ${canvas}` }).click();
    await expect(page.getByRole('menuitem', { name: 'Terugzetten' })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Archiveren' })).toHaveCount(0);
});
