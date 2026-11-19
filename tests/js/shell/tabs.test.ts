import { describe, expect, it } from 'vitest';
import { isActive, registerTab, visibleTabs } from '../../../resources/js/shell/tabs';

const keys = (role: string, canvas: boolean) => visibleTabs(role, canvas).map((tab) => tab.key);

describe('tabs', () => {
    it('shows Canvas and, for an Administrator, Beheer when a Canvas is open', () => {
        expect(keys('administrator', true)).toEqual(['canvas', 'admin']);
    });

    it('shows Overzicht and, for an Administrator, Beheer without a Canvas', () => {
        expect(keys('administrator', false)).toEqual(['overview', 'admin']);
        expect(keys('reader', false)).toEqual(['overview']);
    });

    it('shows a Reader only Canvas with a Canvas open', () => {
        expect(keys('reader', true)).toEqual(['canvas']);
    });

    it('registers no tab of a later delivery in D1', () => {
        const all = [...keys('administrator', true), ...keys('administrator', false)];

        expect(all).not.toContain('costs');
        expect(all).not.toContain('allocate');
        expect(all).not.toContain('versions');
    });

    it('marks the tab whose address prefix matches the current address', () => {
        const [overview, admin] = visibleTabs('administrator', false);

        expect(isActive(overview, '/')).toBe(true);
        expect(isActive(overview, '/admin')).toBe(false);
        expect(isActive(admin, '/admin/users?page=2')).toBe(true);
        expect(isActive(admin, '/administration')).toBe(false);
    });

    it('lets a later delivery register a tab', () => {
        registerTab({ key: 'probe', label: 'shell.tab.overview', context: 'canvas', roles: ['administrator'], href: () => '/probe', matches: ['/probe'] }, 1);

        expect(keys('administrator', true)).toEqual(['canvas', 'probe', 'admin']);
        expect(keys('reader', true)).toEqual(['canvas']);
    });
});
