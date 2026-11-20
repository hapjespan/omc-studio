import type { Page } from '@playwright/test';

export const pixelAt = (page: Page, bytes: Buffer, x: number, y: number): Promise<[number, number, number]> =>
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
