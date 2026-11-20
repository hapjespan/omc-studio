import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { createSSRApp, h } from 'vue';
import { vi } from 'vitest';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import AppButton from '../../../resources/js/components/ui/AppButton.vue';
import Callout from '../../../resources/js/components/ui/Callout.vue';
import Icon from '../../../resources/js/components/ui/Icon.vue';
import IconButton, { ICON_BUTTON_CLASS } from '../../../resources/js/components/ui/IconButton.vue';
import { ICON_PATHS } from '../../../resources/js/components/ui/icons';
import LogoMark from '../../../resources/js/components/ui/LogoMark.vue';
import Pill from '../../../resources/js/components/ui/Pill.vue';
import ActionMenu from '../../../resources/js/components/ui/ActionMenu.vue';
import CheckBox from '../../../resources/js/components/ui/CheckBox.vue';
import ChipSelect from '../../../resources/js/components/ui/ChipSelect.vue';
import Panel from '../../../resources/js/components/ui/Panel.vue';
import SegmentedControl from '../../../resources/js/components/ui/SegmentedControl.vue';
import FileField from '../../../resources/js/components/fields/FileField.vue';
import SearchField from '../../../resources/js/components/ui/SearchField.vue';

vi.mock('@inertiajs/vue3', () => ({ Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } }));

const render = (component: object, props: Record<string, unknown> = {}, slot = '') =>
    renderToString(createSSRApp({ render: () => h(component, props, slot === '' ? undefined : { default: () => slot }) }));

describe('AppButton', () => {
    it('fills the primary button with ink at 36 pixels high', async () => {
        const html = await render(AppButton, { variant: 'primary' }, 'Opslaan');

        expect(html).toContain('bg-ink');
        expect(html).toContain('min-h-control');
        expect(html).toContain('Opslaan');
    });

    it('draws the secondary button white with a border and the danger button in the danger colours', async () => {
        expect(await render(AppButton, {}, 'Annuleren')).toContain('border-edge');
        const danger = await render(AppButton, { variant: 'danger' }, 'Verwijderen');
        expect(danger).toContain('text-danger');
        expect(danger).toContain('border-danger-edge');
    });

    it('offers the smaller sizes of the contract', async () => {
        expect(await render(AppButton, { size: 'dense' }, 'Opslaan')).toContain('min-h-dense');
        expect(await render(AppButton, { size: 'small' }, 'Opslaan')).toContain('min-h-mark');
    });

    it('submits only when asked to', async () => {
        expect(await render(AppButton, {}, 'Opslaan')).toContain('type="button"');
        expect(await render(AppButton, { type: 'submit' }, 'Opslaan')).toContain('type="submit"');
    });
});

describe('IconButton', () => {
    it('keeps its accessible name and measures 28 by 28', async () => {
        const html = await render(IconButton, { label: 'Selectie omhoog', icon: 'chevron-up' });

        expect(html).toContain('aria-label="Selectie omhoog"');
        expect(html).toContain('h-mark');
        expect(html).toContain('w-mark');
        expect(html).toContain('<svg');
    });

    it('passes its disabled state on', async () => {
        expect(await render(IconButton, { label: 'Selectie omhoog', icon: 'chevron-up', disabled: true })).toContain('disabled');
    });
});

describe('Icon', () => {
    it('renders an inline decorative svg', async () => {
        const html = await render(Icon, { name: 'search' });

        expect(html).toContain('aria-hidden="true"');
        expect(html).toContain('focusable="false"');
        expect(html).toContain('currentColor');
    });
});

describe('LogoMark', () => {
    it('draws the grid in a rounded ink square without an outside asset', async () => {
        const html = await render(LogoMark);

        expect(html).toContain('<svg');
        expect(html).not.toContain('<img');
        expect(html).toContain('fill-ink');
    });
});

describe('Pill', () => {
    it('is round and carries its text', async () => {
        expect(await render(Pill, {}, 'Voorbeelddata')).toContain('rounded-full');
        expect(await render(Pill, { tone: 'warning' }, 'leeg')).toContain('text-warning');
    });
});

describe('Callout', () => {
    it('shows a warning with its icon and bold kind', async () => {
        const html = await render(Callout, { tone: 'warning', label: 'Leeg cluster:' }, 'Veiligheid');

        expect(html).toContain('bg-warning-soft');
        expect(html).toContain('<svg');
        expect(html).toContain('Leeg cluster:');
        expect(html).toContain('Veiligheid');
    });

    it('shows a success callout in the success colours', async () => {
        expect(await render(Callout, { tone: 'success' }, 'Totalen sluiten')).toContain('bg-success-soft');
    });
});

describe('SearchField', () => {
    it('ties a label to the field and shows the search icon', async () => {
        const html = await render(SearchField, { id: 'area-search', label: 'Zoek in deze area', placeholder: 'Zoek in deze area', modelValue: '' });

        expect(html).toContain('<label for="area-search"');
        expect(html).toContain('id="area-search"');
        expect(html).toContain('type="search"');
        expect(html).toContain('<svg');
    });
});

describe('the reorder arrows of the tree', () => {
    const source = readFileSync(fileURLToPath(new URL('../../../resources/js/pages/Canvas/ReorderButtons.vue', import.meta.url)), 'utf8');

    it('keeps the look of the icon button without an instance per row', () => {
        expect(source).toContain('ICON_BUTTON_CLASS');
        expect(source).not.toContain('<IconButton');
        expect(ICON_BUTTON_CLASS).toContain('h-mark');
    });

    it('inlines the same chevron paths as the icon component', () => {
        expect(source).toContain(`d="${ICON_PATHS['chevron-up']}"`);
        expect(source).toContain(`d="${ICON_PATHS['chevron-down']}"`);
    });
});

