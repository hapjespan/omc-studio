import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { inkPixels, openProof, samplePixels, screenOrigin } from './support';

const rgb = (hex: string) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));

test('P2: the drawing matches the fixture', async ({ page }, info) => {
    const errors = await openProof(page);
    expect(errors).toEqual([]);
    const origin = await screenOrigin(page);
    expect(origin.zoom).toBeCloseTo(1, 5);
    const blocks = await page.evaluate(() => {
        const { blocks } = (window as any).proof;
        const byId = new Map<string, any>(blocks.map((block: any) => [block.id, block]));
        return blocks.map((block: any) => {
            let x = 0;
            let y = 0;
            for (let node = block; node; node = node.parent ? byId.get(node.parent) : undefined) {
                x += node.x;
                y += node.y;
            }
            return { ...block, ax: x, ay: y };
        });
    });
    const shot = await page.screenshot();
    mkdirSync('../../specs/001-omc-studio-v1/wp-e0-evidence', { recursive: true });
    await page.screenshot({ path: `../../specs/001-omc-studio-v1/wp-e0-evidence/p2-scene-${info.project.name}.png` });

    const corners = await samplePixels(page, shot, blocks.map((block: any) => ({ x: origin.x + block.ax + block.width - 4, y: origin.y + block.ay + block.height - 4 })));
    blocks.forEach((block: any, index: number) => {
        const want = rgb(block.fill);
        corners[index].forEach((channel, i) => expect(Math.abs(channel - want[i]), `${block.id} fill`).toBeLessThanOrEqual(2));
    });

    for (const block of blocks.filter((candidate: any) => candidate.lines.length > 0)) {
        const ink = await inkPixels(
            page,
            shot,
            { x: origin.x + block.ax + block.padding, y: origin.y + block.ay + block.padding, width: block.width - 2 * block.padding, height: block.lines.length * block.lineHeight },
            rgb(block.fill),
        );
        expect(ink, `${block.id} has drawn text`).toBeGreaterThan(30);
    }
});
