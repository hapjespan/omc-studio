import { afterEach, describe, expect, it, vi } from 'vitest';
import { sendReset } from '../../../resources/js/pages/Drawing/resetRequest';

afterEach(() => vi.unstubAllGlobals());

describe('sendReset', () => {
    it('sends the structure and layout versions and reports a conflict', async () => {
        const fetcher = vi.fn().mockResolvedValue({ ok: false, status: 409, json: async () => ({ conflict: 'layout_changed' }) });
        vi.stubGlobal('fetch', fetcher);
        vi.stubGlobal('document', { cookie: '' });

        const result = await sendReset(5, 'canvas', null, { structure: 3, layout: 7 });

        expect(JSON.parse(fetcher.mock.calls[0][1].body)).toEqual({ scope: 'canvas', structure_version: 3, layout_version: 7 });
        expect(result).toEqual({ outcome: 'conflict', conflict: 'layout_changed' });
    });
});