describe('AppButton as a link', () => {
    it('renders an anchor with the same classes and passes download, target and rel', async () => {
        const link = await render(AppButton, { href: '/export', download: 'export.json', target: '_blank', rel: 'noopener' }, 'Alles exporteren');
        const button = await render(AppButton, {}, 'Alles exporteren');

        expect(link).toMatch(/^<a /);
        expect(link).toContain('href="/export"');
        expect(link).toContain('download="export.json"');
        expect(link).toContain('rel="noopener"');
        expect(link).not.toContain('type=');
        expect(link.match(/class="([^"]*)"/)?.[1]).toBe(button.match(/class="([^"]*)"/)?.[1]);
    });
});

describe('IconButton size', () => {
    it('measures 36 by 36 on request', async () => {
        const html = await render(IconButton, { label: 'Verwijderen', icon: 'close', size: 36 });

        expect(html).toContain('h-control');
        expect(html).toContain('w-control');
        expect(html).not.toContain('h-mark');
    });
});

describe('danger tone', () => {
    it('colours a pill and a callout with the danger tokens', async () => {
        expect(await render(Pill, { tone: 'danger' }, 'fout')).toContain('bg-danger-soft');
        expect(await render(Pill, { tone: 'danger' }, 'fout')).toContain('text-danger');
        const callout = await render(Callout, { tone: 'danger' }, 'Mislukt');
        expect(callout).toContain('bg-danger-soft');
        expect(callout).toContain('<svg');
    });
});

describe('icons', () => {
    it('has the zoom icons', () => {
        expect(ICON_PATHS.minus).toBeTruthy();
        expect(ICON_PATHS.fit).toBeTruthy();
    });
});

describe('CheckBox', () => {
    it('keeps a 16 pixel box in a 24 pixel target with the styled class', async () => {
        const html = await render(CheckBox, { modelValue: false });

        expect(html).toContain('size-6');
        expect(html).toContain('class="check-target');
    });
});

describe('SegmentedControl', () => {
    const items = [{ key: 'a', label: 'Eerste', href: '/a' }, { key: 'b', label: 'Tweede', href: '/b' }];

    it('renders links with aria-current and a named group', async () => {
        const html = (await render(SegmentedControl, { items, active: 'b', label: 'Modus' })).replace(/<!--.*?-->/g, '');

        expect(html).toContain('role="group"');
        expect(html).toContain('aria-label="Modus"');
        expect(html).toContain('h-control');
        expect(html.match(/aria-current="page"/g)).toHaveLength(1);
        expect(html).toMatch(/aria-current="page"[^>]*>Tweede/);
    });

    it('renders buttons with aria-pressed when the items have no href', async () => {
        const html = (await render(SegmentedControl, { items: [{ key: 'a', label: 'Geen' }, { key: 'b', label: 'Modaliteit' }], active: 'a', label: 'Laag', role: 'toolbar' })).replace(/<!--.*?-->/g, '');

        expect(html).toContain('role="toolbar"');
        expect(html).toMatch(/aria-pressed="true"[^>]*>Geen/);
        expect(html).toMatch(/aria-pressed="false"[^>]*>Modaliteit/);
    });
});

describe('ChipSelect', () => {
    it('shows the label and a native select with its options', async () => {
        const html = await render(ChipSelect, { id: 'kind', label: 'Soort', name: 'Soort filter', modelValue: '', options: [{ value: '', label: 'Alle' }, { value: 'a', label: 'Eerste' }] });

        expect(html).toContain('<label for="kind"');
        expect(html).toContain('<select id="kind"');
        expect(html).toContain('aria-label="Soort filter"');
        expect(html).toContain('border-edge');
        expect(html).toContain('h-control');
        expect(html).toContain('Alle');
    });
});

describe('Panel', () => {
    it('is white with a hairline and padding 16 or 24', async () => {
        const small = await render(Panel, {}, 'Inhoud');
        const large = await render(Panel, { padding: 24, heading: 'Exporteren' }, 'Inhoud');

        expect(small).toContain('border-hairline');
        expect(small).toContain('rounded-panel');
        expect(small).toContain('p-4');
        expect(large).toContain('p-6');
        expect(large).toMatch(/<h2[^>]*font-display[^>]*>Exporteren<\/h2>/);
    });
});

describe('FileField', () => {
    it('ties label, hint and error to the native input and shows a Dutch button label', async () => {
        const html = await render(FileField, { id: 'upload', label: 'Bestand', hint: 'Alleen JSON', error: 'Kies een bestand' });

        expect(html).toContain('<label for="upload"');
        expect(html).toContain('type="file"');
        expect(html).toContain('aria-describedby="upload-hint upload-error"');
        expect(html).toContain('aria-invalid="true"');
        expect(html).toContain('Geen bestand gekozen');
    });
});

describe('ActionMenu', () => {
    it('renders one button with a chevron', async () => {
        const html = await render(ActionMenu, { label: 'Meer acties', items: [{ key: 'a', label: 'Eerste' }] });

        expect(html).toContain('Meer acties');
        expect(html).toContain('<svg');
        expect(html).toContain('aria-haspopup="menu"');
    });
});
