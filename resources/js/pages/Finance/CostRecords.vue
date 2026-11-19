<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import type { BulkSummary } from '../../components/list/BulkBar.vue';
import ListView, { type ListColumn, type ListRow, type ListState } from '../../components/list/ListView.vue';
import { useUndoFlash } from '../../components/list/undoFlash';
import { plural, t } from '../../i18n';
import CostRecordForm from './CostRecordForm.vue';
import FiscalYearsPanel from './FiscalYearsPanel.vue';
import YearlyFiguresForm from './YearlyFiguresForm.vue';
import { routes } from '../../routes';

export type Option = { id: number; name: string };

const props = defineProps<{ rows: ListRow[]; count: number; query: ListState; organisation: { id: number; name: string; archived: boolean }; year: number | null; years: { id: number; year: number }[]; owners: Option[]; applications: Option[]; costKinds: string[] }>();

const page = usePage();
watch(() => page.props.flash, (flash) => (flash as { status?: string | null } | undefined)?.status && announce((flash as { status: string }).status));

const list = ref<InstanceType<typeof ListView>>();
const errors = computed(() => page.props.errors as Record<string, string>);
const listError = computed(() => (errors.value.rule ? { rule: errors.value.rule } : {}));
const undo = useUndoFlash();
const bulkResult = computed<BulkSummary | null>(() => {
    const result = (page.props.flash as { bulk?: { done: number; skipped: { name: string; reason: string }[] } | null } | undefined)?.bulk ?? null;
    return result ? { done: result.done, verb: t('cost_records.bulk_verb'), skipped: result.skipped } : null;
});
const yearsOpen = ref(false);
const keep = { preserveScroll: true, preserveState: true };

const columns: ListColumn[] = [
    { key: 'name', label: t('fields.name'), sortable: true },
    { key: 'code', label: t('fields.code'), sortable: true },
    { key: 'budgetText', label: t('fields.budget') },
    { key: 'forecastText', label: t('fields.forecast') },
    { key: 'allocationText', label: t('cost_records.allocation') },
];

const shownRows = computed(() => props.rows.map((row) => ({ ...row, budgetText: row.has_figures ? row.budget : t('cost_records.no_figures'), forecastText: row.forecast ?? '', allocationText: t(`cost_records.allocation_${row.allocation}`) })));
const fresh = (row: ListRow): ListRow => shownRows.value.find((candidate) => candidate.id === row.id) ?? row;
const options = (items: Option[]) => items.map((item) => ({ value: String(item.id), label: item.name }));
const filters = computed(() => [
    { name: 'cost_kind', label: t('fields.cost_kind'), options: props.costKinds.map((kind) => ({ value: kind, label: t(`cost_kind.${kind}`) })) },
    { name: 'owner_id', label: t('fields.owner'), options: options(props.owners) },
    { name: 'application_id', label: t('fields.application'), options: options(props.applications) },
    { name: 'allocation', label: t('cost_records.allocation'), options: [{ value: 'allocated', label: t('cost_records.allocation_allocated') }, { value: 'unallocated', label: t('cost_records.allocation_unallocated') }] },
]);

const consequence = (row: ListRow): string | null => {
    const figures = Number(row.figure_count);
    const sets = Number(row.set_count);
    return figures + sets === 0 ? null : t('cost_records.delete_consequence', { figures: plural('cost_records.figure_count', figures), sets: plural('cost_records.set_count', sets) });
};
const focusList = async (): Promise<void> => {
    await nextTick();
    list.value?.focusSearch();
};
const remove = (row: ListRow): void => {
    undo.start();
    router.delete(routes.costRecord(row.id), keep);
};
const restore = (row: ListRow): void => router.post(routes.costRecordRestore(row.id), {}, { ...keep, onSuccess: focusList });
const archive = (row: ListRow, close: () => void): void => router.post(routes.costRecordArchive(row.id), {}, { ...keep, onSuccess: () => { close(); focusList(); } });
function bulk(key: string, selected: ListRow[]): void {
    if (key !== 'delete') return;
    undo.start();
    router.post(routes.costRecordsBulkDelete(props.organisation.id), { ids: selected.map((row) => row.id) }, keep);
}

function load(query: ListState, year: number | null = props.year): void {
    router.get(routes.costRecords(props.organisation.id), { year: year ?? undefined, search: query.search || undefined, sort: query.sort ?? undefined, direction: query.sort ? query.direction : undefined, filters: Object.keys(query.filters).length ? query.filters : undefined, archived: query.archived ? 1 : undefined }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <Head :title="t('cost_records.title')" />
    <h1 class="text-[20px] font-semibold">{{ t('cost_records.title') }}: {{ organisation.name }}</h1>
    <button type="button" class="mt-3 rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="yearsOpen = true">{{ t('fiscal_years.title') }}</button>
    <FiscalYearsPanel v-model:open="yearsOpen" :organisation-id="organisation.id" :years="years" :writable="!organisation.archived" />
    <div v-if="listError.rule" role="alert" class="mt-3"><ErrorSummary :errors="listError" /></div>
    <div class="mt-3">
        <ListView ref="list" :title="t('cost_records.title')" :columns="columns" :rows="shownRows" :count="count" :query="query" :filters="filters" archivable :can-delete="!organisation.archived" :consequence="consequence" :undo="undo.state.value" :bulk-actions="[{ key: 'delete', label: t('list.delete') }]" :bulk-result="bulkResult" :can-create="!organisation.archived" :create-title="t('cost_records.new')" @change="load($event)" @delete="remove" @restore="restore" @undo="undo.run" @dismiss-undo="undo.dismiss" @bulk="bulk">
            <template #form="{ row, close, setDirty }">
                <CostRecordForm :key="row?.id ?? 'new'" :row="row" :organisation-id="organisation.id" :owners="owners" :applications="applications" :cost-kinds="costKinds" @close="close" @dirty="setDirty" />
                <YearlyFiguresForm v-if="row && year" :key="`${row.id}-${year}`" :row="fresh(row)" :year="year" @dirty="setDirty" />
                <button v-if="row && !row.archived_at" type="button" class="mt-3 rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="archive(row, close)">{{ t('cost_records.archive') }}</button>
            </template>
        </ListView>
    </div>
</template>
