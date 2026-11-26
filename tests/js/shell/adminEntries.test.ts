import { describe, expect, it } from 'vitest';
import { adminEntries } from '../../../resources/js/shell/adminEntries';

describe('adminEntries', () => {
    it('lists Gebruikers, Organisaties, Eigenaren, Applicaties, Logboek, Systeemmeldingen, Voorbeelddata and Volledige export en import', () => {
        expect(adminEntries().map((entry) => entry.label)).toEqual(['shell.admin.users', 'shell.admin.organisations', 'shell.admin.owners', 'shell.admin.applications', 'shell.admin.audit_log', 'shell.admin.system_notices', 'shell.admin.sample_data', 'fullx.title']);
    });
});
