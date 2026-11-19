import { routes } from '../routes';

export interface AdminEntry {
    key: string;
    label: string;
    href: () => string;
}

const registry: AdminEntry[] = [
    { key: 'users', label: 'shell.admin.users', href: () => routes.users() },
    { key: 'organisations', label: 'shell.admin.organisations', href: () => routes.organisations() },
    { key: 'owners', label: 'shell.admin.owners', href: () => routes.adminOwners() },
    { key: 'applications', label: 'shell.admin.applications', href: () => routes.adminApplications() },
    { key: 'audit_log', label: 'shell.admin.audit_log', href: () => routes.auditLog() },
];

export function registerAdminEntry(entry: AdminEntry, position = registry.length): void {
    registry.splice(position, 0, entry);
}

export function adminEntries(): AdminEntry[] {
    return [...registry];
}
