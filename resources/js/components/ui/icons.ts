export const ICON_PATHS = {
    search: 'M11.2 11.2 14.2 14.2M11.5 7a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z',
    minus: 'M3.5 8h9',
    plus: 'M8 3.5v9M3.5 8h9',
    check: 'm3.3 8.4 3.1 3.1 6.3-6.6',
    'chevron-up': 'm4.2 9.8 3.8-3.8 3.8 3.8',
    'chevron-down': 'm4.2 6.2 3.8 3.8 3.8-3.8',
    'chevron-left': 'm9.8 4.2-3.8 3.8 3.8 3.8',
    'chevron-right': 'm6.2 4.2 3.8 3.8-3.8 3.8',
    warning: 'M8 2.4 14.8 13.6H1.2Zm0 4.4v3.3m0 2.1h.01',
    lock: 'M4.3 7.4h7.4v6.2H4.3Zm1.6 0V5.6a2.1 2.1 0 0 1 4.2 0v1.8',
    fit: 'M2.5 6V2.5H6m4 0h3.5V6m0 4v3.5H10m-4 0H2.5V10',
    undo: 'M5.5 3.5 2.5 6.5l3 3M2.7 6.5h6.3a3.5 3.5 0 0 1 0 7H6',
    redo: 'm10.5 3.5 3 3-3 3M13.3 6.5H7a3.5 3.5 0 0 0 0 7h3',
    close: 'm4.2 4.2 7.6 7.6m0-7.6-7.6 7.6',
} as const;

export type IconName = keyof typeof ICON_PATHS;
