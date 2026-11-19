import { routes } from '../routes';

export interface Mode {
    key: string;
    label: string;
    roles: string[];
    href: (canvas: number | string) => string;
}

const registry: Mode[] = [
    { key: 'entry', label: 'shell.mode.entry', roles: ['administrator'], href: (canvas) => routes.entry(canvas) },
    { key: 'view', label: 'shell.mode.view', roles: ['administrator', 'reader'], href: (canvas) => routes.view(canvas) },
];

export function registerMode(mode: Mode, position = registry.length): void {
    registry.splice(position, 0, mode);
}

export function visibleModes(role: string): Mode[] {
    return registry.filter((mode) => mode.roles.includes(role));
}
