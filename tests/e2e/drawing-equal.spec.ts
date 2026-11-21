import { expect, test } from './support/guards';
import { HARNESS_URL } from './support/ports';
import { compareInk, type BlockSpot } from './support/inkCompare';

test.use({ baseURL: HARNESS_URL, viewport: { width: 1700, height: 1200 }, deviceScaleFactor: 1 });

const LIMIT = 1.05;

interface Placed {
    id: string;
    x: number;
    y: number;
    width: number;
    drawnHeight: number;
    textLines: string[];
}

for (const variant of ['', 'align']) {
test(`the text of Tekenen sits within 1 pixel (plus 0.05 of measurement quantisation) of Bekijken for Inter and Space Grotesk at every weight${variant ? ', aligned left, centre and right, with and without a Modality' : ''}`, async ({ page, browserName, guards }) => {
    if (browserName === 'webkit') {
        guards.skipCspViolations();
        test.info().annotations.push({ type: 'csp', description: 'WebKit page.screenshot injects an inline style that the harness CSP refuses; the CSP is covered on every engine by csp-guard.spec.ts and drawing-load.spec.ts, the outside-request guard stays on' });
    }
    const query = variant ? `&variant=${variant}` : '';
    await page.goto(`/?demo=Equal&view=html${query}`);
    await page.waitForFunction(() => document.querySelector('main')?.getAttribute('data-ready') === 'true');
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(500);
    const html = await page.screenshot();
    const htmlOrigin = await page.evaluate(() => {
        const box = document.querySelector('[data-artboard]')!.getBoundingClientRect();
        return { x: box.left, y: box.top };
    });

    await page.goto(`/?demo=Equal${query}`);
    await page.waitForFunction(() => document.querySelector('main')?.getAttribute('data-ready') === 'true');
    await page.waitForTimeout(1500);
    const drawing = await page.screenshot();
    const placed = await page.evaluate(() => (window as unknown as { equal: { blocks: Placed[] } }).equal.blocks);
    const view = await page.evaluate(() => (window as unknown as { equal: { engine: { getView(): { x: number; y: number; zoom: number } } } }).equal.engine.getView());
    const surface = await page.evaluate(() => {
        const box = document.querySelector('[data-drawing-surface]')!.getBoundingClientRect();
        return { x: box.left, y: box.top };
    });
    expect(view.zoom).toBeCloseTo(1, 3);

    const spots = (origin: { x: number; y: number }): BlockSpot[] => placed.map((block) => ({ id: block.id, x: origin.x + block.x, y: origin.y + block.y, width: block.width, height: block.drawnHeight, lines: block.textLines.length }));
    const analyser = await page.context().newPage();
    await analyser.goto('about:blank');
    const rows = await compareInk(analyser, [html, drawing], [spots(htmlOrigin), spots({ x: surface.x + view.x, y: surface.y + view.y })], 12, 20);

    await analyser.close();
    const problems: string[] = [];
    for (const row of rows) {
        if (row.fillDelta > 1) problems.push(`${row.id}: box differs by ${row.fillDelta}`);
        row.lines.forEach((line, index) => {
            const [reference, canvas] = line.centroid;
            if (!Number.isFinite(canvas) || !Number.isFinite(reference)) problems.push(`${row.id} line ${index}: no ink`);
            else if (Math.abs(canvas - reference) > LIMIT) problems.push(`${row.id} line ${index}: vertical offset ${(canvas - reference).toFixed(2)}`);
        });
    }
    test.info().annotations.push({ type: 'max vertical offset', description: rows.map((row) => `${row.id}:${row.lines.map((line) => (line.centroid[0] - line.centroid[1]).toFixed(2)).join('/')}`).join(' ') + ' max ' + Math.max(...rows.flatMap((row) => row.lines.map((line) => Math.abs(line.centroid[0] - line.centroid[1])))).toFixed(3) });
    expect(problems).toEqual([]);
});
}
