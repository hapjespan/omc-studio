import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import ImportReport from '../../../resources/js/components/ImportReport.vue';

const totals = { new: 0, updated: 0, unchanged: 0, errors: 0 };
const render = (creates: Record<string, string[]>) => renderToString(createSSRApp({ render: () => h(ImportReport, { report: { lines: [], totals, creates } }) }));

describe('ImportReport', () => {
    it('renders no labelled section for an empty create list', async () => {
        const html = await render({ owners: [], applications: ['Portal'] });

        expect(html).not.toContain('import-creates-owners');
        expect(html).toContain('import-creates-applications');
    });
});
