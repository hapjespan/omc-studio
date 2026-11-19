<script setup lang="ts">
import { t } from '../i18n';

export interface ImportReportData {
    lines: { line: number; status: string; messages: string[] }[];
    totals: Record<'new' | 'updated' | 'unchanged' | 'errors', number>;
    creates?: Record<string, string[]>;
}

withDefaults(defineProps<{ report: ImportReportData; heading?: 'h2' | 'h3' }>(), { heading: 'h2' });

const totalKeys = ['new', 'updated', 'unchanged', 'errors'] as const;
</script>

<template>
    <section role="region" aria-labelledby="import-summary" class="mt-4">
        <component :is="heading" id="import-summary" class="text-[16px] font-semibold">{{ t('import.summary') }}</component>
        <table class="mt-2 text-[13px]">
            <caption class="sr-only">{{ t('import.summary') }}</caption>
            <thead><tr><th scope="col" class="pr-6 text-left">{{ t('import.col_status') }}</th><th scope="col" class="text-right">{{ t('import.col_count') }}</th></tr></thead>
            <tbody>
                <tr v-for="key in totalKeys" :key="key"><th scope="row" class="pr-6 text-left font-normal">{{ t(`import.total_${key}`) }}</th><td class="text-right">{{ report.totals[key] }}</td></tr>
            </tbody>
        </table>
    </section>
    <template v-for="(names, kind) in report.creates ?? {}" :key="kind">
        <section role="region" v-if="names.length" :aria-labelledby="`import-creates-${kind}`" class="mt-4">
            <component :is="heading" :id="`import-creates-${kind}`" class="text-[16px] font-semibold">{{ t('import.creates') }}: {{ t(`import.${kind}`) }}</component>
            <ul class="mt-2 list-disc pl-5 text-[13px]"><li v-for="name in names" :key="name">{{ name }}</li></ul>
        </section>
    </template>
    <section role="region" aria-labelledby="import-lines" class="mt-4">
        <component :is="heading" id="import-lines" class="text-[16px] font-semibold">{{ t('import.lines') }}</component>
        <table class="mt-2 w-full text-[13px]">
            <caption class="sr-only">{{ t('import.lines') }}</caption>
            <thead><tr><th scope="col" class="text-left">{{ t('import.col_line') }}</th><th scope="col" class="text-left">{{ t('import.col_status') }}</th><th scope="col" class="text-left">{{ t('import.col_messages') }}</th></tr></thead>
            <tbody>
                <tr v-for="row in report.lines" :key="row.line" class="align-top">
                    <th scope="row" class="text-left font-normal">{{ row.line }}</th>
                    <td>{{ t(`import.status_${row.status}`) }}</td>
                    <td><p v-for="message in row.messages" :key="message" class="text-[#A12A1E]">{{ message }}</p></td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
