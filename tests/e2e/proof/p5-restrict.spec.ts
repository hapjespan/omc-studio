import { expect, test } from '@playwright/test';
import { openProof } from './support';

const press = (shortcut: string) => shortcut.replace(/Key([A-Z])$/, '$1');

test('P5: the library cannot create, delete or reparent, and keeps the locked parts and the children in place', async ({ page }) => {
    await openProof(page);
    const snapshot = () =>
        page.evaluate(() => {
            const { graph, editor } = (window as any).proof;
            return {
                nodes: [...graph.getAllNodes()].map((node: any) => [node.id, node.parentId, node.x, node.y, node.width, node.height].join('|')).sort(),
                tool: editor.state.activeTool,
            };
        });
    const initial = await snapshot();

    const shortcuts: string[] = await page.evaluate(() => (window as any).proof.inertShortcuts);
    expect(shortcuts.length).toBeGreaterThanOrEqual(12);
    await page.mouse.move(850, 1100);
    await page.evaluate(() => (window as any).proof.editor.select(['act-1', 'dom-2']));
    for (const shortcut of shortcuts) {
        await page.keyboard.press(press(shortcut));
    }
    await page.mouse.move(600, 500);
    await page.mouse.click(600, 500);
    await page.keyboard.press('Escape');
    const afterKeys = await snapshot();
    expect(afterKeys.tool).toBe('SELECT');
    expect(afterKeys.nodes).toEqual(initial.nodes);

    await page.evaluate(() => (window as any).proof.editor.select(['act-3']));
    await page.mouse.move(290, 802);
    await page.mouse.down();
    await page.mouse.move(800, 600, { steps: 12 });
    await page.mouse.move(1400, 400, { steps: 12 });
    await page.mouse.up();
    await page.waitForTimeout(200);
    const dragged = await page.evaluate(() => {
        const node = (window as any).proof.graph.getNode('act-3');
        const parent = (window as any).proof.graph.getNode(node.parentId);
        return { parent: node.parentId, inside: node.x >= 0 && node.y >= 0 && node.x + node.width <= parent.width && node.y + node.height <= parent.height };
    });
    expect(dragged).toEqual({ parent: 'dom-1', inside: true });

    await page.evaluate(() => (window as any).proof.editor.select(['area-direction']));
    await page.mouse.move(600, 100);
    await page.mouse.down();
    await page.mouse.move(700, 400, { steps: 8 });
    await page.mouse.up();
    await page.mouse.dblclick(300, 100);
    await page.mouse.dblclick(380, 390);
    await page.waitForTimeout(200);
    const end = await snapshot();
    const band = end.nodes.find((entry) => entry.startsWith('area-direction|'));
    expect(band).toBe(initial.nodes.find((entry) => entry.startsWith('area-direction|')));
    expect(end.nodes.length).toBe(initial.nodes.length);
    expect(end.nodes.map((entry) => entry.split('|').slice(0, 2).join('|')).filter((entry) => !entry.startsWith('act-3|'))).toEqual(
        initial.nodes.map((entry) => entry.split('|').slice(0, 2).join('|')).filter((entry) => !entry.startsWith('act-3|')),
    );
    expect(end.tool === 'SELECT' || end.tool === 'HAND').toBe(true);
    const refusals = await page.evaluate(() => (window as any).proof.restrictions.refusals.map((r: any) => r.action));
    expect(refusals.filter((action: string) => action === 'reparent').length).toBeGreaterThanOrEqual(1);
});
