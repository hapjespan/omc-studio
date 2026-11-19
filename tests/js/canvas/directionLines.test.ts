import { describe, expect, it } from 'vitest';
import { splitDirectionLine } from '../../../resources/js/canvas/directionLines';

describe('splitDirectionLine', () => {
    it('splits the number, the bold title and the rest of a line', () => {
        expect(splitDirectionLine('1. Eén kindprofiel. Van opvang', { number: 3, bold: 16 })).toEqual({ number: '1. ', bold: 'Eén kindprofiel.', rest: ' Van opvang' });
    });

    it('leaves a line without a mark plain and clamps a mark that outgrew a truncated line', () => {
        expect(splitDirectionLine('Leren voor het leven')).toEqual({ number: '', bold: '', rest: 'Leren voor het leven' });
        expect(splitDirectionLine('Kort…', { number: 0, bold: 40 })).toEqual({ number: '', bold: 'Kort…', rest: '' });
    });
});
