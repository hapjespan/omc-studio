import { describe, expect, it } from 'vitest';
import { printWarning } from '../../../resources/js/canvas/printCanvas';

describe('printCanvas', () => {
    it('warns only when blocks fall beyond the Artboard', () => {
        expect(printWarning(0)).toBe('');
        expect(printWarning(3)).toBe('Niet alles past op het A3-tekenvlak: 3 blokken komen op een volgende pagina');
    });
});
