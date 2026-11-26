import type { Page } from '@playwright/test';

export async function openProof(page: Page) {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(String(error)));
    await page.goto('/');
    await page.waitForFunction(() => document.documentElement.dataset.ready === 'true' || document.documentElement.dataset.error, null, { timeout: 30000 });
    const failure = await page.evaluate(() => document.documentElement.dataset.error ?? '');
    if (failure) throw new Error(failure);
    await page.waitForTimeout(500);
    return errors;
}

export function screenOrigin(page: Page) {
    return page.evaluate(() => {
        const { editor } = (window as any).proof;
        const a = editor.screenToCanvas(0, 0);
        const b = editor.screenToCanvas(100, 100);
        const zoom = 100 / (b.x - a.x);
        return { x: -a.x * zoom, y: -a.y * zoom, zoom };
    });
}

export function samplePixels(page: Page, shot: Buffer, points: { x: number; y: number }[]) {
    return page.evaluate(
        async ({ data, points }) => {
            const image = new Image();
            image.src = `data:image/png;base64,${data}`;
            await image.decode();
            const canvas = document.createElement('canvas');
            canvas.width = image.width;
            canvas.height = image.height;
            const context = canvas.getContext('2d', { willReadFrequently: true })!;
            context.drawImage(image, 0, 0);
            return points.map(({ x, y }) => Array.from(context.getImageData(Math.round(x), Math.round(y), 1, 1).data.slice(0, 3)));
        },
        { data: shot.toString('base64'), points },
    );
}

export function inkPixels(page: Page, shot: Buffer, box: { x: number; y: number; width: number; height: number }, fill: number[]) {
    return page.evaluate(
        async ({ data, box, fill }) => {
            const image = new Image();
            image.src = `data:image/png;base64,${data}`;
            await image.decode();
            const canvas = document.createElement('canvas');
            canvas.width = image.width;
            canvas.height = image.height;
            const context = canvas.getContext('2d', { willReadFrequently: true })!;
            context.drawImage(image, 0, 0);
            const pixels = context.getImageData(Math.round(box.x), Math.round(box.y), Math.round(box.width), Math.round(box.height)).data;
            let ink = 0;
            for (let i = 0; i < pixels.length; i += 4) {
                if (Math.abs(pixels[i] - fill[0]) + Math.abs(pixels[i + 1] - fill[1]) + Math.abs(pixels[i + 2] - fill[2]) > 90) ink++;
            }
            return ink;
        },
        { data: shot.toString('base64'), box, fill },
    );
}
