<script setup lang="ts">
import { t } from '../i18n';
import Pill from './ui/Pill.vue';

export interface ImportReportData {
    lines: { line: number; status: string; messages: string[] }[];
    totals: Record<'new' | 'updated' | 'unchanged' | 'errors', number>;
    creates?: Record<string, string[]>;
}

withDefaults(defineProps<{ report: ImportReportData; heading?: 'h2' | 'h3' }>(), { heading: 'h2' });

const totalKeys = ['new', 'updated', 'unchanged', 'errors'] as const;
</script>

<template>
    <section role="region" aria-labelledby="import-summary" class="mt-6">
        <component :is="heading" id="import-summary" class="font-display text-[16px] font-semibold">{{ t('import.summary') }}</component>
        <div class="mt-3 overflow-x-auto rounded-card border border-hairline">
            <table class="w-full border-collapse text-[13px]">
                <caption class="sr-only">{{ t('import.summary') }}</caption>
                <thead><tr><th scope="col" class="px-3 py-2.5 text-[11.5px] font-semibold text-secondary text-left">{{ t('import.col_status') }}</th><th scope="col" class="px-3 py-2.5 text-[11.5px] font-semibold text-secondary text-right">{{ t('import.col_count') }}</th></tr></thead>
                <tbody>
                    <tr v-for="key in totalKeys" :key="key" class="border-t border-hairline"><th scope="row" class="px-3 py-2.5 text-left font-normal">{{ t(`import.total_${key}`) }}</th><td :class="['px-3 py-2.5 text-right font-semibold', key === 'errors' ? (report.totals.errors ? 'text-danger' : 'text-success') : '']">{{ report.totals[key] }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>
    <template v-for="(names, kind) in report.creates ?? {}" :key="kind">
        <section v-if="names.length" role="region" :aria-labelledby="`import-creates-${kind}`" class="mt-6">
            <component :is="heading" :id="`import-creates-${kind}`" class="font-display text-[16px] font-semibold">{{ t('import.creates') }}: {{ t(`import.${kind}`) }}</component>
            <ul class="mt-3 list-disc rounded-card border border-hairline py-2.5 pl-8 pr-3 text-[13px]"><li v-for="name in names" :key="name">{{ name }}</li></ul>
        </section>
    </template>
    <section role="region" aria-labelledby="import-lines" class="mt-6">
        <component :is="heading" id="import-lines" class="font-display text-[16px] font-semibold">{{ t('import.lines') }}</component>
        <div class="mt-3 overflow-x-auto rounded-card border border-hairline">
            <table class="w-full border-collapse text-[13px]">
                <caption class="sr-only">{{ t('import.lines') }}</caption>
                <thead><tr><th scope="col" class="px-3 py-2.5 text-[11.5px] font-semibold text-secondary text-left">{{ t('import.col_line') }}</th><th scope="col" class="px-3 py-2.5 text-[11.5px] font-semibold text-secondary text-left">{{ t('import.col_status') }}</th><th scope="col" class="px-3 py-2.5 text-[11.5px] font-semibold text-secondary text-left">{{ t('import.col_messages') }}</th></tr></thead>
                <tbody>
                    <tr v-for="row in report.lines" :key="row.line" class="border-t border-hairline align-top">
                        <th scope="row" class="whitespace-nowrap px-3 py-2.5 text-left font-normal">{{ row.line }}</th>
                        <td class="whitespace-nowrap px-3 py-2.5"><Pill :tone="row.status === 'error' ? 'warning' : row.status === 'new' ? 'success' : 'neutral'">{{ t(`import.status_${row.status}`) }}</Pill></td>
                        <td class="px-3 py-2.5"><p v-for="message in row.messages" :key="message" class="text-danger">{{ message }}</p></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
