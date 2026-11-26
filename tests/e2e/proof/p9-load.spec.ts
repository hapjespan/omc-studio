import { expect, test } from '@playwright/test';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { cpus, totalmem } from 'node:os';

const median = (values: number[]) => [...values].sort((a, b) => a - b)[Math.floor(values.length / 2)];

async function coldLoad(browser: any, url: string, throttle: number) {
    const context = await browser.newContext({ viewport: { width: 1700, height: 1200 }, deviceScaleFactor: 1 });
    const page = await context.newPage();
    if (throttle > 1 && browser.browserType().name() === 'chromium') {
        const session = await context.newCDPSession(page);
        await session.send('Emulation.setCPUThrottlingRate', { rate: throttle });
    }
    const started = Date.now();
    await page.goto(url);
    await page.waitForFunction(() => document.documentElement.dataset.ready === 'true' || document.documentElement.dataset.error, null, { timeout: 60000 });
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    const wall = Date.now() - started;
    expect(await page.evaluate(() => document.documentElement.dataset.error ?? '')).toBe('');
    const load = await page.evaluate(() => (window as any).proof.load);
    const action = await page.evaluate(
        () =>
            new Promise<number>((resolve) => {
                const w = window as any;
                const stop = w.proof.watchFinishedActions(() => {
                    stop();
                    resolve(performance.now() - t0);
                });
                const t0 = performance.now();
                w.proof.editor.updateNodeWithUndo(w.proof.graph.getNode('act-5') ? 'act-5' : 'bulk-1', { x: 5 }, 'Move');
            }),
    );
    await context.close();
    return { wall, readyAt: Math.round(load.readyAt), totalBytes: load.totalBytes, heapMegabytes: load.heapMegabytes, actionMs: +action.toFixed(1) };
}

test('P9: the drawing is usable within 3 seconds on a cold load', async ({ browser }, info) => {
    test.setTimeout(240000);
    const runs = 5;
    const result: Record<string, any> = {
        machine: { cpu: cpus()[0].model, arch: process.arch, cores: cpus().length, memoryGigabytes: +(totalmem() / 1073741824).toFixed(1), kernel: readFileSync('/proc/version', 'utf8').slice(0, 60), browser: `${browser.browserType().name()} ${browser.version()}`, note: 'container in a virtual machine, headless, software rendering; not an ordinary laptop' },
    };
    for (const [label, url, throttle] of [
        ['scene', '/', 1],
        ['scene-cpu-x4', '/', 4],
        ['bulk-500', '/?bulk=500', 1],
    ] as const) {
        if (throttle > 1 && info.project.name !== 'chromium') continue;
        const samples = [];
        for (let i = 0; i < runs; i++) samples.push(await coldLoad(browser, url, throttle));
        result[label] = { samples, medianReadyMs: median(samples.map((s) => s.readyAt)), maxReadyMs: Math.max(...samples.map((s) => s.readyAt)), maxWallMs: Math.max(...samples.map((s) => s.wall)), maxActionMs: Math.max(...samples.map((s) => s.actionMs)) };
    }
    mkdirSync('../../specs/001-omc-studio-v1/wp-e0-evidence', { recursive: true });
    writeFileSync(`../../specs/001-omc-studio-v1/wp-e0-evidence/p9-load-${info.project.name}.json`, JSON.stringify(result, null, 2));
    expect(result.scene.maxWallMs).toBeLessThan(3000);
    expect(result['bulk-500'].maxActionMs).toBeLessThan(1000);
});
