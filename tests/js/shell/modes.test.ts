import { describe, expect, it } from 'vitest';
import { registerMode, visibleModes } from '../../../resources/js/shell/modes';

const open = { id: 1, name: 'A', kind: 'canvas', archived: false, organisation: { id: 1, name: 'O' } };
const keys = (role: string, canvas = open) => visibleModes(role, canvas).map((mode) => mode.key);

describe('modes', () => {
    it('offers an Administrator Invoeren, Tekenen and Bekijken', () => {
        expect(keys('administrator')).toEqual(['entry', 'drawing', 'view']);
    });

    it('offers a Reader Bekijken only', () => {
        expect(keys('reader')).toEqual(['view']);
    });

    it('lets a later delivery register a mode between them', () => {
        registerMode({ key: 'extra', label: 'shell.mode.extra', roles: ['administrator'], writes: true, onTemplate: false, href: (id) => `/canvases/${id}/extra` }, 2);

        expect(keys('administrator')).toEqual(['entry', 'drawing', 'extra', 'view']);
        expect(keys('reader')).toEqual(['view']);
    });

    it('offers an archived Canvas Bekijken only, even to an Administrator', () => {
        expect(keys('administrator', { ...open, archived: true })).toEqual(['view']);
    });

    it('offers a Template Invoeren only', () => {
        expect(keys('administrator', { ...open, kind: 'template', organisation: null as never })).toEqual(['entry']);
    });
});
