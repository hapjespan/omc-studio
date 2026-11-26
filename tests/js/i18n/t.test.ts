import { describe, expect, it } from 'vitest';
import { t } from '../../../resources/js/i18n';

describe('t', () => {
    it('returns the message of a key', () => {
        expect(t('app.title')).toBe('OMC Studio');
    });

    it('interpolates parameters', () => {
        expect(t('setup.greeting', { name: 'Sam', count: 3 })).toBe('Hallo Sam, je hebt 3 berichten');
    });

    it('fails on a missing key instead of rendering the key', () => {
        expect(() => t('does.not.exist')).toThrow('does.not.exist');
    });
});
