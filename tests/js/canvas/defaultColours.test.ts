import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import contrast from '../../fixtures/contrast.json';
import { contrastRatio } from '../../../resources/js/colour';
import { computeCanvasLayout } from '../../../resources/js/layout';
import type { AreaKey, DrawnBlock } from '../../../resources/js/layout';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { element, withElements } from '../layout/support';

const contract = readFileSync('specs/001-omc-studio-v1/design-contract.md', 'utf8');
const hexes = (row: string): string[] => [...(contract.split('\n').find((line) => line.startsWith(`| ${row}`)) ?? '').matchAll(/#[0-9A-F]{6}/g)].map((match) => match[0]);
const blend = (over: string, ink: string, share: number): string =>
    '#' + [1, 3, 5].map((i) => Math.round(parseInt(over.slice(i, i + 2), 16) * (1 - share) + parseInt(ink.slice(i, i + 2), 16) * share).toString(16).padStart(2, '0')).join('').toUpperCase();

const INK = hexes('Ink: primary text')[0];
const coloured: [AreaKey, string][] = [
    ['steering', 'Richten, Beheersen'],
    ['governing', 'Richten, Beheersen'],
    ['primary_activities', 'Primaire activiteiten'],
    ['supporting_activities', 'Ondersteunende activiteiten'],
];

const drawn = computeCanvasLayout(
    withElements(
        coloured.flatMap(([area]) => [
            element(`${area}-d`, area, 'domain', 'Domein'),
            element(`${area}-c`, area, 'cluster', 'Cluster', `${area}-d`),
            element(`${area}-a`, area, 'activity', 'Activiteit', `${area}-c`),
        ]),
    ),
    fakeMeasurer,
).blocks;
const find = (id: string): DrawnBlock => drawn.find((block) => block.id === id)!;

describe('Default colours of a new Canvas against the design contract', () => {
    it.each(coloured)('%s: Domain fill, Cluster block and Activity match the contract', (area, row) => {
        const [domainFill] = hexes(row);
        expect(find(`${area}-d`).style.fill).toBe(domainFill);
        expect(find(`${area}-c`).style.fill).toBe(blend(domainFill, INK, 0.06));
        expect(find(`${area}-a`).style.fill).toBe('#FFFFFF');
        expect(find(`${area}-a`).style.border).toBe('#DCDFE2');
    });

    it('draws the Direction band in ink with white text and the contract label colour', () => {
        const band = find('direction');
        expect(band.style.fill).toBe(hexes('Direction band')[0]);
        expect(band.style.textColour).toBe('#FFFFFF');
        expect(band.style.labelColour).toBe(hexes('Direction band')[1]);
    });

    it('draws the list Areas without a Domain fill and the Area containers white with a hairline', () => {
        const lists = computeCanvasLayout(withElements(['actors', 'channels', 'value_propositions'].map((area) => element(area + '-g', area as AreaKey, 'domain', 'Groep'))), fakeMeasurer).blocks;
        for (const area of ['actors', 'channels', 'value_propositions']) {
            expect(lists.find((block) => block.id === `${area}-g`)?.style.fill).toBeNull();
            expect(lists.find((block) => block.id === area)?.style).toMatchObject({ fill: '#FFFFFF', border: '#DCDFE2', radius: 10 });
        }
    });

    it('keeps every drawn text colour at the minimum contrast on its fill', () => {
        const filled = [...drawn, ...computeCanvasLayout(withElements([]), fakeMeasurer).blocks].filter((block) => block.style.fill !== null);
        expect(filled.length).toBeGreaterThan(10);
        for (const block of filled) expect(contrastRatio(block.style.fill as string, block.style.textColour), block.id).toBeGreaterThanOrEqual(contrast.minimumRatio);
    });
});
