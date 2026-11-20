import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedPhp } from './support/seed';

function seed(name: string): number {
    const code = `$o = App\\Models\\Organisation::factory()->create(['name' => '${name}', 'is_sample' => true]); $c = App\\Models\\Canvas::factory()->create(['organisation_id' => $o->id]); echo 'ID=' . $c->id;`;
    const output = seedPhp(code);

    return Number(/ID=(\d+)/.exec(output)?.[1]);
}

function cleanUp(name: string): void {
    const code = `$o = App\\Models\\Organisation::where('name', '${name}')->first(); App\\Models\\Canvas::where('organisation_id', $o->id)->get()->each->deleteQuietly(); $o->deleteQuietly();`;
    seedPhp(code);
}

test('a sample Organisation carries the text label Voorbeeld on its overview section and the badge in the breadcrumb', async ({ page }) => {
    const name = `Proef ${randomUUID().slice(0, 8)}`;
    const canvas = seed(name);
    try {
        await signInAs(page, 'administrator');
        await page.goto('/overview');
        const section = page.getByRole('region', { name: new RegExp(name) });
        await expect(section.getByText('Voorbeeld', { exact: true })).toBeVisible();
        await expectNoAxeViolations(page, undefined, `[aria-labelledby="${await section.getAttribute('aria-labelledby')}"]`);

        await page.goto(`/canvases/${canvas}`);
        await expect(page.getByRole('navigation', { name: 'Kruimelpad' }).getByText('Voorbeelddata')).toBeVisible();
    } finally {
        cleanUp(name);
    }
});
