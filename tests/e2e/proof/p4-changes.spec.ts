import { expect, test } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { openProof } from './support';

test('P4: a change notice arrives within 100 ms of the finished action', async ({ page }) => {
    await openProof(page);
    await page.evaluate(() => {
        const w = window as any;
        w.notices = [];
        w.triggers = [];
        w.proof.watchFinishedActions((action: any) => w.notices.push(action));
        window.addEventListener('pointerup', () => w.triggers.push({ kind: 'drag', at: performance.now() }), true);
        window.addEventListener('keydown', () => w.triggers.push({ kind: 'key', at: performance.now() }), true);
    });

    const drags = 8;
    for (let i = 0; i < drags; i++) {
        const x = 290;
        const y = 802;
        await page.mouse.move(x, y);
        await page.mouse.down();
        await page.mouse.move(x + 30 + i, y + 8, { steps: 4 });
        await page.mouse.up();
        await page.waitForTimeout(150);
    }
    await page.evaluate(() => {
        const w = window as any;
        w.proof.editor.select(['act-1']);
        w.triggers.push({ kind: 'nudge-debounced', at: performance.now() });
        w.proof.editor.nudgeSelected(10, 0);
    });
    await page.waitForTimeout(600);
    await page.evaluate(() => {
        const w = window as any;
        w.triggers.push({ kind: 'nudge-flushed', at: performance.now() });
        w.proof.editor.nudgeSelected(10, 0);
        w.proof.editor.flushNudge();
    });
    await page.waitForTimeout(600);
    await page.evaluate(() => {
        const w = window as any;
        w.triggers.push({ kind: 'command', at: performance.now() });
        w.proof.editor.updateNodeWithUndo('act-5', { x: 40 }, 'Move');
    });
    await page.waitForTimeout(150);

    const { notices, triggers } = await page.evaluate(() => ({ notices: (window as any).notices, triggers: (window as any).triggers }));
    const pairs = triggers.map((trigger: any) => {
        const notice = notices.find((candidate: any) => candidate.at >= trigger.at);
        return notice ? { kind: trigger.kind, ms: +(notice.at - trigger.at).toFixed(2), ids: notice.ids } : { kind: trigger.kind, ms: null, ids: [] };
    });
    mkdirSync('../../specs/001-omc-studio-v1/wp-e0-evidence', { recursive: true });
    writeFileSync('../../specs/001-omc-studio-v1/wp-e0-evidence/p4-latency.json', JSON.stringify({ pairs, notices: notices.length }, null, 2));
    const drag = pairs.filter((pair: any) => pair.kind === 'drag');
    expect(drag.length).toBe(drags);
    for (const pair of drag) expect(pair.ms).toBeLessThan(100);
    const flushed = pairs.find((pair: any) => pair.kind === 'nudge-flushed');
    expect(flushed.ms).toBeLessThan(100);
    const command = pairs.find((pair: any) => pair.kind === 'command');
    expect(command.ms).toBeLessThan(100);
});
