import { arch, cpus, platform, release, totalmem } from 'node:os';
import { readFileSync } from 'node:fs';
import { expect, test } from './support/guards';
import { seedPerformanceCanvas } from './support/overlay';
import { signInAs } from './support/shell';
import { expectWithinBudget, PERFORMANCE_TAG, sample } from './support/timing';
import { seedPhp } from './support/seed';

function seedSnapshotOnDataset(): { canvas: number; snapshot: number } {
    const canvas = seedPerformanceCanvas();
    const output = seedPhp(`auth()->login(App\\Models\\User::factory()->create(['role' => 'administrator'])); $c = App\\Models\\Canvas::find(${canvas}); $f = App\\Models\\FiscalYear::where('organisation_id', $c->organisation_id)->where('year', 2026)->first(); $s = app(App\\Services\\Snapshot\\Snapshots::class)->take($c, $f, 'Meting'); echo 'ID=' . $s->id;`);

    return { canvas, snapshot: Number(/ID=(\d+)/.exec(output)?.[1]) };
}

const machine = (browser: string): string => `${platform()} ${release()} ${arch()}, ${cpus().length} x ${cpus()[0]?.model ?? 'cpu'}, ${Math.round(totalmem() / 2 ** 30)} GiB, ${browser}`;

test('Versies opens within one second on 500 Elements and 1000 Cost records', { tag: PERFORMANCE_TAG }, async ({ page, browserName }) => {
    test.setTimeout(480_000);
    const seed = seedSnapshotOnDataset();
    await signInAs(page, 'reader');

    const samples = await sample(async () => {
        await page.goto('about:blank');
        await page.goto(`/canvases/${seed.canvas}/versions`, { waitUntil: 'commit' });
        await page.getByRole('link', { name: 'Meting' }).waitFor({ state: 'visible', timeout: 10000 });

        return page.evaluate(() => performance.now());
    });

    expectWithinBudget(samples, 'Versies, 500 Elements and 1000 Cost records', browserName);
});

test('the Snapshot view shows 500 Elements within one second', { tag: PERFORMANCE_TAG }, async ({ page, browserName }) => {
    test.setTimeout(480_000);
    const seed = seedSnapshotOnDataset();
    await signInAs(page, 'reader');

    const samples = await sample(async () => {
        await page.goto('about:blank');
        await page.goto(`/snapshots/${seed.snapshot}/view`, { waitUntil: 'commit' });
        await page.locator('[data-artboard]').waitFor({ state: 'visible', timeout: 10000 });
        await page.locator('[data-block]').nth(499).waitFor({ state: 'attached' });

        return page.evaluate(() => performance.now());
    });

    expectWithinBudget(samples, 'Snapshot view, 500 Elements and 1000 Cost records', browserName);
});

test('the Canvas JSON export answers within one second', { tag: PERFORMANCE_TAG }, async ({ page, browserName }) => {
    test.setTimeout(480_000);
    const seed = seedSnapshotOnDataset();
    await signInAs(page, 'administrator');

    const samples = await sample(async () => {
        const started = Date.now();
        const response = await page.request.get(`/canvases/${seed.canvas}/export/json`);
        expect(response.ok()).toBe(true);
        await response.body();

        return Date.now() - started;
    });

    expectWithinBudget(samples, 'Canvas JSON export, 500 Elements and 1000 Cost records', browserName);
});

test('PNG export and print are measured once and recorded for the product owner (P-6)', { tag: PERFORMANCE_TAG }, async ({ page, browserName }) => {
    test.setTimeout(240_000);
    const seed = seedSnapshotOnDataset();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${seed.canvas}/view?overlay=financial&year=2026`);
    await page.locator('[data-block]').nth(499).waitFor({ state: 'attached' });

    const started = Date.now();
    await page.getByRole('button', { name: 'Exporteren' }).click();
    const download = page.waitForEvent('download');
    await page.getByRole('menuitem', { name: 'Exporteren als PNG' }).click();
    const file = await download;
    const bytes = readFileSync((await file.path()) as string);
    const png = Date.now() - started;
    expect(bytes.readUInt32BE(16)).toBeGreaterThan(0);

    let print = 'not measured (only Chromium produces a print file)';
    if (browserName === 'chromium') {
        const printStarted = Date.now();
        await page.emulateMedia({ media: 'print' });
        await page.pdf({ format: 'A3', landscape: true, printBackground: true });
        print = `${Date.now() - printStarted} ms`;
    }

    console.log(`P-6 on ${machine(browserName)}; 500 Elements and 1000 Cost records: PNG ${png} ms (${Math.round(bytes.length / 1024)} KiB), print ${print}`);
});
