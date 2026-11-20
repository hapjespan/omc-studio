import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import type { Page } from '@playwright/test';
import { expect, test } from './support/guards';
import { seedMoveCanvas } from './support/drawing';
import { signInAs } from './support/shell';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

const tinker = (code: string): string => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env, maxBuffer: 64 * 1024 * 1024 }).toString();

const exportPng = async (page: Page): Promise<Buffer> => {
    await page.getByRole('button', { name: 'Exporteren' }).click();
    const download = page.waitForEvent('download');
    await page.getByRole('menuitem', { name: 'Exporteren als PNG' }).click();
    const bytes = readFileSync((await (await download).path()) as string);
    await expect(page.getByTestId('export-note')).toHaveText('PNG is gemaakt');

    return bytes;
};

const pixelAt = (page: Page, bytes: Buffer, x: number, y: number): Promise<[number, number, number]> =>
    page.evaluate(
        async ({ data, at }) => {
            const blob = new Blob([Uint8Array.from(atob(data), (character) => character.charCodeAt(0))], { type: 'image/png' });
            const bitmap = await createImageBitmap(blob);
            const canvas = document.createElement('canvas');
            canvas.width = bitmap.width;
            canvas.height = bitmap.height;
            const context = canvas.getContext('2d')!;
            context.drawImage(bitmap, 0, 0);
            const [red, green, blue] = context.getImageData(at.x, at.y, 1, 1).data;

            return [red, green, blue] as [number, number, number];
        },
        { data: bytes.toString('base64'), at: { x, y } },
    );

test('a fill stored from Tekenen shows in the PNG export', async ({ page }) => {
    test.setTimeout(120_000);
    const canvas = seedMoveCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await page.locator('[data-artboard]').waitFor();
    const artboard = (await page.locator('[data-artboard]').boundingBox())!;
    const scale = artboard.width / 1680;
    const block = (await page.locator('[data-block]', { has: page.getByText('Onderwijs', { exact: true }) }).first().boundingBox())!;
    const x = Math.round(((block.x + block.width - artboard.x) / scale - 10) * 2);
    const y = Math.round(((block.y + block.height - artboard.y) / scale - 10) * 2);

    const before = await pixelAt(page, await exportPng(page), x, y);
    expect(before).not.toEqual([255, 0, 0]);

    tinker(`$id = App\\Models\\Element::where('canvas_id', ${canvas.id})->where('name', 'Onderwijs')->value('id'); App\\Models\\Layout::where('element_id', $id)->update(['style' => json_encode(['fill' => '#FF0000'])]); App\\Models\\Canvas::whereKey(${canvas.id})->increment('layout_version');`);
    await page.reload();
    await page.locator('[data-artboard]').waitFor();

    const after = await pixelAt(page, await exportPng(page), x, y);
    for (const [index, channel] of [255, 0, 0].entries()) expect(Math.abs(after[index] - channel)).toBeLessThanOrEqual(2);
});
