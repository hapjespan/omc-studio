import { expect, test, type Page } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';

interface Box {
    x: number;
    y: number;
    width: number;
    height: number;
}

const rgb = (hex: string) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));

async function open(page: Page, url: string) {
    await page.goto(url);
    await page.waitForFunction(() => document.documentElement.dataset.ready === 'true' || document.documentElement.dataset.error, null, { timeout: 30000 });
    expect(await page.evaluate(() => document.documentElement.dataset.error ?? '')).toBe('');
    await page.waitForTimeout(800);
    await page.evaluate(() => document.fonts.ready);
}

function analyse(page: Page, canvasShot: Buffer, htmlShot: Buffer, blocks: any[]) {
    return page.evaluate(
        async ({ shots, blocks }) => {
            const load = async (data: string) => {
                const image = new Image();
                image.src = `data:image/png;base64,${data}`;
                await image.decode();
                const canvas = document.createElement('canvas');
                canvas.width = image.width;
                canvas.height = image.height;
                const context = canvas.getContext('2d', { willReadFrequently: true })!;
                context.drawImage(image, 0, 0);
                return context.getImageData(0, 0, image.width, image.height);
            };
            const images = [await load(shots[0]), await load(shots[1])];
            const hex = (value: string) => [1, 3, 5].map((i) => parseInt(value.slice(i, i + 2), 16));
            const near = (data: ImageData, x: number, y: number, color: number[], tolerance: number) => {
                const i = (y * data.width + x) * 4;
                return Math.abs(data.data[i] - color[0]) + Math.abs(data.data[i + 1] - color[1]) + Math.abs(data.data[i + 2] - color[2]) <= tolerance;
            };
            const fillExtent = (data: ImageData, block: any) => {
                const color = hex(block.fill);
                let minX = 1e9, minY = 1e9, maxX = -1, maxY = -1;
                for (let y = Math.max(0, block.ay - 6); y < Math.min(data.height, block.ay + block.height + 6); y++) {
                    for (let x = Math.max(0, block.ax - 6); x < Math.min(data.width, block.ax + block.width + 6); x++) {
                        if (!near(data, x, y, color, 6)) continue;
                        minX = Math.min(minX, x); minY = Math.min(minY, y); maxX = Math.max(maxX, x); maxY = Math.max(maxY, y);
                    }
                }
                return { x: minX, y: minY, width: maxX - minX + 1, height: maxY - minY + 1 };
            };
            const ink = (data: ImageData, box: any, color: number[]) => {
                let minX = 1e9, minY = 1e9, maxX = -1, maxY = -1;
                for (let y = box.y; y < box.y + box.height; y++) {
                    for (let x = box.x; x < box.x + box.width; x++) {
                        if (near(data, x, y, color, 120)) continue;
                        minX = Math.min(minX, x); minY = Math.min(minY, y); maxX = Math.max(maxX, x); maxY = Math.max(maxY, y);
                    }
                }
                let mass = 0, centroidY = 0;
                for (let y = box.y; y < box.y + box.height; y++) {
                    for (let x = box.x; x < box.x + box.width; x++) {
                        const i = (y * data.width + x) * 4;
                        const weight = Math.abs(data.data[i] - color[0]) + Math.abs(data.data[i + 1] - color[1]) + Math.abs(data.data[i + 2] - color[2]);
                        mass += weight;
                        centroidY += weight * y;
                    }
                }
                return maxX < 0 ? null : { x: minX, y: minY, width: maxX - minX + 1, height: maxY - minY + 1, centroidY: centroidY / mass, mass };
            };
            return blocks.map((block: any) => {
                const fills = images.map((data) => fillExtent(data, block));
                const lines = block.lines.map((_: string, index: number) => {
                    const band = { x: block.ax + block.padding, y: block.ay + block.padding + index * block.lineHeight, width: block.width - 2 * block.padding + 8, height: block.lineHeight };
                    const color = hex(block.fill);
                    return images.map((data) => ink(data, band, color));
                });
                const below = images.map((data) => ink(data, { x: block.ax + block.padding, y: block.ay + block.padding + block.lines.length * block.lineHeight, width: block.width - 2 * block.padding, height: Math.min(6, block.height - 2 * block.padding - block.lines.length * block.lineHeight) }, hex(block.fill)));
                return { id: block.id, expected: { x: block.ax, y: block.ay, width: block.width, height: block.height }, fills, lines, below, hasChildrenBelow: block.kind !== 'activity' };
            });
        },
        { shots: [canvasShot.toString('base64'), htmlShot.toString('base64')], blocks },
    );
}

