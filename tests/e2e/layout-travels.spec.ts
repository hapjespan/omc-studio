import { randomUUID } from 'node:crypto';
import type { Page } from '@playwright/test';
import { expect, test } from './support/guards';
import { openDrawing, screenOf, seedMoveCanvas } from './support/drawing';
import { ARTBOARD_WIDTH } from './support/artboard';
import { seedPhp } from './support/seed';

interface Row {
    key: string;
    name: string;
    parent: string | null;
    parentName: string | null;
    x: number;
    y: number;
    width: number;
    height: number;
}

const matched = (output: string, tag: string): string => {
    const found = new RegExp(`${tag}=(.*)`).exec(output);
    expect(found, `${tag} missing in: ${output.slice(0, 300)}`).not.toBeNull();

    return found![1];
};

const storedRows = (canvas: number | string): Row[] =>
    JSON.parse(
        matched(
            seedPhp(`$names = App\\Models\\Element::where('canvas_id', ${canvas})->pluck('name', 'id'); $areas = App\\Models\\Area::pluck('key', 'id'); $elements = App\\Models\\Element::where('canvas_id', ${canvas})->get()->keyBy('id'); echo 'ROWS=' . json_encode(App\\Models\\Layout::where('canvas_id', ${canvas})->get()->map(function ($row) use ($names, $areas, $elements) { $element = $row->element_id === null ? null : $elements[$row->element_id]; $parent = $element === null ? null : ($element->parent_id ?? $areas[$element->area_id]); return ['key' => $row->element_id ?? $areas[$row->area_id], 'name' => $element === null ? $areas[$row->area_id] : $element->name, 'parent' => $parent, 'parentName' => $parent === null ? null : ($names[$parent] ?? $parent), 'x' => $row->x, 'y' => $row->y, 'width' => $row->width, 'height' => $row->height]; })->values());`),
            'ROWS',
        ),
    ) as Row[];

const identity = (row: Row): string => `${row.name}|${row.parentName ?? ''}`;

const keyed = (rows: Row[]): Record<string, Row> => Object.fromEntries(rows.map((row) => [identity(row), row]));

const expectDrawn = async (page: Page, rows: Row[], drawnRows: Row[] = rows): Promise<void> => {
    await page.locator('[data-artboard]').waitFor();
    const artboard = (await page.locator('[data-artboard]').boundingBox())!;
    const scale = artboard.width / ARTBOARD_WIDTH;
    const box = async (key: string | null) => {
        if (key === null) return { x: artboard.x, y: artboard.y, width: artboard.width, height: artboard.height };
        const found = await page.locator(`[data-block="${key}"]`).boundingBox();
        expect(found, `no block ${key}`).not.toBeNull();

        return found!;
    };
    const byIdentity = keyed(drawnRows);

    for (const row of rows) {
        const drawn = byIdentity[identity(row)];
        expect(drawn, `no layout row for ${identity(row)}`).toBeDefined();
        const block = await box(drawn.key);
        const parent = await box(drawn.parent);

        expect(Math.abs((block.x - parent.x) / scale - row.x), `${identity(row)} x`).toBeLessThanOrEqual(1);
        expect(Math.abs((block.y - parent.y) / scale - row.y), `${identity(row)} y`).toBeLessThanOrEqual(1);
        expect(Math.abs(block.width / scale - row.width), `${identity(row)} width`).toBeLessThanOrEqual(1);
        expect(block.height / scale + 1, `${identity(row)} height`).toBeGreaterThanOrEqual(row.height);
    }
};

