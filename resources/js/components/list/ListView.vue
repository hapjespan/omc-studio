<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { t } from '../../i18n';
import ArchiveToggle from './ArchiveToggle.vue';
import BulkBar, { type BulkAction, type BulkSummary } from './BulkBar.vue';
import ConfirmDelete from './ConfirmDelete.vue';
import FilterBar, { type FilterDef } from './FilterBar.vue';
import UndoMessage from './UndoMessage.vue';
import ListForm from './ListForm.vue';
import { SearchEcho } from './searchEcho';
import SortHeader from './SortHeader.vue';
import AppButton from '../ui/AppButton.vue';
import SearchField from '../ui/SearchField.vue';

export type ListRow = { id: number | string; name: string; archived_at?: string | null; [key: string]: unknown };
export type ListColumn = { key: string; label: string; sortable?: boolean; align?: 'right'; secondary?: string };
export type UndoState = { id: number | string; message: string; refusal?: string | null };
export type ListState = { search: string; sort: string | null; direction: 'asc' | 'desc'; filters: Record<string, string>; archived: boolean };

const props = withDefaults(defineProps<{ title: string; columns: ListColumn[]; rows: ListRow[]; count: number; query: ListState; filters?: FilterDef[]; canCreate?: boolean; createTitle?: string; kind?: string; canDelete?: boolean; consequence?: (row: ListRow) => string | null; requireName?: boolean; undo?: UndoState | null; bulkActions?: BulkAction[]; bulkResult?: BulkSummary | null; archivable?: boolean; undoable?: boolean }>(), { canCreate: true, undoable: true });
const emit = defineEmits<{ change: [query: ListState]; delete: [row: ListRow]; undo: []; dismissUndo: []; bulk: [key: string, rows: ListRow[]]; restore: [row: ListRow] }>();

const editing = ref<ListRow | 'new' | null>(null);
const formOpen = computed({ get: () => editing.value !== null, set: (open) => { if (!open) editing.value = null; } });
const deleting = ref<ListRow | null>(null);
const deleteOpen = computed({ get: () => deleting.value !== null, set: (open) => { if (!open) deleting.value = null; } });
const selected = ref<(number | string)[]>([]);
const allSelected = computed(() => props.rows.length > 0 && selected.value.length === props.rows.length);
const searchId = useId();
const SEARCH_DELAY_MS = 250;
const search = ref(props.query.search);
const searchField = ref<InstanceType<typeof SearchField>>();
const echo = new SearchEcho();
let timer: ReturnType<typeof setTimeout> | undefined;

watch(() => props.rows, (rows) => (selected.value = selected.value.filter((id) => rows.some((row) => row.id === id))));
watch(() => props.query.search, (value) => {
    if (echo.adopts(value)) search.value = value;
});
onBeforeUnmount(() => clearTimeout(timer));
watch(() => props.undo, async (now, before) => {
    if (before && !now) {
        await nextTick();
        searchField.value?.focus();
    }
});

function change(patch: Partial<ListState>): void {
    emit('change', { ...props.query, ...patch });
}

function toggleAll(checked: boolean): void {
    selected.value = checked ? props.rows.map((row) => row.id) : [];
}

async function focusSearchUnlessUndo(): Promise<void> {
    await nextTick();
    if (!props.undo) searchField.value?.focus();
}

function runBulk(key: string): void {
    emit('bulk', key, props.rows.filter((row) => selected.value.includes(row.id)));
    selected.value = [];
    focusSearchUnlessUndo();
}

function onDelete(row: ListRow): void {
    emit('delete', row);
    focusSearchUnlessUndo();
}

function onFilters(filters: Record<string, string>): void {
    const removed = Object.keys(filters).length < Object.keys(props.query.filters).length;
    change({ filters });
    if (removed) focusSearchUnlessUndo();
}

function onSearch(): void {
    clearTimeout(timer);
    echo.typed();
    timer = setTimeout(() => {
        echo.sent(search.value);
        change({ search: search.value });
    }, SEARCH_DELAY_MS);
}

function sortBy(key: string): void {
    const descending = props.query.sort === key && props.query.direction === 'asc';
    change({ sort: key, direction: descending ? 'desc' : 'asc' });
}

function directionOf(key: string): 'asc' | 'desc' | null {
    return props.query.sort === key ? props.query.direction : null;
}

defineExpose({ focusSearch: () => searchField.value?.focus() });
</script>

