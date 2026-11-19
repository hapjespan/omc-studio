import { describe, expect, it } from 'vitest';
import { exactName } from '../../../resources/js/pages/Overview/CanvasDialogs.vue';

describe('exactName', () => {
    it('accepts only the identical name', () => {
        expect(exactName('Model', 'Model')).toBe(true);
        expect(exactName('model', 'Model')).toBe(false);
        expect(exactName(' Model', 'Model')).toBe(false);
        expect(exactName('', '')).toBe(false);
    });
});