const fillEdges = async (page: Page, shot: Buffer, spot: { x: number; y: number; width: number; height: number }): Promise<{ left: number; top: number; right: number }> => {
    const edges = await page.evaluate(
        async ({ data, at }) => {
            const bitmap = await createImageBitmap(new Blob([Uint8Array.from(atob(data), (character) => character.charCodeAt(0))], { type: 'image/png' }));
            const canvas = document.createElement('canvas');
            canvas.width = bitmap.width;
            canvas.height = bitmap.height;
            const context = canvas.getContext('2d', { willReadFrequently: true })!;
            context.drawImage(bitmap, 0, 0);
            const isFill = (x: number, y: number): boolean => {
                const [red, green, blue] = context.getImageData(Math.round(x), Math.round(y), 1, 1).data;

                return Math.abs(red - 0xf6) + Math.abs(green - 0xe6) + Math.abs(blue - 0xcc) <= 12;
            };
            const middleY = at.y + at.height / 2;
            const middleX = at.x + at.width / 2;
            let left = Number.NaN;
            let right = Number.NaN;
            let top = Number.NaN;
            for (let x = at.x - 10; x < at.x + 10; x += 1) if (Number.isNaN(left) && isFill(x, middleY)) left = x;
            for (let x = at.x + at.width + 10; x > at.x + at.width - 10; x -= 1) if (Number.isNaN(right) && isFill(x, middleY)) right = x + 1;
            for (let y = at.y - 10; y < at.y + 10; y += 1) if (Number.isNaN(top) && isFill(middleX, y)) top = y;

            return { left, right, top };
        },
        { data: shot.toString('base64'), at: spot },
    );

    return edges;
};

test('Tekenen, Bekijken, a Snapshot, a copy and an import draw every block at its stored place within 1 pixel', async ({ page, browserName }) => {
    test.setTimeout(180_000);
    const canvas = seedMoveCanvas();
    const rows = storedRows(canvas.id);
    expect(rows.length).toBeGreaterThan(5);
    const onderwijs = rows.find((row) => row.name === 'Onderwijs')!;
    const area = rows.find((row) => row.name === onderwijs.parent)!;

    await openDrawing(page, canvas);
    const origin = await screenOf(page, { x: area.x + onderwijs.x, y: area.y + onderwijs.y });
    const { zoom } = JSON.parse((await page.locator('[data-view]').getAttribute('data-view')) ?? '{}') as { zoom: number };
    const width = onderwijs.width * zoom;
    if (browserName === 'chromium') {
        const ratio = await page.evaluate(() => window.devicePixelRatio);
        const spot = { x: origin.x * ratio, y: origin.y * ratio, width: width * ratio, height: onderwijs.height * zoom * ratio };
        let edges = { left: Number.NaN, top: Number.NaN, right: Number.NaN };
        for (let attempt = 0; attempt < 10 && Number.isNaN(edges.left); attempt += 1) {
            await page.waitForTimeout(500);
            edges = await fillEdges(page, await page.screenshot(), spot);
        }
        expect(Math.abs(edges.left - spot.x) / ratio, 'Tekenen left edge').toBeLessThanOrEqual(1);
        expect(Math.abs(edges.top - spot.y) / ratio, 'Tekenen top edge').toBeLessThanOrEqual(1);
        expect(Math.abs(edges.right - (spot.x + spot.width)) / ratio, 'Tekenen right edge').toBeLessThanOrEqual(1);
    } else {
        test.info().annotations.push({ type: 'not measured here', description: 'a WebKit screenshot injects an inline style that the CSP guard refuses and the Tekenen drawing is not captured on Firefox (known defect of the Tekenen suite); the picture of Tekenen against Bekijken is covered by drawing-equal and h11-picture' });
    }

    await page.goto(`/canvases/${canvas.id}/view`);
    await expectDrawn(page, rows);

    const snapshot = matched(seedPhp(`$c = App\\Models\\Canvas::find(${canvas.id}); $y = App\\Models\\FiscalYear::firstOrCreate(['organisation_id' => $c->organisation_id, 'year' => 2026]); echo 'SNAP=' . app(App\\Services\\Snapshot\\Snapshots::class)->take($c, $y, 'Opmaak ${randomUUID().slice(0, 8)}')->id;`), 'SNAP');
    seedPhp(`$id = App\\Models\\Element::where('canvas_id', ${canvas.id})->where('name', 'Onderwijs')->value('id'); App\\Models\\Layout::where('element_id', $id)->increment('x', 30); App\\Models\\Canvas::whereKey(${canvas.id})->increment('layout_version');`);
    const moved = storedRows(canvas.id);
    expect(moved.find((row) => row.name === 'Onderwijs')!.x).toBe(onderwijs.x + 30);

    await page.goto(`/snapshots/${snapshot}/view`);
    await expectDrawn(page, rows);

    await page.goto(`/canvases/${canvas.id}/view`);
    await expectDrawn(page, moved);

    const copied = matched(seedPhp(`$c = App\\Models\\Canvas::find(${canvas.id}); echo 'COPY=' . app(App\\Services\\Canvas\\CanvasLifecycle::class)->copy($c, $c->organisation, 'Kopie ${randomUUID().slice(0, 8)}')->id;`), 'COPY');
    const copiedRows = storedRows(copied);
    expect(copiedRows).toHaveLength(moved.length);
    await page.goto(`/canvases/${copied}/view`);
    await expectDrawn(page, moved, copiedRows);

    const imported = matched(seedPhp(`$c = App\\Models\\Canvas::find(${canvas.id}); $r = app(App\\Services\\Exchange\\CanvasImport::class)->commit((string) json_encode(app(App\\Services\\Exchange\\CanvasExport::class)->build($c)), null, 'Import ${randomUUID().slice(0, 8)}', 'Doel ${randomUUID().slice(0, 8)}'); echo 'IMPORT=' . $r['canvas_id'];`), 'IMPORT');
    const importedRows = storedRows(imported);
    expect(importedRows).toHaveLength(moved.length);
    await page.goto(`/canvases/${imported}/view`);
    await expectDrawn(page, moved, importedRows);

    const plain = matched(seedPhp(`$c = App\\Models\\Canvas::find(${canvas.id}); $file = app(App\\Services\\Exchange\\CanvasExport::class)->build($c); $file['canvas']['layout'] = null; $r = app(App\\Services\\Exchange\\CanvasImport::class)->commit((string) json_encode($file), null, 'Zonder ${randomUUID().slice(0, 8)}', 'Zonder doel ${randomUUID().slice(0, 8)}'); echo 'PLAIN=' . $r['canvas_id'] . ',' . App\\Models\\Layout::where('canvas_id', $r['canvas_id'])->count();`), 'PLAIN');
    const [plainCanvas, plainRows] = plain.split(',');
    expect(plainRows).toBe('0');
    await page.goto(`/canvases/${plainCanvas}/view`);
    await page.locator('[data-artboard]').waitFor();
    await expect(page.locator('[data-block="primary_activities"]')).toBeVisible();
});