test('P7: the drawing equals the HTML view (vertical text limit 1 px, 1.1 px in Chromium)', async ({ page }, info) => {
    await open(page, '/?view=html');
    const htmlShot = await page.screenshot();
    const dom = await page.evaluate(() => {
        const board = document.getElementById('html-artboard')!.getBoundingClientRect();
        const boxes = [...document.querySelectorAll<HTMLElement>('.html-block')].map((element) => {
            const rect = element.getBoundingClientRect();
            return { id: element.dataset.id!, x: rect.x - board.x, y: rect.y - board.y, width: rect.width, height: rect.height, lines: [...element.querySelectorAll('.html-line')].map((line) => line.textContent) };
        });
        return { boxes, blocks: (window as any).proof.blocks };
    });

    await open(page, '/');
    const canvasShot = await page.screenshot();
    const drawn = await page.evaluate(() => {
        const { blocks, graph } = (window as any).proof;
        return blocks.map((block: any) => {
            const bounds = graph.getAbsoluteBounds(block.id);
            return { id: block.id, ax: bounds.x, ay: bounds.y, width: bounds.width, height: bounds.height };
        });
    });
    const blocks = dom.blocks.map((block: any) => {
        const place = drawn.find((entry: any) => entry.id === block.id);
        return { ...block, ax: place.ax, ay: place.ay };
    });

    for (const box of dom.boxes) {
        const block = blocks.find((entry: any) => entry.id === box.id);
        expect(Math.abs(box.x - block.ax), `${box.id} x`).toBeLessThanOrEqual(1);
        expect(Math.abs(box.y - block.ay), `${box.id} y`).toBeLessThanOrEqual(1);
        expect(Math.abs(box.width - block.width), `${box.id} width`).toBeLessThanOrEqual(1);
        expect(Math.abs(box.height - block.height), `${box.id} height`).toBeLessThanOrEqual(1);
        expect(box.lines, `${box.id} lines`).toEqual(block.lines);
    }

    const rows = await analyse(page, canvasShot, htmlShot, blocks.filter((block: any) => block.kind !== 'direction' && block.kind !== 'logo'));
    const delta = { box: 0, lineLeft: 0, lineRight: 0, lineTop: 0, lineBottom: 0, centroidY: 0, massRatio: 1 };
    const problems: string[] = [];
    const perLine: any[] = [];
    for (const row of rows) {
        const [canvasFill, htmlFill] = row.fills;
        for (const key of ['x', 'y', 'width', 'height'] as const) {
            delta.box = Math.max(delta.box, Math.abs(canvasFill[key] - htmlFill[key]), Math.abs(canvasFill[key] - row.expected[key]));
        }
        row.lines.forEach((pair: any, index: number) => {
            const [c, h] = pair;
            if (!c || !h) {
                problems.push(`${row.id} line ${index}: ink canvas=${!!c} html=${!!h}`);
                return;
            }
            const source = blocks.find((entry: any) => entry.id === row.id);
            perLine.push({ id: row.id, line: index, text: source.lines[index], font: `${source.font.family} ${source.font.weight} ${source.font.size}`, left: c.x - h.x, right: c.x + c.width - (h.x + h.width), top: c.y - h.y, bottom: c.y + c.height - (h.y + h.height), canvasWidth: c.width, htmlWidth: h.width, centroidY: +(c.centroidY - h.centroidY).toFixed(2) });
            delta.centroidY = Math.max(delta.centroidY, Math.abs(c.centroidY - h.centroidY));
            delta.massRatio = Math.max(delta.massRatio, c.mass / h.mass, h.mass / c.mass);
            delta.lineLeft = Math.max(delta.lineLeft, Math.abs(c.x - h.x));
            delta.lineRight = Math.max(delta.lineRight, Math.abs(c.x + c.width - (h.x + h.width)));
            delta.lineTop = Math.max(delta.lineTop, Math.abs(c.y - h.y));
            delta.lineBottom = Math.max(delta.lineBottom, Math.abs(c.y + c.height - (h.y + h.height)));
        });
        if (!row.hasChildrenBelow) row.below.forEach((entry: any, side: number) => entry && problems.push(`${row.id}: extra ink below the last line in ${side === 0 ? 'canvas' : 'html'}`));
    }
    mkdirSync('../../specs/001-omc-studio-v1/wp-e0-evidence', { recursive: true });
    writeFileSync(`../../specs/001-omc-studio-v1/wp-e0-evidence/p7-${info.project.name}.json`, JSON.stringify({ delta, problems, blocks: rows.length, boxes: rows.map((row: any) => ({ id: row.id, expected: row.expected, canvas: row.fills[0], html: row.fills[1] })), perLine }, null, 2));
    await page.screenshot({ path: `../../specs/001-omc-studio-v1/wp-e0-evidence/p7-canvas-${info.project.name}.png` });
    expect(problems).toEqual([]);
    expect(delta.box).toBeLessThanOrEqual(1);
    expect(delta.massRatio, 'ink mass of a line, drawing against HTML').toBeLessThanOrEqual(1.5);
    expect(delta.lineLeft).toBeLessThanOrEqual(1);
    expect(delta.lineRight).toBeLessThanOrEqual(1);
    const verticalLimit = info.project.name === 'chromium' ? 1.1 : 1;
    expect(delta.centroidY, `vertical text offset limit ${verticalLimit}`).toBeLessThanOrEqual(verticalLimit);
    expect(delta.lineTop).toBeLessThanOrEqual(2);
    expect(delta.lineBottom).toBeLessThanOrEqual(2);
});
