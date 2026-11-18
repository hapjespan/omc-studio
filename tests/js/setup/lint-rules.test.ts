import { ESLint } from 'eslint';
import { describe, expect, it } from 'vitest';

const eslint = new ESLint();

async function messages(code: string, filePath: string) {
    const [result] = await eslint.lintText(code, { filePath });
    return result.messages.map((m) => m.ruleId);
}

describe('lint rules', () => {
    it('rejects v-html in a component', async () => {
        const rules = await messages('<template><div v-html="x" /></template>\n<script setup lang="ts">const x = "";</script>\n', 'resources/js/pages/Fixture.vue');
        expect(rules).toContain('vue/no-v-html');
    });

    it('rejects localStorage and sessionStorage', async () => {
        expect(await messages('localStorage.setItem("a", "b");\n', 'resources/js/fixture.ts')).toContain('no-restricted-globals');
        expect(await messages('window.sessionStorage.getItem("a");\n', 'resources/js/fixture.ts')).toContain('no-restricted-properties');
    });

    it('allows storage in the one history key file', async () => {
        expect(await messages('sessionStorage.getItem("a");\n', 'resources/js/support/history-key.ts')).toEqual([]);
    });
});
