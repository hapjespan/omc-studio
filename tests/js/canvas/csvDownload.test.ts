import { afterEach, describe, expect, it, vi } from 'vitest';
import { exportFull } from '../../../resources/js/canvas/csvDownload';

afterEach(() => vi.unstubAllGlobals());

const answer = (omitted: string | null): Response =>
    ({ ok: true, headers: { get: (name: string) => (name === 'X-Omitted-Snapshots' ? omitted : name === 'Content-Disposition' ? 'attachment; filename=full.json' : null) }, blob: async () => new Blob(['{}']) }) as unknown as Response;

const stub = (response: Response): void => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response));
    vi.stubGlobal('document', { createElement: () => ({ click: () => undefined }) });
    vi.stubGlobal('URL', { createObjectURL: () => 'blob:x', revokeObjectURL: () => undefined });
};

describe('exportFull', () => {
    it('names the damaged versions that were left out', async () => {
        stub(answer('2'));

        expect(await exportFull('/admin/export/full', 'full.json')).toBe('Niet opgenomen in de export: 2 beschadigde versies');
    });

    it('says that every version is included when none was left out', async () => {
        stub(answer('0'));

        expect(await exportFull('/admin/export/full', 'full.json')).toBe('Geëxporteerd: alle versies zijn opgenomen');
    });
});
