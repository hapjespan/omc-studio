import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

function seed(name: string): number {
    const code = `$o = App\\Models\\Organisation::factory()->create(['name' => '${name}', 'is_sample' => true]); $c = App\\Models\\Canvas::factory()->create(['organisation_id' => $o->id]); echo 'ID=' . $c->id;`;
    const output = execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env }).toString();

    return Number(/ID=(\d+)/.exec(output)?.[1]);
}

function cleanUp(name: string): void {
    const code = `$o = App\\Models\\Organisation::where('name', '${name}')->first(); App\\Models\\Canvas::where('organisation_id', $o->id)->each->deleteQuietly(); $o->deleteQuietly();`;
    execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env });
}

test('a sample Organisation carries the text label Voorbeeld on its overview section and the badge in the breadcrumb', async ({ page }) => {
    const name = `Proef ${randomUUID().slice(0, 8)}`;
    const canvas = seed(name);
    try {
        await signInAs(page, 'administrator');
        await page.goto('/overview');
        await expect(page.getByRole('region', { name: new RegExp(name) }).getByText('Voorbeeld', { exact: true })).toBeVisible();
        await expectNoAxeViolations(page);

        await page.goto(`/canvases/${canvas}`);
        await expect(page.getByRole('navigation', { name: 'Kruimelpad' }).getByText('Voorbeelddata')).toBeVisible();
    } finally {
        cleanUp(name);
    }
});
