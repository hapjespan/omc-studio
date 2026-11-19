import { describe, expect, it } from 'vitest';
import { isNavigationKey, navigate } from '../../../resources/js/canvas/treeNavigation';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { element, withElements } from '../layout/support';

const { blocks } = computeCanvasLayout(
    withElements([
        element('d1', 'primary_activities', 'domain', 'Een', null, 1),
        element('d2', 'primary_activities', 'domain', 'Twee', null, 2),
        element('c', 'primary_activities', 'cluster', 'Cluster', 'd1', 1),
        element('a1', 'primary_activities', 'activity', 'Eerste', 'c', 1),
        element('a2', 'primary_activities', 'activity', 'Tweede', 'c', 2),
        element('s', 'supporting_activities', 'domain', 'Steun'),
    ]),
    fakeMeasurer,
);

describe('navigate', () => {
    it('moves Down and Up between siblings of one parent and stops at the ends', () => {
        expect(navigate(blocks, 'd1', 'ArrowDown')).toBe('d2');
        expect(navigate(blocks, 'd2', 'ArrowUp')).toBe('d1');
        expect(navigate(blocks, 'd1', 'ArrowUp')).toBeNull();
        expect(navigate(blocks, 'd2', 'ArrowDown')).toBeNull();
        expect(navigate(blocks, 'a1', 'ArrowDown')).toBe('a2');
    });

    it('does not cross into another Area or parent', () => {
        expect(navigate(blocks, 'd2', 'ArrowDown')).toBeNull();
        expect(navigate(blocks, 'a2', 'ArrowDown')).toBeNull();
    });

    it('goes Right into the first child and Left to the parent', () => {
        expect(navigate(blocks, 'd1', 'ArrowRight')).toBe('c');
        expect(navigate(blocks, 'c', 'ArrowRight')).toBe('a1');
        expect(navigate(blocks, 'a1', 'ArrowRight')).toBeNull();
        expect(navigate(blocks, 'a1', 'ArrowLeft')).toBe('c');
        expect(navigate(blocks, 'c', 'ArrowLeft')).toBe('d1');
    });

    it('treats the Area as the parent of its Domains', () => {
        expect(navigate(blocks, 'd1', 'ArrowLeft')).toBe('primary_activities');
        expect(navigate(blocks, 'primary_activities', 'ArrowRight')).toBe('d1');
        expect(navigate(blocks, 'primary_activities', 'ArrowDown')).toBe('d1');
        expect(navigate(blocks, 'primary_activities', 'ArrowLeft')).toBeNull();
        expect(navigate(blocks, 'steering', 'ArrowRight')).toBeNull();
    });

    it('ignores an unknown id and recognises only the arrow keys', () => {
        expect(navigate(blocks, 'missing', 'ArrowDown')).toBeNull();
        expect(isNavigationKey('ArrowUp')).toBe(true);
        expect(isNavigationKey('Enter')).toBe(false);
    });
});
