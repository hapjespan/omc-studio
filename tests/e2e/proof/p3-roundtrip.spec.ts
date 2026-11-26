import { expect, test } from '@playwright/test';
import { openProof } from './support';

test('P3: the nodes read back unchanged after the library rendered them', async ({ page }) => {
    await openProof(page);
    const { before, after } = await page.evaluate(() => {
        const { blocks, readBlocks } = (window as any).proof;
        const before = blocks.map((block: any) => ({ id: block.id, x: block.x, y: block.y, width: block.width, height: block.height, fill: block.fill, lines: block.lines }));
        const after = readBlocks().map((block: any) => ({ id: block.id, x: block.x, y: block.y, width: block.width, height: block.height, fill: block.fill, lines: block.lines }));
        return { before, after };
    });
    const byId = (a: any, b: any) => a.id.localeCompare(b.id);
    expect(after.sort(byId)).toEqual(before.sort(byId));
});
