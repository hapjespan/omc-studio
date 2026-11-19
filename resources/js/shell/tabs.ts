import { routes } from '../routes';
import type { ShellCanvas } from './TopBar.vue';

export interface Tab {
    key: string;
    label: string;
    context: 'canvas' | 'none' | 'any';
    roles: string[];
    href: (canvas: ShellCanvas | null) => string;
    matches: string[];
    exclude?: string[];
}

const everyone = ['administrator', 'reader'];

const registry: Tab[] = [
    { key: 'canvas', label: 'shell.tab.canvas', context: 'canvas', roles: everyone, href: (canvas) => routes.canvas(canvas?.id ?? ''), matches: ['/canvases'], exclude: ['/allocation', '/cost-records'] },
    { key: 'costs', label: 'shell.tab.costs', context: 'canvas', roles: ['administrator'], href: (canvas) => (canvas ? routes.canvasCostRecords(canvas.id) : routes.home()), matches: ['/organisations'] },
    { key: 'allocation', label: 'shell.tab.allocation', context: 'canvas', roles: ['administrator'], href: (canvas) => (canvas ? routes.allocation(canvas.id) : routes.home()), matches: [] },
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

    if (tab.key === 'costs' && /^\/canvases\/[^/]+\/cost-records/.test(path)) return true;
    if (tab.key === 'allocation') return /^\/canvases\/[^/]+\/allocation/.test(path);
    if (tab.exclude?.some((part) => path.includes(part))) return false;

    return tab.matches.some((match) => path === match || path.startsWith(`${match}/`));
}
