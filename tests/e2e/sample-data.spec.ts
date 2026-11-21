import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { expectPanel, expectTitle, height, style } from './support/measure';
import { signInAs } from './support/shell';
import { seedPhp } from './support/seed';

function reseed(): void {
    const code = "App\\Models\\Organisation::where('is_sample', true)->exists() || (Illuminate\\Support\\Facades\\DB::table('application_state')->where('key', 'sample_data_removed')->delete() && app(App\\Services\\Operations\\SampleData::class)->seed());";
    seedPhp(code);
}

function seedSample(name: string): number {
    const code = `$o = App\\Models\\Organisation::factory()->create(['name' => '${name}', 'is_sample' => true]); $c = App\\Models\\Canvas::factory()->create(['organisation_id' => $o->id]); echo 'ID=' . $c->id;`;
    const output = seedPhp(code);

    return Number(/ID=(\d+)/.exec(output)?.[1]);
}

function cleanUpSample(name: string): void {
    const code = `$o = App\\Models\\Organisation::where('name', '${name}')->first(); App\\Models\\Canvas::where('organisation_id', $o->id)->get()->each->deleteQuietly(); $o->deleteQuietly();`;
    seedPhp(code);
}

test.describe('the sample Organisation is unique per database, so every spec that creates or removes it runs in this one file, one after the other', () => {
    test.describe.configure({ mode: 'serial' });

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

    test('a sample Organisation carries the text label Voorbeeld on its overview section and the badge in the breadcrumb', async ({ page }) => {
        const name = `Proef ${randomUUID().slice(0, 8)}`;
        const canvas = seedSample(name);
        try {
            await signInAs(page, 'administrator');
            await page.goto('/overview');
            const section = page.getByRole('region', { name: new RegExp(name) });
            await expect(section.getByText('Voorbeeld', { exact: true })).toBeVisible();
            await expectNoAxeViolations(page, undefined, `[aria-labelledby="${await section.getAttribute('aria-labelledby')}"]`);

            await page.goto(`/canvases/${canvas}`);
            await expect(page.getByRole('navigation', { name: 'Kruimelpad' }).getByText('Voorbeelddata')).toBeVisible();
        } finally {
            cleanUpSample(name);
        }
    });

    test('Voorbeelddata: title, white panel and a danger button with the contract outline', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        seedPhp("App\\Models\\Organisation::where('is_sample', true)->exists() || (Illuminate\\Support\\Facades\\DB::table('application_state')->where('key', 'sample_data_removed')->delete() && app(App\\Services\\Operations\\SampleData::class)->seed());");
        await signInAs(page, 'administrator');
        await page.goto('/admin/sample-data');

        await expectTitle(page.getByRole('heading', { level: 1, name: 'Voorbeelddata' }));
        const button = page.getByRole('button', { name: 'Voorbeelddata verwijderen' });
        await expectPanel(button.locator('..'));
        expect(await style(button.locator('..'), 'padding-top')).toBe('24px');
        expect(await height(button)).toBe(36);
        expect(await style(button, 'color')).toBe('rgb(161, 42, 30)');
        expect(await style(button, 'border-top-color')).toBe('rgb(192, 100, 90)');
    });
});

test('a Reader has no access to the sample data page', async ({ page }) => {
    await signInAs(page, 'reader');
    const response = await page.goto('/admin/sample-data');
    expect(response?.status()).toBe(403);
});
