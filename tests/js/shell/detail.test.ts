import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { detail, discardDetail, openDetail, requestCloseDetail } from '../../../resources/js/shell/detail';

const opener = () => ({ focus: vi.fn() }) as unknown as HTMLElement;

beforeEach(() => {
    detail.open = false;
    detail.dirty = false;
    detail.confirming = false;
});

describe('detail panel', () => {
    it('opens on selection and returns focus to the selected row on close', async () => {
        const row = opener();

        openDetail('Rij 1', row);
        expect(detail.open).toBe(true);
        expect(detail.title).toBe('Rij 1');

        requestCloseDetail();
        await nextTick();
        expect(detail.open).toBe(false);
        expect(row.focus).toHaveBeenCalledOnce();
    });

    it('asks before closing when there is unsaved input and keeps it open', () => {
        openDetail('Rij 1', opener());
        detail.dirty = true;

        requestCloseDetail();

        expect(detail.open).toBe(true);
        expect(detail.confirming).toBe(true);
    });

    it('closes after the input is discarded and returns focus', async () => {
        const row = opener();
        openDetail('Rij 1', row);
        detail.dirty = true;
        requestCloseDetail();

        discardDetail();
        await nextTick();

        expect(detail.open).toBe(false);
        expect(detail.dirty).toBe(false);
        expect(detail.confirming).toBe(false);
        expect(row.focus).toHaveBeenCalledOnce();
    });
});
