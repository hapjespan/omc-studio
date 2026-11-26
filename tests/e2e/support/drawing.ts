import type { Page } from '@playwright/test';
import { signInAs } from './shell';
import { seedViewCanvas, type ViewCanvas } from './view';
import { seedPhp } from './seed';

export function seedRow(): ViewCanvas {
    const canvas = seedViewCanvas();
    const code = `$c = App\\Models\\Canvas::find(${canvas.id}); $a = App\\Models\\Area::where('key', 'primary_activities')->value('id'); $d = App\\Models\\Element::where('canvas_id', ${canvas.id})->where('name', 'Onderwijs')->first(); foreach (['Alfa', 'Beta', 'Gamma'] as $i => $n) { App\\Models\\Element::create(['canvas_id' => ${canvas.id}, 'area_id' => $a, 'kind' => 'activity', 'name' => $n, 'parent_id' => $d->id, 'position' => 10 + $i]); }`;
    seedPhp(code);
    return canvas;
}

export async function openDrawing(page: Page, canvas: ViewCanvas = seedViewCanvas()) {
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.waitForTimeout(500);
    return page.getByRole('navigation', { name: 'Lagen' });
}

export interface Spot {
    x: number;
    y: number;
}

export function seedMoveCanvas(): ViewCanvas {
    const canvas = seedViewCanvas();
    const code = `$a = fn ($k) => App\\Models\\Area::where('key', $k)->value('id'); $e = fn ($n) => App\\Models\\Element::where('canvas_id', ${canvas.id})->where('name', $n)->value('id'); $l = fn ($area, $element, $x, $y, $w, $h) => App\\Models\\Layout::create(['canvas_id' => ${canvas.id}, 'area_id' => $area, 'element_id' => $element, 'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h, 'z' => 0, 'style' => [], 'format_version' => 1]); $p = App\\Models\\Element::create(['canvas_id' => ${canvas.id}, 'area_id' => $a('primary_activities'), 'kind' => 'domain', 'name' => 'Verkoop', 'position' => 2]); $q = App\\Models\\Element::create(['canvas_id' => ${canvas.id}, 'area_id' => $a('primary_activities'), 'kind' => 'domain', 'name' => 'Smal', 'position' => 3]); App\\Models\\Element::create(['canvas_id' => ${canvas.id}, 'area_id' => $a('primary_activities'), 'kind' => 'activity', 'name' => 'Les geven', 'parent_id' => $q->id, 'position' => 1]); $l($a('primary_activities'), null, 780, 400, 880, 400); $l(null, $q->id, 400, 260, 120, 100); $l(null, $e('Onderwijs'), 20, 40, 300, 200); $l(null, $e('Lessen'), 8, 40, 260, 120); $l(null, $e('Les geven'), 8, 40, 200, 30); $l(null, $p->id, 400, 40, 300, 200); $l($a('supporting_activities'), null, 780, 820, 880, 340); $l(null, $e('Beheer'), 20, 40, 300, 200); App\\Models\\Canvas::whereKey(${canvas.id})->increment('layout_version', 1, ['layout_saved_at' => now()->subDay()]);`;
    seedPhp(code);

    return canvas;
}

export async function screenOf(page: Page, absolute: Spot): Promise<Spot> {
    const host = page.locator('[data-view]');
    const box = (await host.boundingBox())!;
    const view = JSON.parse((await host.getAttribute('data-view')) ?? '{}') as { x: number; y: number; zoom: number };

    return { x: box.x + view.x + view.zoom * absolute.x, y: box.y + view.y + view.zoom * absolute.y };
}

export async function shiftDrag(page: Page, from: Spot, to: Spot, beforeRelease?: () => Promise<void>): Promise<void> {
    await page.mouse.move(from.x, from.y);
    await page.keyboard.down('Shift');
    await page.mouse.down();
    await page.mouse.move((from.x + to.x) / 2, (from.y + to.y) / 2, { steps: 4 });
    await page.mouse.move(to.x, to.y, { steps: 6 });
    if (beforeRelease) await beforeRelease();
    await page.mouse.up();
    await page.keyboard.up('Shift');
}

export const ACTIVITY: Spot = { x: 830, y: 535 };
export const DOMAIN_TARGET: Spot = { x: 1200, y: 460 };
export const NARROW_TARGET: Spot = { x: 1200, y: 700 };
export const CLUSTER: Spot = { x: 830, y: 490 };

export async function dragTo(page: Page, from: Spot, to: Spot): Promise<void> {
    await shiftDrag(page, await screenOf(page, from), await screenOf(page, to));
}

export function structureChanges(canvas: number, code: string): void {
    seedPhp(code);
    seedPhp(`App\\Models\\Canvas::whereKey(${canvas})->increment('structure_version');`);
}

export function layoutChanges(canvas: number): void {
    seedPhp(`App\\Models\\Canvas::whereKey(${canvas})->increment('layout_version');`);
}

export function seedMoveContent(): ViewCanvas {
    const canvas = seedMoveCanvas();
    seedPhp(`App\\Models\\Layout::where('canvas_id', ${canvas.id})->delete();`);

    return canvas;
}
