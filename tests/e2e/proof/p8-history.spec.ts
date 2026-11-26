import { expect, test } from '@playwright/test';
import { openProof } from './support';

test('P8: a drag with the mouse is undone and redone as one step', async ({ page }) => {
    await openProof(page);
    const position = () =>
        page.evaluate(() => {
            const node = (window as any).proof.graph.getNode('act-5');
            return [node.x, node.y];
        });
    const before = await position();
    await page.evaluate(() => (window as any).proof.editor.select(['act-5']));
    await page.mouse.move(840, 322);
    await page.mouse.down();
    await page.mouse.move(850, 340, { steps: 6 });
    await page.mouse.move(860, 370, { steps: 6 });
    await page.mouse.up();
    await page.waitForTimeout(150);
    const after = await position();
    expect(after).not.toEqual(before);

    await page.evaluate(() => (window as any).proof.editor.undoAction());
    expect(await position()).toEqual(before);
    await page.evaluate(() => (window as any).proof.editor.undoAction());
    expect(await position()).toEqual(before);
    await page.evaluate(() => (window as any).proof.editor.redoAction());
    expect(await position()).toEqual(after);
    await page.evaluate(() => (window as any).proof.editor.redoAction());
    expect(await position()).toEqual(after);
});
