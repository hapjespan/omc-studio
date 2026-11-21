<script setup lang="ts">
import { computed } from 'vue';
import { t } from '../i18n';
import ReportTable from './ui/ReportTable.vue';

export interface CountsReportData {
    errors: { location: string; message: string }[];
    count: number;
    totals: Record<string, number>;
}

const props = withDefaults(defineProps<{ prefix: string; id: string; keys: readonly string[]; report: CountsReportData; heading?: 'h2' | 'h3' }>(), { heading: 'h2' });
const totalRows = computed(() => [...props.keys.map((key) => ({ key, label: t(`${props.prefix}.total_${key}`), count: props.report.totals[key] })), { key: 'errors', label: t(`${props.prefix}.total_errors`), count: props.report.count }]);
</script>

<template>
    <section role="region" :aria-labelledby="`${id}-summary`" >
        <component :is="heading" :id="`${id}-summary`" class="font-display text-[16px] font-semibold">{{ t(`${prefix}.summary`) }}</component>
        <ReportTable class="mt-3" :caption="t(`${prefix}.summary`)" :columns="[{ key: 'label', label: t(`${prefix}.col_kind`) }, { key: 'count', label: t(`${prefix}.col_count`), align: 'right' }]" :rows="totalRows" row-header="label">
            <template #cell-count="{ row }"><span :class="['font-semibold', row.key === 'errors' ? (report.count ? 'text-danger' : 'text-success') : '']">{{ row.count }}</span></template>
        </ReportTable>
        <ReportTable v-if="report.errors.length" class="mt-4" :caption="t(`${prefix}.errors`)" :columns="[{ key: 'location', label: t(`${prefix}.col_location`) }, { key: 'message', label: t(`${prefix}.col_message`) }]" :rows="report.errors">
            <template #cell-location="{ row }"><span class="text-secondary">{{ row.location }}</span></template>
            <template #cell-message="{ row }"><span class="text-danger">{{ row.message }}</span></template>
        </ReportTable>
    </section>
</template>