<template>
    <section :aria-label="title" :class="['flex flex-col gap-3', formOpen && 'min-[48rem]:pr-[376px]']">
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-64"><SearchField :id="searchId" ref="searchField" v-model="search" :label="t('list.search')" @input="onSearch" /></div>
            <FilterBar v-if="filters?.length" :filters="filters" :active="query.filters" @change="onFilters" />
            <ArchiveToggle v-if="archivable" :model-value="query.archived" @update:model-value="change({ archived: $event })" />
            <p role="status" aria-live="polite" aria-atomic="true" class="text-[12.5px] text-secondary">{{ count === 1 ? t('list.count_one') : t('list.count', { count }) }}</p>
            <AppButton v-if="$slots.form && canCreate" variant="primary" icon="plus" class="ml-auto" @click="editing = 'new'">{{ t('list.new') }}</AppButton>
        </div>
        <UndoMessage v-if="undo && undoable" :key="undo.id" :message="undo.message" :refusal="undo.refusal" @undo="emit('undo')" @close="emit('dismissUndo')" />
        <BulkBar v-if="bulkActions?.length" :actions="bulkActions" :selected="selected.length" :result="bulkResult" @run="runBulk" />
        <div class="overflow-x-auto rounded-panel border border-hairline bg-white">
        <table class="w-full border-collapse text-[13px]">
            <caption class="sr-only">{{ title }}</caption>
            <thead>
                <tr class="border-b border-hairline">
                    <th v-if="bulkActions?.length" scope="col" class="px-3 py-2.5"><input type="checkbox" :checked="allSelected" :indeterminate="selected.length > 0 && !allSelected" :aria-label="t('list.select_all')" @change="toggleAll(($event.target as HTMLInputElement).checked)" /></th>
                    <template v-for="column in columns" :key="column.key">
                        <SortHeader v-if="column.sortable" :label="column.label" :direction="directionOf(column.key)" :align="column.align" @sort="sortBy(column.key)" />
                        <th v-else scope="col" :class="['px-3 py-2.5 text-[12px] font-medium text-muted', column.align === 'right' ? 'text-right' : 'text-left']">{{ column.label }}</th>
                    </template>
                    <th v-if="archivable" scope="col" class="sr-only">{{ t('list.restore') }}</th>
                    <th v-if="canDelete" scope="col" class="sr-only">{{ t('list.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.id" :class="['border-t border-hairline first:border-t-0 hover:bg-surface', editing === row && 'bg-surface']">
                    <td v-if="bulkActions?.length" class="px-3 py-2.5"><input v-model="selected" type="checkbox" :value="row.id" :aria-label="t('list.select_row', { name: row.name })" /></td>
                    <td v-for="(column, index) in columns" :key="column.key" :class="['px-3 py-2.5', column.align === 'right' && 'text-right tabular-nums']">
                        <button v-if="index === 0 && $slots.form" type="button" class="rounded-control text-left font-semibold" @click="editing = row">{{ row[column.key] }}</button>
                        <template v-else>{{ row[column.key] }}</template>
                        <span v-if="index === 0 && row.archived_at" class="ml-2 rounded-badge border border-hairline px-1 text-[11.5px] text-secondary">{{ t('list.archived') }}</span>
                        <span v-if="column.secondary && row[column.secondary]" class="block text-[12px] text-muted">{{ row[column.secondary] }}</span>
                    </td>
                    <td v-if="archivable" class="px-3 py-2.5">
                        <AppButton v-if="row.archived_at" size="small" :aria-label="`${t('list.restore')} ${row.name}`" @click="emit('restore', row)">{{ t('list.restore') }}</AppButton>
                    </td>
                    <td v-if="canDelete" class="px-3 py-2.5">
                        <AppButton size="small" :aria-label="`${t('list.delete')} ${row.name}`" @click="deleting = row">{{ t('list.delete') }}</AppButton>
                    </td>
                </tr>
                <tr v-if="rows.length === 0"><td :colspan="columns.length + 3" class="px-3 py-3 text-secondary">{{ t('list.empty') }}</td></tr>
            </tbody>
        </table>
        </div>
        <ConfirmDelete v-if="deleting" v-model:open="deleteOpen" :name="deleting.name" :consequence="consequence?.(deleting) ?? null" :require-name="requireName" @confirm="onDelete(deleting)" />
        <ListForm v-if="$slots.form" v-model:open="formOpen" :kind="kind ? t(editing === 'new' || editing === null ? 'list.kind_new' : 'list.kind_edit', { kind }) : undefined" :title="editing === 'new' || editing === null ? (createTitle ?? t('list.new')) : editing.name" v-slot="{ close, setDirty }">
            <slot name="form" :row="editing === 'new' ? null : editing" :close="close" :set-dirty="setDirty" />
        </ListForm>
    </section>
</template>
