<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import AppButton from '../../components/ui/AppButton.vue';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import type { BulkSummary } from '../../components/list/BulkBar.vue';
import ListView, { type ListColumn, type ListRow, type ListState } from '../../components/list/ListView.vue';
import { useUndoFlash } from '../../components/list/undoFlash';
import { plural, t } from '../../i18n';
import CarryOver from './CarryOver.vue';
import CostRecordForm from './CostRecordForm.vue';
import FinaliseCard from './FinaliseCard.vue';
import FiscalYearsPanel from './FiscalYearsPanel.vue';
import SummaryCards, { type Cards } from './SummaryCards.vue';
import YearlyFiguresForm from './YearlyFiguresForm.vue';
import { routes } from '../../routes';
import { formatAmount } from '../../format';
import { exportCsv } from '../../canvas/csvDownload';

export type Option = { id: number; name: string };

const props = defineProps<{ rows: ListRow[]; count: number; query: ListState; organisation: { id: number; name: string; archived: boolean }; canvas?: { id: number; name: string }; cards?: Cards | null; canvases?: { id: number; name: string }[]; finalised?: { canvas_id: number; year: number; at: string; by: string | null }[]; year: number | null; years: { id: number; year: number }[]; owners: Option[]; applications: Option[]; costKinds: string[] }>();

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
const exportNote = ref('');
async function exportFile(url: string, name: string): Promise<void> {
    const text = await exportCsv(url, name);
    if (!text) return;
    exportNote.value = text;
    announce(text);
}
const yearsOpen = ref(false);
const carryOpen = ref(false);
const yearFinalised = computed(() => Boolean(props.canvas && props.finalised?.some((entry) => entry.canvas_id === props.canvas?.id && entry.year === props.year)));
const dock = ref<HTMLElement | null>(null);
let skipGuard = false;
const removeGuard = router.on('before', (event) => {
    const visit = event.detail.visit;
    if (skipGuard || visit.method !== 'get' || visit.url.pathname !== window.location.pathname || visit.url.searchParams.get('year') === String(props.year)) return;
    const target = `${visit.url.pathname}${visit.url.search}`;
    list.value?.guard(
        () => {
            skipGuard = true;
            router.get(target, {}, { preserveScroll: true, preserveState: false, onFinish: () => (skipGuard = false) });
        },
        () => {
            const select = document.getElementById('year-selector') as HTMLSelectElement | null;
            if (select) select.value = String(props.year);
        },
    );
    return false;
});
onBeforeUnmount(removeGuard);
const keep = { preserveScroll: true, preserveState: true };

const columns: ListColumn[] = [
    { key: 'name', label: t('fields.name'), sortable: true, sub: 'code' },
    { key: 'kindText', label: t('cost_records.filter_kind') },
    { key: 'ownerText', label: t('fields.owner') },
    { key: 'applicationText', label: t('fields.application') },
    { key: 'budgetText', label: t('fields.budget'), align: 'right' },
    { key: 'forecastText', label: t('fields.forecast'), align: 'right' },
    { key: 'allocationText', label: props.canvas ? t('cost_records.allocation_canvas') : t('cost_records.allocation') },
];

const nameOf = (items: Option[], id: unknown): string => items.find((item) => item.id === Number(id))?.name ?? '';
const delta = (row: ListRow): string => {
    const cents = Number(row.forecast_cents) - Number(row.budget_cents);
    return row.forecast_cents === null || row.budget_cents === null || cents === 0 ? '' : `${cents > 0 ? '+' : '-'}${formatAmount(Math.abs(cents)).replace('€ ', '')}`;
};
const shownRows = computed(() => props.rows.map((row) => ({ ...row, kindText: row.cost_kind ? t(`cost_kind.${row.cost_kind}`) : '', ownerText: nameOf(props.owners, row.owner_id), applicationText: nameOf(props.applications, row.application_id), budgetText: row.has_figures ? row.budget : t('cost_records.no_figures'), forecastText: row.forecast ?? '', allocationText: props.canvas ? (row.allocation === 'allocated' ? '100%' : t('cost_records.allocation_unknown')) : t(`cost_records.allocation_${row.allocation}`) })));
const fresh = (row: ListRow): ListRow => shownRows.value.find((candidate) => candidate.id === row.id) ?? row;
const options = (items: Option[]) => items.map((item) => ({ value: String(item.id), label: item.name }));
const filters = computed(() => [
    { name: 'cost_kind', label: t('fields.cost_kind'), short: t('cost_records.filter_kind'), options: props.costKinds.map((kind) => ({ value: kind, label: t(`cost_kind.${kind}`) })) },
    { name: 'owner_id', label: t('fields.owner'), options: options(props.owners) },
    { name: 'application_id', label: t('fields.application'), options: options(props.applications) },
    { name: 'allocation', label: t('cost_records.allocation'), options: [{ value: 'allocated', label: t('cost_records.allocation_allocated') }, { value: 'unallocated', label: t('cost_records.allocation_unallocated') }] },
]);

