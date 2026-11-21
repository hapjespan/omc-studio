import { describe, expect, it } from 'vitest';
import widths from '../../fixtures/automatic-widths.json';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { automaticWidths } from '../../../resources/js/layout/automaticWidths';
import { readdirSync, readFileSync } from 'node:fs';
import { fakeMeasurer } from './fakeMeasurer';
import { element, withElements } from './support';

const automatic = widths.cases.filter((row) => !('areaWidths' in row) && !('elementWidths' in row));

describe('drawn widths against the automatic width rule', () => {
    it.each(automatic.map((row) => [row.name, row] as const))('%s', (_name, row) => {
        const elements = row.elements.map((entry, position) => element(entry.id, entry.area as never, entry.kind as never, entry.id, entry.parent, position + 1));
        const drawn = computeCanvasLayout(withElements(elements), fakeMeasurer);
        const expected = automaticWidths(elements);

        for (const [id, width] of Object.entries(expected)) expect(drawn.blocks.find((block) => block.id === id)?.width, id).toBe(width);
    });

    it('defines the cluster indent once', () => {
        const dir = 'resources/js/layout';
        const definitions = readdirSync(dir).filter((file) => file.endsWith('.ts') && /const CLUSTER_INDENT = /.test(readFileSync(`${dir}/${file}`, 'utf8')));

        expect(definitions).toEqual(['flowDomains.ts']);
    });
});
