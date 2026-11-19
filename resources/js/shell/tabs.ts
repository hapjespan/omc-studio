import { routes } from '../routes';
import type { ShellCanvas } from './TopBar.vue';

export interface Tab {
    key: string;
    label: string;
    context: 'canvas' | 'none' | 'any';
    roles: string[];
    href: (canvas: ShellCanvas | null) => string;
    matches: string[];
}

const everyone = ['administrator', 'reader'];

const registry: Tab[] = [
    { key: 'canvas', label: 'shell.tab.canvas', context: 'canvas', roles: everyone, href: (canvas) => routes.canvas(canvas?.id ?? ''), matches: ['/canvases'] },
    { key: 'costs', label: 'shell.tab.costs', context: 'canvas', roles: ['administrator'], href: (canvas) => (canvas?.organisation ? routes.costRecords(canvas.organisation.id) : routes.home()), matches: ['/organisations'] },
    { key: 'overview', label: 'shell.tab.overview', context: 'none', roles: everyone, href: () => routes.home(), matches: ['/', '/overview'] },
    { key: 'admin', label: 'shell.tab.admin', context: 'any', roles: ['administrator'], href: () => routes.admin(), matches: ['/admin'] },
];

export function registerTab(tab: Tab, position = registry.length): void {
    registry.splice(position, 0, tab);
}

export function visibleTabs(role: string, hasCanvas: boolean): Tab[] {
    return registry.filter((tab) => tab.roles.includes(role) && (tab.context === 'any' || (tab.context === 'canvas') === hasCanvas));
}

export function isActive(tab: Tab, url: string): boolean {
    const path = url.split('?')[0];

    return tab.matches.some((match) => path === match || path.startsWith(`${match}/`));
}
