import { describe, expect, it } from 'vitest';
import { adminEntries, registerAdminEntry } from '../../../resources/js/shell/adminEntries';

describe('adminEntries', () => {
    it('lists Gebruikers, Organisaties, Eigenaren, Applicaties, Logboek and Systeemmeldingen', () => {
        expect(adminEntries().map((entry) => entry.label)).toEqual(['shell.admin.users', 'shell.admin.organisations', 'shell.admin.owners', 'shell.admin.applications', 'shell.admin.audit_log', 'shell.admin.system_notices', 'shell.admin.sample_data']);
    });

    it('lets a later delivery register an entry', () => {
        registerAdminEntry({ key: 'probe', label: 'shell.admin.users', href: () => '/probe' });

        expect(adminEntries().map((entry) => entry.key)).toContain('probe');
    });
});
