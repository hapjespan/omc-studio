import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import Header from '../../../resources/js/pages/Drawing/Header.vue';
import type { SaveStatus } from '../../../resources/js/pages/Drawing/SaveQueue';

const render = (status: SaveStatus): Promise<string> => renderToString(createSSRApp({ render: () => h(Header, { status, canUndo: false, canRedo: false }) }));

const slotOf = (html: string): string | undefined => /<div ([^>]*data-save-slot[^>]*)>/.exec(html)?.[1];

describe('the Tekenen header', () => {
    it('keeps the save status in a slot of its own that is there in every state', async () => {
        const states: SaveStatus[] = ['idle', 'saving', 'saved', 'failed'];
        const slots = await Promise.all(states.map(async (status) => slotOf(await render(status))));

        expect(slots[0]).toBeDefined();
        expect(new Set(slots).size).toBe(1);
    });

    it('names the state inside that slot', async () => {
        expect(await render('saving')).toContain('Opslaan');
        expect(await render('saved')).toContain('Opmaak opgeslagen');
        expect(await render('failed')).toContain('Opmaak niet opgeslagen');
    });
});