const consequence = (row: ListRow): string | null => {
    const figures = Number(row.figure_count);
    const sets = Number(row.set_count);
    return figures + sets === 0 ? null : t('cost_records.delete_consequence', { figures: plural('cost_records.figure_count', figures), sets: plural('cost_records.set_count', sets) });
};
const eyebrow = (row: ListRow | null): string | null => (row ? [t('cost_records.edit_eyebrow'), row.code].filter(Boolean).join(' · ') : null);
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
    <div class="grid items-start gap-x-5 gap-y-4 lg:grid-cols-[minmax(0,1fr)_auto]">
        <div class="flex min-w-0 flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="font-display text-[20px] font-semibold tracking-[-0.2px]">{{ year ? `${t('cost_records.title')} ${year}` : t('cost_records.title') }}</h1>
                <div class="ml-auto flex flex-wrap justify-end gap-2">
                    <AppButton @click="yearsOpen = true">{{ t('fiscal_years.title') }}</AppButton>
                    <AppButton @click="exportFile(routes.costRecordsCsvExport(organisation.id), 'cost-records.csv')">{{ t('csvx.export_cost_records') }}</AppButton>
                    <AppButton v-if="canvas" @click="exportFile(routes.allocationsCsvExport(canvas.id), 'allocations.csv')">{{ t('csvx.export_allocations') }}</AppButton>
                    <AppButton v-if="canvas && cards && year !== null" @click="carryOpen = true">{{ t('carry_over.title') }}</AppButton>
                    <AppButton v-if="!organisation.archived" @click="router.get(routes.costRecordImport(organisation.id))">{{ t('cost_records.import_csv') }}</AppButton>
                    <AppButton v-if="!organisation.archived" variant="primary" icon="plus" @click="list?.create($event.currentTarget as HTMLElement)">{{ t('list.new') }}</AppButton>
                </div>
            </div>
            <p v-if="exportNote" class="text-[12.5px]" data-testid="export-note">{{ exportNote }}</p>
            <FiscalYearsPanel v-model:open="yearsOpen" :organisation-id="organisation.id" :years="years" :canvases="canvases" :finalised="finalised" :writable="!organisation.archived" />
            <CarryOver v-if="canvas && cards && year !== null" v-model:open="carryOpen" :canvas-id="canvas.id" :years="years" />
            <div v-if="canvas && cards" class="grid gap-3 lg:grid-cols-4">
                <SummaryCards :cards="cards" class="lg:col-span-3" />
                <FinaliseCard v-if="year !== null" :canvas-id="canvas.id" :year="year" :remaining="cards.unknown.count" :finalised="yearFinalised" :writable="!organisation.archived" />
            </div>
            <div v-if="listError.rule" role="alert"><ErrorSummary :errors="listError" /></div>
            <ListView ref="list" :title="t('cost_records.title')" :columns="columns" :rows="shownRows" :count="count" :query="query" :filters="filters" :dock="dock" :eyebrow="eyebrow" :search-placeholder="t('cost_records.search_placeholder')" archivable :can-delete="!organisation.archived" :consequence="consequence" :undo="undo.state.value" :bulk-actions="[{ key: 'delete', label: t('list.delete') }]" :bulk-result="bulkResult" :can-create="!organisation.archived" :create-title="t('cost_records.new')" @change="load($event)" @delete="remove" @restore="restore" @undo="undo.run" @dismiss-undo="undo.dismiss" @bulk="bulk">
                <template #cell="{ row, column }">
                    <span v-if="column.key === 'allocationText'" :class="['inline-flex items-center gap-1.5 whitespace-nowrap text-[12px] font-semibold', row.allocation === 'allocated' ? 'text-success' : 'text-warning']"><span aria-hidden="true" :class="['inline-block h-[7px] w-[7px] rounded-full', row.allocation === 'allocated' ? 'bg-success' : 'border-[1.5px] border-warning']" />{{ row[column.key] }}</span>
                    <span v-else-if="column.key === 'budgetText'" class="whitespace-nowrap font-semibold">{{ row[column.key] }}</span>
                    <span v-else-if="column.key === 'forecastText'" class="whitespace-nowrap">{{ row[column.key] }}<span v-if="delta(row)" :class="['ml-1.5 text-[11.5px]', delta(row).startsWith('+') ? 'text-warning' : 'text-success']"><span class="sr-only">{{ t('cost_records.delta') }} </span>{{ delta(row) }}</span></span>
                    <span v-else-if="column.key === 'ownerText' || column.key === 'applicationText' || column.key === 'kindText'" :class="['whitespace-nowrap', row[column.key] ? '' : 'text-muted']">{{ row[column.key] || t('cost_records.none_text') }}</span>
                    <template v-else>{{ row[column.key] }}</template>
                </template>
                <template #form="{ row, close, requestClose, setDirty, requestDelete }">
                    <CostRecordForm :key="row?.id ?? 'new'" :row="row" :organisation-id="organisation.id" :owners="owners" :applications="applications" :cost-kinds="costKinds" @close="close" @cancel="requestClose" @dirty="setDirty($event, 'record')" />
                    <YearlyFiguresForm v-if="row && year" :key="`${row.id}-${year}`" :row="fresh(row)" :year="year" @dirty="setDirty($event, 'figures')" />
                    <div v-if="row" class="mt-4 border-t border-hairline pt-4">
                        <p class="text-[12.5px] text-secondary">{{ consequence(row) ?? t('list.delete_none') }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <AppButton v-if="!row.archived_at" @click="archive(row, close)">{{ t('cost_records.archive') }}</AppButton>
                            <AppButton v-if="!organisation.archived" variant="danger" @click="requestDelete">{{ t('list.delete') }}</AppButton>
                        </div>
                    </div>
                </template>
            </ListView>
        </div>
        <aside ref="dock" :aria-label="t('cost_records.panel')" class="w-full empty:hidden lg:w-detail-panel" />
    </div>
</template>
