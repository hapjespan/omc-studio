<script setup lang="ts">
import { computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import ListView, { type ListColumn, type ListRow, type ListState } from '../../components/list/ListView.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

export type Option = { id: number; name: string };

const props = defineProps<{ rows: ListRow[]; count: number; query: ListState; organisation: { id: number; name: string; archived: boolean }; year: number | null; years: { id: number; year: number }[]; owners: Option[]; applications: Option[]; costKinds: string[] }>();

const page = usePage();
watch(() => page.props.flash, (flash) => (flash as { status?: string | null } | undefined)?.status && announce((flash as { status: string }).status));

const columns: ListColumn[] = [
    { key: 'code', label: t('fields.code'), sortable: true },
    { key: 'name', label: t('fields.name'), sortable: true },
    { key: 'budgetText', label: t('fields.budget') },
    { key: 'forecastText', label: t('fields.forecast') },
    { key: 'allocationText', label: t('cost_records.allocation') },
];

const shownRows = computed(() => props.rows.map((row) => ({ ...row, budgetText: row.has_figures ? row.budget : t('cost_records.no_figures'), forecastText: row.forecast ?? '', allocationText: t(`cost_records.allocation_${row.allocation}`) })));
const options = (items: Option[]) => items.map((item) => ({ value: String(item.id), label: item.name }));
const filters = computed(() => [
    { name: 'cost_kind', label: t('fields.cost_kind'), options: props.costKinds.map((kind) => ({ value: kind, label: t(`cost_kind.${kind}`) })) },
    { name: 'owner_id', label: t('fields.owner'), options: options(props.owners) },
    { name: 'application_id', label: t('fields.application'), options: options(props.applications) },
    { name: 'allocation', label: t('cost_records.allocation'), options: [{ value: 'allocated', label: t('cost_records.allocation_allocated') }, { value: 'unallocated', label: t('cost_records.allocation_unallocated') }] },
]);

function load(query: ListState, year: number | null = props.year): void {
    router.get(routes.costRecords(props.organisation.id), { year: year ?? undefined, search: query.search || undefined, sort: query.sort ?? undefined, direction: query.sort ? query.direction : undefined, filters: Object.keys(query.filters).length ? query.filters : undefined, archived: query.archived ? 1 : undefined }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <Head :title="t('cost_records.title')" />
    <h1 class="text-[20px] font-semibold">{{ t('cost_records.title') }}: {{ organisation.name }}</h1>
    <div class="mt-3 flex flex-col gap-1">
        <label for="fiscal-year" class="text-[13px] font-semibold">{{ t('cost_records.year') }}</label>
        <select id="fiscal-year" :value="year ?? ''" class="w-40 rounded-md border border-[#848B95] bg-white px-2 py-1.5 text-[13px]" @change="load(query, Number(($event.target as HTMLSelectElement).value))">
            <option v-for="item in years" :key="item.id" :value="item.year">{{ item.year }}</option>
        </select>
    </div>
    <div class="mt-3">
        <ListView :title="t('cost_records.title')" :columns="columns" :rows="shownRows" :count="count" :query="query" :filters="filters" archivable @change="load($event)" />
    </div>
</template>
