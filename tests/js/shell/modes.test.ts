import { describe, expect, it } from 'vitest';
import { registerMode, visibleModes } from '../../../resources/js/shell/modes';

const keys = (role: string) => visibleModes(role).map((mode) => mode.key);

describe('modes', () => {
    it('offers an Administrator Invoeren and Bekijken in D1', () => {
        expect(keys('administrator')).toEqual(['entry', 'view']);
    });

    it('offers a Reader Bekijken only', () => {
        expect(keys('reader')).toEqual(['view']);
    });

    it('lets a later delivery register Tekenen between them', () => {
        registerMode({ key: 'draw', label: 'shell.mode.draw', roles: ['administrator'], href: (id) => `/canvases/${id}/draw` }, 1);

        expect(keys('administrator')).toEqual(['entry', 'draw', 'view']);
        expect(keys('reader')).toEqual(['view']);
    });
});
