import { computed } from 'vue';
import { describe, expect, it } from 'vitest';
import { SelectionHistory } from '../../../resources/js/pages/Drawing/selectionHistory';

const ids = Array.from({ length: 500 }, (_, index) => `e${index}`);

describe('selection history', () => {
    it('keeps the stored count equal to the distinct ids after repeated and multi selection', () => {
        const history = new SelectionHistory();
        for (let round = 0; round < 20; round++) {
            history.record(['e1', 'e2']);
            history.record(ids.slice(0, 100));
            history.record(['e499']);
        }
        expect(history.size).toBe(100 + 1);
    });

    it('lists only new elements that were never selected, and reacts to selection', () => {
        const history = new SelectionHistory();
        const fresh = computed(() => history.unseen(ids));
        expect(fresh.value).toHaveLength(500);
        history.record(['e3', 'e3']);
        history.record(['e4', 'e5']);
        history.record([]);
        expect(fresh.value).toHaveLength(497);
        expect(fresh.value).not.toContain('e4');
        expect(fresh.value).toContain('e6');
    });
});
