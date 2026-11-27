import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import SampleGuide from '../../../resources/js/components/SampleGuide.vue';
import { t } from '../../../resources/js/i18n';

const render = (group: string) => renderToString(createSSRApp({ render: () => h(SampleGuide, { group, url: `/samples/${group}.csv` }) }));

describe('SampleGuide', () => {
    it.each([
        ['cost_record', 13, 'decimale komma'],
        ['structure', 6, 'verplicht'],
        ['allocation', 4, 'decimale komma'],
    ])('shows the download link and one explanation per column for %s', async (group, count, phrase) => {
        const html = await render(group);

        expect(html).toContain(`href="/samples/${group}.csv"`);
        expect(html).toContain(t('csvx.sample_download'));
        expect(html.match(/<dt/g)).toHaveLength(count);
        expect(html).toContain(phrase);
        expect(html).toContain(`aria-describedby="sample-${group}"`);
        expect(html).toContain(`id="sample-${group}"`);
    });

    it('never puts a comment mark in front of an explanation', async () => {
        expect(await render('cost_record')).not.toContain('#code');
    });
});
