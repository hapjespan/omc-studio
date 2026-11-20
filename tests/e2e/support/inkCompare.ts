import type { Page } from '@playwright/test';

export interface BlockSpot {
    id: string;
    x: number;
    y: number;
    width: number;
    height: number;
    lines: number;
}

export interface InkResult {
    id: string;
    fillDelta: number;
    lines: { centroid: number[]; ink: number[] }[];
}

export function compareInk(page: Page, shots: [Buffer, Buffer], spots: [BlockSpot[], BlockSpot[]], padding: number, lineHeight: number): Promise<InkResult[]> {
    return page.evaluate(
        async ({ data, spots, padding, lineHeight }) => {
            const fill = [0xf6, 0xe6, 0xcc];
            const load = async (encoded: string) => {
                const image = new Image();
                image.src = `data:image/png;base64,${encoded}`;
                await image.decode();
                const canvas = document.createElement('canvas');
                canvas.width = image.width;
                canvas.height = image.height;
                const context = canvas.getContext('2d', { willReadFrequently: true })!;
                context.drawImage(image, 0, 0);
                return context.getImageData(0, 0, image.width, image.height);
            };
            const images = [await load(data[0]), await load(data[1])];
            const near = (image: ImageData, x: number, y: number, tolerance: number) => {
                const i = (Math.round(y) * image.width + Math.round(x)) * 4;
                return Math.abs(image.data[i] - fill[0]) + Math.abs(image.data[i + 1] - fill[1]) + Math.abs(image.data[i + 2] - fill[2]) <= tolerance;
            };
            const fillTop = (image: ImageData, spot: { x: number; y: number; width: number; height: number }) => {
                let top = Infinity;
                for (let y = Math.max(0, spot.y - 6); y < spot.y + spot.height + 6; y++) {
                    for (let x = Math.round(spot.x + 20); x < spot.x + spot.width - 20; x++) {
                        if (near(image, x, y, 6)) top = Math.min(top, y);
                    }
                }
                return top;
            };
            const centroid = (image: ImageData, box: { x: number; y: number; width: number; height: number }) => {
                let mass = 0;
                let sum = 0;
                let sumX = 0;
                for (let y = Math.round(box.y); y < box.y + box.height; y++) {
                    for (let x = Math.round(box.x); x < box.x + box.width; x++) {
                        const i = (y * image.width + x) * 4;
                        const weight = Math.abs(image.data[i] - fill[0]) + Math.abs(image.data[i + 1] - fill[1]) + Math.abs(image.data[i + 2] - fill[2]);
                        if (weight < 120) continue;
                        mass += weight;
                        sum += weight * y;
                        sumX += weight * x;
                    }
                }
                return mass === 0 ? null : { y: sum / mass, x: sumX / mass, mass };
            };
            return spots[0].map((first: { id: string; x: number; y: number; width: number; height: number; lines: number }, index: number) => {
                const second = spots[1][index];
                const tops = [fillTop(images[0], first), fillTop(images[1], second)];
                const lines = Array.from({ length: first.lines }, (_, line) => {
                    const band = (spot: typeof first) => ({ x: spot.x + padding, y: spot.y + padding + line * lineHeight, width: spot.width - 2 * padding, height: lineHeight });
                    const measured = [centroid(images[0], band(first)), centroid(images[1], band(second))];
                    return { centroid: measured.map((entry) => entry?.y ?? NaN), ink: measured.map((entry) => entry?.mass ?? 0) };
                });
                return { id: first.id, fillDelta: Math.abs(tops[0] - first.y - (tops[1] - second.y)), lines };
            });
        },
        { data: [shots[0].toString('base64'), shots[1].toString('base64')], spots, padding, lineHeight },
    );
}
