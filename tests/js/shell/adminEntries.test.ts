import { describe, expect, it } from 'vitest';
import { adminEntries, registerAdminEntry } from '../../../resources/js/shell/adminEntries';

describe('adminEntries', () => {
    it('lists Gebruikers, Organisaties and Logboek in D1 and nothing of a later delivery', () => {
        expect(adminEntries().map((entry) => entry.label)).toEqual(['shell.admin.users', 'shell.admin.organisations', 'shell.admin.audit_log']);
    });

    it('lets a later delivery register an entry', () => {
        registerAdminEntry({ key: 'probe', label: 'shell.admin.users', href: () => '/probe' });

        expect(adminEntries().map((entry) => entry.key)).toContain('probe');
    });
});
