<script setup lang="ts">
import { computed } from 'vue';
import { t } from '../i18n';
import Pill from './ui/Pill.vue';
import ReportTable from './ui/ReportTable.vue';

export interface ImportReportData {
    lines: { line: number; status: string; messages: string[] }[];
    totals: Record<'new' | 'updated' | 'unchanged' | 'errors', number>;
    creates?: Record<string, string[]>;
}

const props = withDefaults(defineProps<{ report: ImportReportData; heading?: 'h2' | 'h3' }>(), { heading: 'h2' });

const totalKeys = ['new', 'updated', 'unchanged', 'errors'] as const;
const totalTone = (row: Record<string, unknown>, key: string): string | undefined => (key === 'count' ? ['font-semibold', row.key === 'errors' ? (props.report.totals.errors ? 'text-danger' : 'text-success') : ''].join(' ') : undefined);
const totalRows = computed(() => totalKeys.map((key) => ({ key, label: t(`import.total_${key}`), count: props.report.totals[key] })));
</script>

<template>
    <section role="region" aria-labelledby="import-summary" class="mt-6">
        <component :is="heading" id="import-summary" class="font-display text-[16px] font-semibold">{{ t('import.summary') }}</component>
        <ReportTable class="mt-3" :landmark="false" :cell-class="totalTone" :caption="t('import.summary')" :columns="[{ key: 'label', label: t('import.col_status') }, { key: 'count', label: t('import.col_count'), align: 'right' }]" :rows="totalRows" row-header="label">
        </ReportTable>
    </section>
    <template v-for="(names, kind) in report.creates ?? {}" :key="kind">
        <section v-if="names.length" role="region" :aria-labelledby="`import-creates-${kind}`" class="mt-6">
            <component :is="heading" :id="`import-creates-${kind}`" class="font-display text-[16px] font-semibold">{{ t('import.creates') }}: {{ t(`import.${kind}`) }}</component>
            <ul class="mt-3 list-disc rounded-card border border-hairline py-2.5 pl-8 pr-3 text-[13px]"><li v-for="name in names" :key="name">{{ name }}</li></ul>
        </section>
    </template>
    <section role="region" aria-labelledby="import-lines" class="mt-6">
        <component :is="heading" id="import-lines" class="font-display text-[16px] font-semibold">{{ t('import.lines') }}</component>
        <ReportTable class="mt-3" :landmark="false" :caption="t('import.lines')" :columns="[{ key: 'line', label: t('import.col_line') }, { key: 'status', label: t('import.col_status') }, { key: 'messages', label: t('import.col_messages') }]" :rows="report.lines" row-header="line">
            <template #cell-status="{ row }"><Pill :tone="row.status === 'error' ? 'warning' : row.status === 'new' ? 'success' : 'neutral'">{{ t(`import.status_${row.status}`) }}</Pill></template>
            <template #cell-messages="{ row }"><p v-for="message in row.messages as string[]" :key="message" class="text-danger">{{ message }}</p></template>
        </ReportTable>
    </section>
</template>
