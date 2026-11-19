<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SidePanel from '../../components/overlays/SidePanel.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

type Report = { summary: string; copied: { name: string }[]; skipped: { name: string; reason: string }[]; manual: { name: string }[] };

const props = defineProps<{ canvasId: number; years: { year: number }[] }>();
const open = defineModel<boolean>('open', { default: false });
const page = usePage();
const source = ref(String(props.years[0]?.year ?? ''));
const target = ref(String(props.years[1]?.year ?? props.years[0]?.year ?? ''));
const withFigures = ref(false);
const refusal = computed(() => (page.props.errors as Record<string, string>).rule ?? null);
const report = computed(() => ((page.props.flash as { carry_over?: Report | null } | undefined)?.carry_over ?? null));
const options = computed(() => props.years.map((item) => ({ value: String(item.year), label: String(item.year) })));

function run(): void {
    router.post(routes.carryOver(props.canvasId, Number(target.value)), { source_year: Number(source.value), with_figures: withFigures.value }, { preserveScroll: true });
}
</script>

<template>
    <SidePanel v-model:open="open" :title="t('carry_over.title')">
        <p v-if="refusal" role="alert" class="mb-2 text-[13px] text-[#A12A1E]">{{ refusal }}</p>
        <form novalidate class="flex flex-col gap-3" @submit.prevent="run">
            <label class="flex flex-col gap-1 text-[13px]">{{ t('carry_over.source') }}<select id="carry-over-source" v-model="source" class="rounded-md border border-[#848B95] bg-white px-2 py-1.5"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
            <label class="flex flex-col gap-1 text-[13px]">{{ t('carry_over.target') }}<select id="carry-over-target" v-model="target" class="rounded-md border border-[#848B95] bg-white px-2 py-1.5"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
            <label class="flex items-center gap-2 text-[13px]"><input v-model="withFigures" type="checkbox" />{{ t('carry_over.with_figures') }}</label>
            <div><button type="submit" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('carry_over.run') }}</button></div>
        </form>
        <section role="region" v-if="report" aria-labelledby="carry-over-result" class="mt-4 text-[13px]">
            <h2 id="carry-over-result" role="status" class="text-[16px] font-semibold">{{ report.summary }}</h2>
            <table v-if="report.copied.length" class="mt-2 w-full">
                <caption class="text-left font-semibold">{{ t('carry_over.copied') }}</caption>
                <thead><tr><th scope="col" class="text-left">{{ t('carry_over.col_name') }}</th></tr></thead>
                <tbody><tr v-for="row in report.copied" :key="row.name"><th scope="row" class="text-left font-normal">{{ row.name }}</th></tr></tbody>
            </table>
            <table v-if="report.skipped.length" class="mt-2 w-full">
                <caption class="text-left font-semibold">{{ t('carry_over.skipped') }}</caption>
                <thead><tr><th scope="col" class="text-left">{{ t('carry_over.col_name') }}</th><th scope="col" class="text-left">{{ t('carry_over.col_reason') }}</th></tr></thead>
                <tbody><tr v-for="row in report.skipped" :key="row.name + row.reason"><th scope="row" class="text-left font-normal">{{ row.name }}</th><td>{{ row.reason }}</td></tr></tbody>
            </table>
            <table v-if="report.manual.length" class="mt-2 w-full">
                <caption class="text-left font-semibold">{{ t('carry_over.manual') }}</caption>
                <thead><tr><th scope="col" class="text-left">{{ t('carry_over.col_name') }}</th></tr></thead>
                <tbody><tr v-for="row in report.manual" :key="row.name"><th scope="row" class="text-left font-normal">{{ row.name }}</th></tr></tbody>
            </table>
        </section>
    </SidePanel>
</template>
