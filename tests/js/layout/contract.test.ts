import { readdirSync, readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from './fakeMeasurer';
import { emptyInput } from './support';

describe('computeCanvasLayout contract', () => {
    it('returns the eight Areas for an empty Canvas', () => {
        const result = computeCanvasLayout(emptyInput(), fakeMeasurer);
        expect(result.blocks.map((block) => block.id)).toEqual([
            'direction',
            'steering',
            'governing',
            'actors',
            'channels',
            'value_propositions',
            'primary_activities',
            'supporting_activities',
        ]);
        expect(result.blocks.every((block) => block.kind === 'area')).toBe(true);
        expect(result.warnings).toEqual([]);
        expect(result.beyondArtboardCount).toBe(0);
    });

    it('keeps the engine free of DOM and Vue imports', () => {
        const dir = 'resources/js/layout';
        const files = readdirSync(dir).filter((file) => file.endsWith('.ts') && file !== 'browserMeasurer.ts');
        for (const file of files) {
            const source = readFileSync(`${dir}/${file}`, 'utf8');
            expect(source, file).not.toMatch(/from\s+['"]vue|from\s+['"][^'"]*\.vue['"]/);
            expect(source, file).not.toMatch(/\b(document|window|navigator|HTMLElement|fetch)\b/);
        }
    });
});
