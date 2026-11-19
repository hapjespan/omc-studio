import { describe, expect, it } from 'vitest';
import { isActive, registerTab, visibleTabs } from '../../../resources/js/shell/tabs';

const keys = (role: string, canvas: boolean) => visibleTabs(role, canvas).map((tab) => tab.key);

describe('tabs', () => {
    it('shows Canvas and, for an Administrator, Beheer when a Canvas is open', () => {
        expect(keys('administrator', true)).toEqual(['canvas', 'costs', 'allocation', 'versions', 'admin']);
    });

    it('shows Overzicht and, for an Administrator, Beheer without a Canvas', () => {
        expect(keys('administrator', false)).toEqual(['overview', 'admin']);
        expect(keys('reader', false)).toEqual(['overview']);
    });

    it('shows a Reader only Canvas with a Canvas open', () => {
        expect(keys('reader', true)).toEqual(['canvas', 'versions']);
    });

    it('hides Versies on a Template', () => {
        expect(visibleTabs('administrator', true, 'template').map((tab) => tab.key)).not.toContain('versions');
    });

    it('marks Versies and not Canvas on the versions address', () => {
        const [canvas, , , versions] = visibleTabs('administrator', true);

        expect(isActive(versions, '/canvases/4/versions')).toBe(true);
        expect(isActive(canvas, '/canvases/4/versions')).toBe(false);
        expect(isActive(versions, '/canvases/4/allocation')).toBe(false);
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

        expect(keys('administrator', true)).toEqual(['canvas', 'probe', 'costs', 'allocation', 'versions', 'admin']);
        expect(keys('reader', true)).toEqual(['canvas', 'versions']);
    });
});
