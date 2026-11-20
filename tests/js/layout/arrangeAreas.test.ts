import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { arrangeAreas } from '../../../resources/js/layout/arrangeAreas';
import { layoutDirection } from '../../../resources/js/layout/direction';
import { fakeMeasurer } from './fakeMeasurer';
import { emptyInput } from './support';

const labels = { mission: 'Missie', vision: 'Visie', goals: 'Strategische doelen' };

describe('arrangeAreas', () => {
    const frames = arrangeAreas({});

    it('puts the Direction band across the top at 180 pixels', () => {
        expect(frames.direction).toEqual({ x: 0, y: 0, width: 1680, height: 180 });
    });

    it('puts Richten and Beheersen next to each other under the band', () => {
        expect(frames.steering.y).toBe(frames.governing.y);
        expect(frames.steering.x + frames.steering.width).toBeLessThan(frames.governing.x);
        expect(frames.steering.y).toBeGreaterThanOrEqual(180);
    });

    it('puts Actors, Channels and Value propositions as three left columns', () => {
        const { actors, channels, value_propositions: value } = frames;
        expect(new Set([actors.y, channels.y, value.y]).size).toBe(1);
        expect(actors.x).toBeLessThan(channels.x);
        expect(channels.x).toBeLessThan(value.x);
        expect(value.x + value.width).toBeLessThan(frames.primary_activities.x);
    });

    it('stacks Primary above Supporting on the right and ends inside the Artboard', () => {
        const { primary_activities: primary, supporting_activities: supporting } = frames;
        expect(primary.x).toBe(supporting.x);
        expect(supporting.y).toBeGreaterThan(primary.y + primary.height - 1);
        expect(supporting.y + supporting.height).toBeLessThanOrEqual(1188);
    });

    it('lets an Area grow with its content and pushes the rows below down', () => {
        const grown = arrangeAreas({ steering: 400, supporting_activities: 700 });
        expect(grown.steering.height).toBe(400);
        expect(grown.primary_activities.y).toBeGreaterThan(frames.primary_activities.y);
        expect(grown.supporting_activities.height).toBe(700);
        expect(grown.actors.height).toBeGreaterThanOrEqual(grown.primary_activities.height + grown.supporting_activities.height);
    });
});

describe('arrangeAreas asymmetric needs', () => {
    it('lets a tall Supporting Area take the room of an empty Primary Area', () => {
        const frames = arrangeAreas({ primary_activities: 52, supporting_activities: 600 });
        expect(frames.supporting_activities.y + frames.supporting_activities.height).toBeLessThanOrEqual(1188);
        expect(frames.supporting_activities.height).toBeGreaterThanOrEqual(600);
    });

    it('lets a tall Primary Area take the room of an empty Supporting Area', () => {
        const frames = arrangeAreas({ primary_activities: 600, supporting_activities: 52 });
        expect(frames.supporting_activities.y + frames.supporting_activities.height).toBeLessThanOrEqual(1188);
    });
});

describe('Direction band', () => {
    it('lays four columns side by side over the full width', () => {
        const columns = layoutDirection([], labels, fakeMeasurer);
        expect(columns.map((column) => column.key)).toEqual(['logo', 'mission', 'vision', 'goals']);
        expect(columns.at(-1)!.x + columns.at(-1)!.width).toBe(1680);
        expect(columns.every((column) => !column.truncated)).toBe(true);
    });

    it('cuts text that does not fit with an ellipsis, flags the column and warns', () => {
        const input = {
            ...emptyInput(),
            statements: [
                { kind: 'mission' as const, title: null, body: 'woord '.repeat(400), position: 1 },
                { kind: 'vision' as const, title: null, body: 'Kort', position: 1 },
            ],
        };
        const result = computeCanvasLayout(input, fakeMeasurer);
        const mission = result.direction.find((column) => column.key === 'mission')!;
        expect(mission.truncated).toBe(true);
        expect(mission.lines.at(-1)!.endsWith('…')).toBe(true);
        expect(result.direction.find((column) => column.key === 'vision')!.truncated).toBe(false);
        expect(result.warnings).toEqual([{ kind: 'directionTextCut', column: 'mission' }]);
    });

    it('numbers the goals in goal order, joins title and body without a colon and marks the title', () => {
        const columns = layoutDirection(
            [
                { kind: 'goal', title: 'Tweede', body: 'b', position: 2 },
                { kind: 'goal', title: 'Eerste', body: 'a', position: 1 },
            ],
            labels,
            fakeMeasurer,
        );
        expect(columns[3].lines).toEqual(['1. Eerste. a', '2. Tweede. b']);
        expect(columns[3].emphasis).toEqual([{ line: 0, from: 3, to: 10 }, { line: 1, from: 3, to: 10 }]);
    });
});

describe('computeCanvasLayout arrangement', () => {
    it('gives every Area block the frame of the arrangement', () => {
        const result = computeCanvasLayout(emptyInput(), fakeMeasurer);
        const frames = arrangeAreas({});
        for (const block of result.blocks) {
            const frame = frames[block.areaKey];
            expect([block.x, block.y, block.width, block.drawnHeight]).toEqual([frame.x, frame.y, frame.width, frame.height]);
        }
    });
});
