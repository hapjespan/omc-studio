import { reactive } from 'vue';
import { routes } from '../routes';
import type { ShellCanvas } from './TopBar.vue';

export interface Mode {
    key: string;
    label: string;
    roles: string[];
    writes: boolean;
    onTemplate: boolean;
    href: (canvas: number | string) => string;
}

const registry: Mode[] = [
    { key: 'entry', label: 'shell.mode.entry', roles: ['administrator'], writes: true, onTemplate: true, href: (canvas) => routes.entry(canvas) },
    { key: 'drawing', label: 'shell.mode.drawing', roles: ['administrator'], writes: true, onTemplate: false, href: (canvas) => routes.drawing(canvas) },
    { key: 'view', label: 'shell.mode.view', roles: ['administrator', 'reader'], writes: false, onTemplate: false, href: (canvas) => routes.view(canvas) },
];

export const modeSwitchPlacement = reactive({ inPage: false });

export function visibleModes(role: string, canvas: ShellCanvas): Mode[] {
    const readOnly = role !== 'administrator' || canvas.archived;

    return registry.filter((mode) => mode.roles.includes(role) && (canvas.kind === 'template' ? mode.onTemplate : !readOnly || !mode.writes));
}
