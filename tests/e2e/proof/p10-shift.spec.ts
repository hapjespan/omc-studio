import { expect, test, type Page } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { openProof } from './support';

const node = (page: Page, id: string) =>
    page.evaluate((id) => {
        const n = (window as any).proof.graph.getNode(id);
        return { parent: n.parentId, x: n.x, y: n.y, width: n.width, height: n.height };
    }, id);

async function shiftDrag(page: Page, from: [number, number], to: [number, number], release = true) {
    await page.mouse.move(...from);
    await page.mouse.down();
    await page.mouse.move((from[0] * 3 + to[0]) / 4, (from[1] * 3 + to[1]) / 4, { steps: 4 });
    await page.keyboard.down('Shift');
    await page.mouse.move((from[0] + to[0]) / 2, (from[1] + to[1]) / 2, { steps: 6 });
    await page.mouse.move(to[0], to[1], { steps: 6 });
    if (release) {
        await page.mouse.up();
        await page.keyboard.up('Shift');
    }
}

const select = (page: Page, ids: string[]) => page.evaluate((ids) => (window as any).proof.editor.select(ids), ids);
const label = (page: Page) => page.evaluate(() => { const l = document.getElementById('shift-label')!; return { hidden: l.hidden, text: l.textContent, valid: l.dataset.valid }; });
const evidence: Record<string, unknown> = {};

test('P10 control: without the adapter the library locks Shift-drags to one axis', async ({ page }) => {
    await page.goto('/?no-shift-move');
    await page.waitForFunction(() => document.documentElement.dataset.ready === 'true');
    await page.waitForTimeout(500);
    await select(page, ['act-3']);
    const before = await node(page, 'act-3');
    await shiftDrag(page, [290, 802], [300, 842]);
    await page.waitForTimeout(150);
    const after = await node(page, 'act-3');
    expect(after.x).toBe(before.x);
    expect(after.y).toBeGreaterThan(before.y);
    evidence.control = { before, after };
});

test('P10: a Shift-drag becomes a move intent for a valid target and nothing else', async ({ page }, info) => {
    await openProof(page);
    await select(page, ['act-3']);
    const start = await node(page, 'act-3');

    await shiftDrag(page, [290, 802], [300, 842]);
    await page.waitForTimeout(150);
    const free = await node(page, 'act-3');
    expect(free.x).toBe(start.x + 10);
    expect(free.y).toBe(start.y + 40);
    expect(free.parent).toBe('dom-1');
    expect(await page.evaluate(() => (window as any).proof.intents.length)).toBe(0);
    evidence.noAxisLock = { start, free };

    await page.evaluate(() => (window as any).proof.editor.undoAction());
    await select(page, ['act-3']);
    const sceneBefore = await page.evaluate(() => JSON.stringify((window as any).proof.readBlocks()));
    const reparentsBefore = await page.evaluate(() => (window as any).proof.restrictions.refusals.filter((r: any) => r.action === 'reparent').length);
    const here = await node(page, 'act-3');
    const cx = 56 + 234 + (here.x - 16);
    const cy = 220 + here.y + 22;
    await shiftDrag(page, [cx, cy], [1400, 700], false);
    const hover = await label(page);
    expect(hover).toEqual({ hidden: false, text: 'Naar Supporting activities', valid: 'true' });
    await page.screenshot({ path: `../../specs/001-omc-studio-v1/wp-e0-evidence/p10-label-valid-${info.project.name}.png` });
    await page.mouse.up();
    await page.keyboard.up('Shift');
    await page.waitForTimeout(150);

    const intents = await page.evaluate(() => (window as any).proof.intents);
    expect(intents.length).toBe(1);
    expect(intents[0].elementId).toBe('act-3');
    expect(intents[0].targetId).toBe('dom-3');
    expect(intents[0].layout.x).toBeGreaterThanOrEqual(0);
    expect(intents[0].layout.x + intents[0].layout.width).toBeLessThanOrEqual(500);
    expect(intents[0].layout.y + intents[0].layout.height).toBeLessThanOrEqual(700);
    const reparentsAfter = await page.evaluate(() => (window as any).proof.restrictions.refusals.filter((r: any) => r.action === 'reparent').length);
    expect(reparentsAfter).toBe(reparentsBefore);
    expect((await node(page, 'act-3')).parent).toBe('dom-3');
    expect(await label(page)).toMatchObject({ hidden: true });
    evidence.intent = intents[0];

    const history = await page.evaluate(() => (window as any).proof.editor.undo.canUndo ?? null);
    await page.evaluate(() => (window as any).proof.shiftMove.revertMove('act-3'));
    expect(await page.evaluate(() => JSON.stringify((window as any).proof.readBlocks()))).toBe(sceneBefore);
    await page.evaluate(() => { (window as any).proof.editor.undoAction(); (window as any).proof.editor.undoAction(); });
    expect(await page.evaluate(() => JSON.stringify((window as any).proof.readBlocks()))).toBe(sceneBefore);
    evidence.revert = { restoredTo: here, historyBefore: history };

    await select(page, ['act-3']);
    await shiftDrag(page, [cx, cy], [840, 700], false);
    expect(await label(page)).toEqual({ hidden: false, text: 'Kan hier niet: ander soort gebied', valid: 'false' });
    await page.screenshot({ path: `../../specs/001-omc-studio-v1/wp-e0-evidence/p10-label-invalid-${info.project.name}.png` });
    await page.mouse.up();
    await page.keyboard.up('Shift');
    await page.waitForTimeout(150);
    expect(await page.evaluate(() => (window as any).proof.intents.length)).toBe(1);
    expect((await node(page, 'act-3')).parent).toBe('dom-1');

    await select(page, ['act-3']);
    await page.mouse.move(cx, cy);
    await page.mouse.down();
    await page.mouse.move(900, 700, { steps: 6 });
    await page.mouse.move(1400, 700, { steps: 6 });
    expect(await label(page)).toMatchObject({ hidden: true });
    await page.mouse.up();
    await page.waitForTimeout(150);
    expect(await page.evaluate(() => (window as any).proof.intents.length)).toBe(1);
    expect((await node(page, 'act-3')).parent).toBe('dom-1');

    await select(page, ['act-3', 'act-4']);
    await shiftDrag(page, [cx, cy], [1400, 700]);
    await page.waitForTimeout(150);
    expect(await page.evaluate(() => (window as any).proof.intents.length)).toBe(1);

    mkdirSync('../../specs/001-omc-studio-v1/wp-e0-evidence', { recursive: true });
    writeFileSync(`../../specs/001-omc-studio-v1/wp-e0-evidence/p10-${info.project.name}.json`, JSON.stringify(evidence, null, 2));
});

test('P10: a Shift-click without movement still adds to the selection', async ({ page }) => {
    await openProof(page);
    await select(page, ['act-3']);
    await page.keyboard.down('Shift');
    await page.mouse.click(290, 880);
    await page.keyboard.up('Shift');
    const ids = await page.evaluate(() => (window as any).proof.editor.getSelectedNodes().map((n: any) => n.id).sort());
    expect(ids).toEqual(['act-3', 'act-4']);
    expect(await page.evaluate(() => (window as any).proof.intents.length)).toBe(0);
});
