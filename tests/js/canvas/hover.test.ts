import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CanvasView from '../../../resources/js/canvas/CanvasView.vue';
import { DIRECTION_PREFIX, directionText } from '../../../resources/js/canvas/HoverContent.vue';
import { createPeek } from '../../../resources/js/canvas/peek';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { eightAreas, element, emptyInput } from '../layout/support';

describe('createPeek', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('opens at once and closes after a short delay', () => {
        const { peeked, peek } = createPeek(200);
        peek('a');
        expect(peeked.value).toBe('a');
        peek(null);
        expect(peeked.value).toBe('a');
        vi.advanceTimersByTime(200);
        expect(peeked.value).toBeNull();
    });

    it('stays open while the pointer moves onto the content', () => {
        const { peeked, peek } = createPeek(200);
        peek('a');
        peek(null);
        vi.advanceTimersByTime(100);
        peek('a');
        vi.advanceTimersByTime(500);
        expect(peeked.value).toBe('a');
    });

    it('closes with Escape at once and no stale timer reopens it', () => {
        const { peeked, peek, dismiss } = createPeek(200);
        peek('a');
        peek(null);
        dismiss();
        expect(peeked.value).toBeNull();
        peek('b');
        vi.advanceTimersByTime(500);
        expect(peeked.value).toBe('b');
    });
});

describe('directionText', () => {
    const statements = [
        { kind: 'goal' as const, title: 'Groei', body: 'Meer', position: 2 },
        { kind: 'goal' as const, title: null, body: 'Minder', position: 1 },
        { kind: 'vision' as const, title: null, body: 'Ver', position: 1 },
    ];

    it('returns the full statements of a column in order', () => {
        expect(directionText(statements, 'goals')).toEqual(['Minder', 'Groei: Meer']);
        expect(directionText(statements, 'vision')).toEqual(['Ver']);
        expect(directionText(statements, 'logo')).toEqual([]);
        expect(DIRECTION_PREFIX).toBe('direction:');
    });
});

describe('Unknown allocation card in the view', () => {
    it('carries the Systeem mark', async () => {
        const elements = [element('u', 'supporting_activities', 'domain', 'Onbekende allocatie', null, 1, { unknownAllocation: true }), element('h', 'supporting_activities', 'domain', 'HR', null, 2)];
        const html = await renderToString(createSSRApp({ render: () => h(CanvasView, { drawn: computeCanvasLayout({ ...emptyInput(), elements }, fakeMeasurer), areas: eightAreas, elements, statements: [], modalities: [] }) }));
        expect(html.match(/data-system-mark/g)).toHaveLength(1);
        expect(html).toMatch(/data-block="u"[^>]*>(?:(?!<\/button>).)*data-system-mark/);
    });
});
