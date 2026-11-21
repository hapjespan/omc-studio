import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedPhp } from './support/seed';

function reseed(): void {
    const code = "App\\Models\\Organisation::where('is_sample', true)->exists() || (Illuminate\\Support\\Facades\\DB::table('application_state')->where('key', 'sample_data_removed')->delete() && app(App\\Services\\Operations\\SampleData::class)->seed());";
    seedPhp(code);
}

test('the sample Organisation is removed after one confirmation and the page says it will not return', async ({ page }, info) => {
    test.skip(info.project.name !== 'chromium', 'the sample Organisation is unique per database, so only one browser removes it');
    reseed();
    await signInAs(page, 'administrator');
    await page.goto('/admin');
    await page.getByRole('link', { name: 'Voorbeelddata' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Voorbeelddata' })).toBeVisible();
    await expectNoAxeViolations(page);

    await page.getByRole('button', { name: 'Voorbeelddata verwijderen' }).click();
    const dialog = page.getByRole('dialog', { name: 'Voorbeelddata verwijderen?' });
    await expect(dialog).toBeVisible();
    await expectNoAxeViolations(page);
    await dialog.getByRole('button', { name: 'Voorbeelddata verwijderen' }).click();

    await expect(page.getByText('De voorbeelddata is verwijderd en komt niet terug.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Voorbeelddata verwijderen' })).toBeHidden();
});

test('a Reader has no access to the sample data page', async ({ page }) => {
    await signInAs(page, 'reader');
    const response = await page.goto('/admin/sample-data');
    expect(response?.status()).toBe(403);
});
