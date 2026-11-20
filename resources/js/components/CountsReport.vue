<script setup lang="ts">
import { t } from '../i18n';

export interface CountsReportData {
    errors: { location: string; message: string }[];
    count: number;
    totals: Record<string, number>;
}

withDefaults(defineProps<{ prefix: string; id: string; keys: readonly string[]; report: CountsReportData; heading?: 'h2' | 'h3' }>(), { heading: 'h2' });
</script>

<template>
    <section role="region" :aria-labelledby="`${id}-summary`" >
        <component :is="heading" :id="`${id}-summary`" class="font-display text-[16px] font-semibold">{{ t(`${prefix}.summary`) }}</component>
        <div class="mt-3 overflow-x-auto rounded-card border border-hairline">
            <table class="w-full border-collapse text-[13px]">
                <caption class="sr-only">{{ t(`${prefix}.summary`) }}</caption>
                <thead><tr><th scope="col" class="px-3 py-2.5 text-left text-[11.5px] font-semibold text-secondary">{{ t(`${prefix}.col_kind`) }}</th><th scope="col" class="px-3 py-2.5 text-right text-[11.5px] font-semibold text-secondary">{{ t(`${prefix}.col_count`) }}</th></tr></thead>
                <tbody>
                    <tr v-for="key in keys" :key="key" class="border-t border-hairline"><th scope="row" class="px-3 py-2.5 text-left font-normal">{{ t(`${prefix}.total_${key}`) }}</th><td class="px-3 py-2.5 text-right font-semibold">{{ report.totals[key] }}</td></tr>
                    <tr class="border-t border-hairline"><th scope="row" class="px-3 py-2.5 text-left font-normal">{{ t(`${prefix}.total_errors`) }}</th><td :class="['px-3 py-2.5 text-right font-semibold', report.count ? 'text-danger' : 'text-success']">{{ report.count }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="report.errors.length" class="mt-4 overflow-x-auto rounded-card border border-hairline">
            <table class="w-full border-collapse text-[13px]">
                <caption class="sr-only">{{ t(`${prefix}.errors`) }}</caption>
                <thead><tr><th scope="col" class="px-3 py-2.5 text-left text-[11.5px] font-semibold text-secondary">{{ t(`${prefix}.col_location`) }}</th><th scope="col" class="px-3 py-2.5 text-left text-[11.5px] font-semibold text-secondary">{{ t(`${prefix}.col_message`) }}</th></tr></thead>
                <tbody>
                    <tr v-for="(error, index) in report.errors" :key="index" class="border-t border-hairline align-top"><td class="px-3 py-2.5 text-secondary">{{ error.location }}</td><td class="px-3 py-2.5 text-danger">{{ error.message }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
