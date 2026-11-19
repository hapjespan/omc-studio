import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from './fakeMeasurer';
import { element, withElements } from './support';
import type { LayoutElement } from '../../../resources/js/layout/types';

const run = (elements: LayoutElement[]) => computeCanvasLayout(withElements(elements), fakeMeasurer);

describe('Warnings', () => {
    it('names an empty Cluster and a Domain without Activities', () => {
        const { warnings } = run([
            element('d1', 'primary_activities', 'domain', 'Onderwijs', null, 1),
            element('k', 'primary_activities', 'cluster', 'Peuters', 'd1', 1),
            element('a', 'primary_activities', 'activity', 'Les', 'd1', 2),
            element('d2', 'supporting_activities', 'domain', 'Facilitair', null, 1),
        ]);
        expect(warnings).toEqual([
            { kind: 'emptyCluster', id: 'k', name: 'Peuters', area: 'primary_activities' },
            { kind: 'domainWithoutActivities', id: 'd2', name: 'Facilitair', area: 'supporting_activities' },
        ]);
    });

    it('counts Activities inside Clusters', () => {
        const { warnings } = run([
            element('d', 'primary_activities', 'domain', 'Onderwijs'),
            element('k', 'primary_activities', 'cluster', 'Groep', 'd'),
            element('a', 'primary_activities', 'activity', 'Les', 'k'),
        ]);
        expect(warnings).toEqual([]);
    });

    it('shows both Warnings for a Domain with only empty Clusters', () => {
        const { warnings } = run([element('d', 'steering', 'domain', 'Leeg'), element('k', 'steering', 'cluster', 'Kluster', 'd')]);
        expect(warnings.map((warning) => warning.kind).sort()).toEqual(['domainWithoutActivities', 'emptyCluster']);
    });

    it('warns about a Group without Items in a list Area', () => {
        expect(run([element('g', 'channels', 'domain', 'Web')]).warnings).toEqual([{ kind: 'domainWithoutActivities', id: 'g', name: 'Web', area: 'channels' }]);
    });

    it('raises no Warning for the Unknown allocation Domain alone', () => {
        expect(run([element('u', 'supporting_activities', 'domain', 'Onbekende allocatie', null, 1, { unknownAllocation: true })]).warnings).toEqual([]);
    });

    it('warns and counts Domains partly or wholly below the Artboard', () => {
        const elements: LayoutElement[] = [];
        for (let i = 0; i < 30; i++) {
            elements.push(element(`d${i}`, 'supporting_activities', 'domain', `Domein ${i}`, null, i + 1));
            elements.push(element(`a${i}`, 'supporting_activities', 'activity', 'woord '.repeat(10), `d${i}`, 1));
        }
        const result = run(elements);
        const beyond = result.blocks.filter((block) => block.kind === 'domain' && block.y + block.drawnHeight > 1188);
        expect(beyond.length).toBeGreaterThan(0);
        expect(beyond.length).toBeLessThan(30);
        expect(result.beyondArtboardCount).toBe(beyond.length);
        expect(result.warnings.filter((warning) => warning.kind === 'beyondArtboard').map((warning) => 'id' in warning && warning.id)).toEqual(beyond.map((block) => block.id));
    });

    it('reports a fitting Canvas with a count of zero', () => {
        expect(run([element('d', 'steering', 'domain', 'Klein'), element('a', 'steering', 'activity', 'Taak', 'd')]).beyondArtboardCount).toBe(0);
    });
});
