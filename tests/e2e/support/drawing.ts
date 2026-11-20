import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';
import { signInAs } from './shell';
import { seedViewCanvas, type ViewCanvas } from './view';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

export function seedRow(): ViewCanvas {
    const canvas = seedViewCanvas();
    const code = `$c = App\\Models\\Canvas::find(${canvas.id}); $a = App\\Models\\Area::where('key', 'primary_activities')->value('id'); $d = App\\Models\\Element::where('canvas_id', ${canvas.id})->where('name', 'Onderwijs')->first(); foreach (['Alfa', 'Beta', 'Gamma'] as $i => $n) { App\\Models\\Element::create(['canvas_id' => ${canvas.id}, 'area_id' => $a, 'kind' => 'activity', 'name' => $n, 'parent_id' => $d->id, 'position' => 10 + $i]); }`;
    execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env });
    return canvas;
}

export async function openDrawing(page: Page, canvas: ViewCanvas = seedViewCanvas()) {
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.waitForTimeout(500);
    return page.getByRole('navigation', { name: 'Lagen' });
}
