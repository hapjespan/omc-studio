import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

describe('Dialog discard confirmation', () => {
    const source = readFileSync(fileURLToPath(new URL('../../../resources/js/components/overlays/Dialog.vue', import.meta.url)), 'utf8');

    it('takes a generated id instead of a fixed one', () => {
        expect(source).not.toContain('discard-title');
        expect(source).toContain('useId()');
    });
});
