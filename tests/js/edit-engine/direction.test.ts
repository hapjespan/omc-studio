import { createEditor } from '@open-pencil/core/editor';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import DirectionBand from '../../../resources/js/pages/Drawing/DirectionBand.vue';
import { restrict } from '../../../resources/js/edit-engine/restrictions';
import { buildScene } from '../../../resources/js/edit-engine/scene';
import { splitDirectionLine } from '../../../resources/js/canvas/directionLines';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { emptyInput } from '../layout/support';

const long = Array.from({ length: 80 }, (_, index) => `woord${index}`).join(' ');
const statements = (body: string) => [{ kind: 'mission' as const, title: null, body, position: 1 }];
const draw = (body: string) => computeCanvasLayout({ ...emptyInput(), statements: statements(body) }, fakeMeasurer);

describe('Direction band in the drawing', () => {
    it('draws the band text as locked nodes that nothing can move', () => {
        const drawn = draw('Leren voor het leven');
        const graph = buildScene(drawn);
        const editor = createEditor({ graph, skipInitialGraphSetup: true });
        restrict(editor, graph, ['direction']);
        const text = graph.getNode('direction-mission#text')!;
        expect(text.text).toContain('Leren voor het leven');
        graph.updateNode('direction-mission#text', { x: 400 });
        graph.updateNode('direction', { y: 50, height: 400 });
        expect([graph.getNode('direction-mission#text')!.x, graph.getNode('direction')!.y, graph.getNode('direction')!.height]).toEqual([text.x, 0, 180]);
    });

    it('cuts text that does not fit and lists the warning', async () => {
        const drawn = draw(long);
        expect(drawn.direction.find((column) => column.key === 'mission')!.lines.at(-1)).toMatch(/…$/);
        expect(drawn.warnings).toContainEqual({ kind: 'directionTextCut', column: 'mission' });
        const html = await renderToString(createSSRApp({ render: () => h(DirectionBand, { drawn, statements: statements(long) }) }));
        expect(html).toContain('Koerstekst past niet in de koersbalk');
        expect(html).toContain('woord79');
        expect(html).toContain('tabindex="0"');
    });

    it('shows no warning when everything fits', async () => {
        const drawn = draw('Kort');
        const html = await renderToString(createSSRApp({ render: () => h(DirectionBand, { drawn, statements: statements('Kort') }) }));
        expect(html).not.toContain('Koerstekst past niet');
    });
});

describe('Direction band arrangement shared with Bekijken', () => {
    const goals = [
        { kind: 'goal' as const, title: 'Groei', body: 'meer leerlingen bereiken', position: 1 },
        { kind: 'goal' as const, title: null, body: 'tevreden team', position: 2 },
    ];
    const drawn = computeCanvasLayout({ ...emptyInput(), statements: goals }, fakeMeasurer);

    it('draws the dashed Logo box that Bekijken shows', () => {
        const graph = buildScene(drawn);
        const logo = graph.getNode('direction-logo-box#text')!;
        expect(logo).toBeDefined();
        expect([logo.width, logo.height]).toEqual([160, 92]);
        expect(logo.strokes[0].dashPattern?.length).toBeGreaterThan(0);
        expect(graph.getNode('direction-logo-label#text')!.text).toBe('LOGO');
    });

    it('keeps goal weights to the bold segment only, numbers apart', () => {
        const graph = buildScene(drawn);
        const column = drawn.direction.find((entry) => entry.key === 'goals')!;
        const body = graph.getNode('direction-goals#text')!;
        const segments = column.lines.map((line, index) => splitDirectionLine(line, column.marks[index]));
        expect(body.fontWeight).toBe(400);
        expect(body.text).toBe(segments.map((segment) => segment.bold + segment.rest).join('\n'));
        expect(body.styleRuns.some((run) => run.style.fontWeight === 600)).toBe(true);
        expect(graph.getNode('direction-goals-number-0#text')!.text).toBe(segments[0].number.trim());
    });
});
