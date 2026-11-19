import { describe, expect, it } from 'vitest';
import { splitDirectionLines } from '../../../resources/js/canvas/directionLines';

describe('splitDirectionLines', () => {
    it('bolds the number and title of each goal across wrapped lines', () => {
        const lines = ['1. Eén kindprofiel.', 'Van opvang', '2. Groei meer', '3. Rust voor iedereen'];
        expect(splitDirectionLines(lines, [19, 13, 0])).toEqual([
            { bold: '1. Eén kindprofiel.', rest: '' },
            { bold: '', rest: 'Van opvang' },
            { bold: '2. Groei meer', rest: '' },
            { bold: '', rest: '3. Rust voor iedereen' },
        ]);
    });

    it('leaves the lines of a column without bold lengths plain', () => {
        expect(splitDirectionLines(['Leren voor het leven'], [])).toEqual([{ bold: '', rest: 'Leren voor het leven' }]);
    });
});