test('saving a moved Area, Domain, Cluster and Activity keeps their snapped positions after a mode switch and reload', async ({ page }) => {
    const canvas = seedMoveCanvas();
    const layers = await openDrawing(page, canvas);
    const activity = storedRows(canvas.id).find((row) => row.parentName === 'Lessen' && row.name === 'Les geven')!;
    const edits = [
        ['Primaire activiteiten', 773, 770],
        ['Onderwijs', 33, 30],
        ['Lessen', 17, 20],
        ['Les geven', 13, 10],
    ] as const;
    for (const [name, x] of edits) {
        const layer = name === 'Les geven' ? layers.locator(`[data-layer="${activity.key}"]`) : layers.getByRole('button', { name, exact: true });
        await layer.click();
        if (name === 'Primaire activiteiten') await page.getByRole('checkbox', { name: 'Raster' }).check();
        const field = page.getByLabel(`X van ${name}`);
        await field.fill(String(x));
        await field.press('Enter');
    }
    const width = page.getByLabel('B (breedte) van Les geven');
    await width.fill('217');
    await width.press('Enter');
    await page.getByRole('group', { name: 'Modus' }).getByRole('link', { name: 'Bekijken' }).click();
    await page.locator('[data-artboard]').waitFor();
    const rows = storedRows(canvas.id);
    for (const [name, , snapped] of edits) {
        const stored = rows.find((row) => name === 'Les geven' ? row.key === activity.key : row.name === (name === 'Primaire activiteiten' ? 'primary_activities' : name));
        expect(stored?.x).toBe(snapped);
    }
    expect(rows.find(({ key }) => key === activity.key)?.width).toBe(220);
    await expectDrawn(page, rows);
    await page.reload();
    await expectDrawn(page, rows);
});
