import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import type { Page } from '@playwright/test';
import { expect, test } from './support/guards';
import { openDrawing, seedMoveCanvas } from './support/drawing';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

const tinker = (code: string): string => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env, maxBuffer: 64 * 1024 * 1024 }).toString();

interface Box {
    x: number;
    y: number;
    width: number;
}

interface Stored {
    area: Box;
    domain: Box;
}

const storedLayout = (canvas: number): Stored => {
    const output = tinker(`$rows = App\\Models\\Layout::where('canvas_id', ${canvas})->get(); $domain = $rows->firstWhere('element_id', App\\Models\\Element::where('canvas_id', ${canvas})->where('name', 'Onderwijs')->value('id')); $area = $rows->firstWhere('area_id', App\\Models\\Area::where('key', 'primary_activities')->value('id')); echo 'STORED=' . json_encode(['area' => $area->only(['x', 'y', 'width']), 'domain' => $domain->only(['x', 'y', 'width'])]);`);

    return JSON.parse(/STORED=(.*)/.exec(output)![1]) as Stored;
};

const measure = async (page: Page, name: string): Promise<Stored> => {
    await page.locator('[data-artboard]').waitFor();
    const artboard = (await page.locator('[data-artboard]').boundingBox())!;
    const scale = artboard.width / 1680;
    const box = async (locator: ReturnType<Page['locator']>): Promise<Box> => {
        const found = (await locator.boundingBox())!;

        return { x: (found.x - artboard.x) / scale, y: (found.y - artboard.y) / scale, width: found.width / scale };
    };
    const area = await box(page.locator('[data-block="primary_activities"]'));
    const domain = await box(page.locator('[data-block]', { has: page.getByText(name, { exact: true }) }).first());

    return { area, domain: { x: domain.x - area.x, y: domain.y - area.y, width: domain.width } };
};

const expectAlike = (actual: Stored, stored: Stored): void => {
    for (const key of ['x', 'y', 'width'] as const) {
        expect(Math.abs(actual.area[key] - stored.area[key]), `area ${key}`).toBeLessThanOrEqual(1);
        expect(Math.abs(actual.domain[key] - stored.domain[key]), `domain ${key}`).toBeLessThanOrEqual(1);
    }
};

test('Tekenen, Bekijken, a Snapshot and an imported copy draw every block at its stored place within 1 pixel', async ({ page }) => {
    const canvas = seedMoveCanvas();
    const stored = storedLayout(canvas.id);

    const layers = await openDrawing(page, canvas);
    await layers.getByRole('button', { name: 'Onderwijs', exact: true }).click();
    expect(Number(await page.getByLabel('X van Onderwijs').inputValue())).toBe(stored.domain.x);
    expect(Number(await page.getByLabel('Y van Onderwijs').inputValue())).toBe(stored.domain.y);
    expect(Number(await page.getByLabel('B (breedte) van Onderwijs').inputValue())).toBe(stored.domain.width);

    await page.goto(`/canvases/${canvas.id}/view`);
    expectAlike(await measure(page, 'Onderwijs'), stored);

    const snapshot = /SNAP=(\d+)/.exec(tinker(`$c = App\\Models\\Canvas::find(${canvas.id}); $y = App\\Models\\FiscalYear::firstOrCreate(['organisation_id' => $c->organisation_id, 'year' => 2026]); echo 'SNAP=' . app(App\\Services\\Snapshot\\Snapshots::class)->take($c, $y, 'Opmaak ${randomUUID().slice(0, 8)}')->id;`))![1];
    await page.goto(`/snapshots/${snapshot}/view`);
    expectAlike(await measure(page, 'Onderwijs'), stored);

    const copy = /COPY=(\d+)/.exec(tinker(`$c = App\\Models\\Canvas::find(${canvas.id}); $r = app(App\\Services\\Exchange\\CanvasImport::class)->commit((string) json_encode(app(App\\Services\\Exchange\\CanvasExport::class)->build($c)), null, 'Kopie ${randomUUID().slice(0, 8)}', 'Doel ${randomUUID().slice(0, 8)}'); echo 'COPY=' . $r['canvas_id'];`))![1];
    await page.goto(`/canvases/${copy}/view`);
    expectAlike(await measure(page, 'Onderwijs'), stored);
    expectAlike(await measure(page, 'Onderwijs'), storedLayout(Number(copy)));
});
