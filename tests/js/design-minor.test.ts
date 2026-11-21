import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const read = (path: string): string => readFileSync(path, 'utf8');

const sources = (directory: string): string[] =>
    readdirSync(directory).flatMap((name) => {
        const path = join(directory, name);

        return statSync(path).isDirectory() ? sources(path) : /\.vue$/.test(name) ? [path] : [];
    });

const PAGE_TITLES = [
    'resources/js/pages/Versions/Index.vue',
    'resources/js/pages/Admin/Exchange.vue',
    'resources/js/pages/Admin/SampleData.vue',
    'resources/js/pages/Admin/SystemNotices.vue',
    'resources/js/pages/Finance/CostRecords.vue',
    'resources/js/pages/Finance/CostRecordImport.vue',
    'resources/js/components/list/OrganisationEntries.vue',
];

describe('typography and elevation tokens', () => {
    const css = read('resources/css/app.css');

    it.each(['text-body', 'text-small', 'text-hint', 'text-type', 'text-label', 'text-card-title', 'text-page-title', 'text-amount', 'text-figure', 'shadow-float'])('defines --%s in the theme', (token) => {
        expect(css).toMatch(new RegExp(`--${token}:`));
    });

    it('keeps the off-scale 15 and 17 pixel sizes out of the wordmark and the quick entry title', () => {
        expect(read('resources/js/pages/Auth/AuthPanel.vue')).not.toContain('text-[17px]');
        expect(read('resources/js/shell/TopBar.vue')).not.toContain('text-[15px]');
        expect(read('resources/js/pages/Canvas/QuickEntry.vue')).not.toMatch(/<h3[^>]*text-\[15px\]/);
    });

    it.each(PAGE_TITLES)('sets the page title of %s in the page title size', (path) => {
        const title = /<h1[^>]*>/.exec(read(path))?.[0] ?? '';

        expect(title).toContain('text-page-title');
        expect(title).not.toContain('text-[20px]');
    });

    it('draws the wordmark in one component for the sign-in panel and the top bar', () => {
        expect(read('resources/js/pages/Auth/AuthPanel.vue')).toContain('Wordmark');
        expect(read('resources/js/shell/TopBar.vue')).toContain('Wordmark');
    });

    it('uses the one float shadow on every floating surface', () => {
        const floating = ['components/ui/ActionMenu.vue', 'components/fields/SelectField.vue', 'shell/SessionTimer.vue', 'pages/Drawing/DragLabel.vue', 'pages/Drawing/DirectionBand.vue', 'canvas/HoverContent.vue'];

        for (const path of floating) expect(read(`resources/js/${path}`), path).toContain('shadow-float');
        for (const path of sources('resources/js')) expect(read(path), path).not.toMatch(/shadow-lg|shadow-\[0_1px_3px/);
    });
});

describe('colours and states that come from tokens', () => {
    it('has no hard-coded hex colour in the element figures and the costs line', () => {
        for (const path of ['resources/js/pages/Canvas/ElementFigures.vue', 'resources/js/pages/Canvas/ElementCostsLine.vue']) expect(read(path), path).not.toMatch(/#[0-9A-Fa-f]{6}/);
    });

    it.each([
        ['resources/js/pages/Admin/AuditLog.vue', "t('audit_log.empty')"],
        ['resources/js/components/list/ListView.vue', 'list.empty'],
        ['resources/js/pages/Overview/Templates.vue', 'templates.empty'],
        ['resources/js/pages/Canvas/DirectionEntry.vue', 'direction_entry.empty_'],
    ])('sets the empty sentence of %s in the muted colour', (path, key) => {
        const line = read(path).split('\n').find((candidate) => candidate.includes(key)) ?? '';

        expect(line).toContain('text-muted');
        expect(line).not.toContain('text-secondary');
    });

    it.each([
        ['resources/js/components/list/ListView.vue', "t('list.delete')} ${row.name}"],
        ['resources/js/pages/Finance/FiscalYearsPanel.vue', "t('list.delete')} ${item.year}"],
    ])('offers the row Verwijderen of %s as a danger button', (path, marker) => {
        const line = read(path).split('\n').find((candidate) => candidate.includes(marker)) ?? '';

        expect(line).toContain('variant="danger"');
    });
});

describe('the modality badge', () => {
    it.each(['resources/js/canvas/ViewDetail.vue', 'resources/js/canvas/overlay/OverlayLegend.vue', 'resources/js/pages/Canvas/FigureSidePanel.vue'])('is the ModalityMark component in %s', (path) => {
        expect(read(path)).toContain('<ModalityMark');
        expect(read(path)).not.toMatch(/h-4 w-4[^"]*text-white/);
    });

    it('is drawn from the shared class in the block and the interface no longer shares the component name', () => {
        expect(read('resources/js/canvas/Block.vue')).toContain('MODALITY_MARK_CLASS');
        expect(read('resources/js/canvas/Block.vue')).not.toMatch(/h-4 w-4[^"]*text-white/);
        expect(read('resources/js/canvas/CanvasView.vue')).toContain('export interface ModalityColour');
        expect(read('resources/js/canvas/CanvasView.vue')).not.toContain('interface ModalityMark');
    });
});

describe('the action menus', () => {
    it('builds CardMenu and UserMenu on ActionMenu instead of their own dropdown markup', () => {
        for (const path of ['resources/js/canvas/CardMenu.vue', 'resources/js/shell/UserMenu.vue']) {
            expect(read(path), path).toContain('<ActionMenu');
            expect(read(path), path).not.toContain('DropdownMenuContent');
        }
    });

    it('gives ActionMenu a trigger slot and an optional trigger label', () => {
        const source = read('resources/js/components/ui/ActionMenu.vue');

        expect(source).toContain('name="trigger"');
        expect(source).toContain('triggerLabel');
    });
});

describe('the notices', () => {
    it.each([
        ['resources/js/canvas/OverflowNotice.vue', 'data-overflow-notice'],
        ['resources/js/pages/Canvas/View.vue', 'data-figures-message'],
        ['resources/js/pages/Canvas/SnapshotView.vue', 'data-figures-message'],
        ['resources/js/pages/Finance/Allocation.vue', 'allocation_page.finalised'],
    ])('are Callout components in %s', (path, marker) => {
        const line = read(path).split('\n').find((candidate) => candidate.includes(marker)) ?? '';

        expect(line).toContain('<Callout');
        expect(line).not.toMatch(/rounded-(md|control) border/);
    });
});

describe('the form controls', () => {
    it('uses NativeSelect, FileField and CheckBox instead of raw controls', () => {
        expect(read('resources/js/pages/Admin/AuditLog.vue')).not.toMatch(/<select\b/);
        expect(read('resources/js/pages/Admin/Organisations.vue')).not.toContain('type="file"');
        expect(read('resources/js/canvas/ExportMenu.vue')).not.toContain('type="checkbox"');
        expect(read('resources/js/canvas/ExportMenu.vue')).toContain('<CheckBox');
    });
});

describe('the discard confirmation', () => {
    it.each(['resources/js/shell/DetailPanel.vue', 'resources/js/components/overlays/Dialog.vue', 'resources/js/components/list/ListDock.vue'])('is the DiscardConfirm component in %s', (path) => {
        expect(read(path)).toContain('<DiscardConfirm');
        expect(read(path)).not.toContain('role="alertdialog"');
    });
});
