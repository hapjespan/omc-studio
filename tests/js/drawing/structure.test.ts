import { readFileSync } from 'node:fs';
import { ESLint } from 'eslint';
import { describe, expect, it } from 'vitest';

const page = 'resources/js/pages/Drawing/Index.vue';

describe('Tekenen page structure', () => {
    it('keeps the page component under 250 lines', () => {
        expect(readFileSync(page, 'utf8').split('\n').length).toBeLessThan(250);
    });

    it('lints the page and its composables without a line over 240 characters', async () => {
        const results = await new ESLint().lintFiles([page, 'resources/js/pages/Drawing/use*.ts']);
        expect(results.flatMap((result) => result.messages.filter((message) => message.ruleId === 'max-len'))).toEqual([]);
    });

    it('rejects a line over 240 characters in the page', async () => {
        const [result] = await new ESLint().lintText(`<script setup lang="ts">\nconst x = '${'a'.repeat(260)}';\n</script>\n`, { filePath: page });
        expect(result.messages.map((message) => message.ruleId)).toContain('max-len');
    });
});
