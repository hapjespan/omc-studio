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

export type ListRow = { id: number | string; name: string; archived_at?: string | null; [key: string]: unknown };
export type ListColumn = { key: string; label: string; sortable?: boolean };
export type UndoState = { id: number | string; message: string; refusal?: string | null };
export type ListState = { search: string; sort: string | null; direction: 'asc' | 'desc'; filters: Record<string, string>; archived: boolean };

const props = withDefaults(defineProps<{ title: string; columns: ListColumn[]; rows: ListRow[]; count: number; query: ListState; filters?: FilterDef[]; canCreate?: boolean; createTitle?: string; canDelete?: boolean; consequence?: (row: ListRow) => string | null; requireName?: boolean; undo?: UndoState | null; bulkActions?: BulkAction[]; bulkResult?: BulkSummary | null; archivable?: boolean; undoable?: boolean }>(), { canCreate: true, undoable: true });
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
const searchField = ref<HTMLInputElement>();
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
    <section :aria-label="title" class="flex flex-col gap-3">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1">
                <label :for="searchId" class="text-[13px] font-semibold">{{ t('list.search') }}</label>
                <input :id="searchId" ref="searchField" v-model="search" type="search" class="rounded-md border border-[#848B95] bg-white px-2 py-1.5 text-[13px]" @input="onSearch" />
            </div>
            <button v-if="$slots.form && canCreate" type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="editing = 'new'">{{ t('list.new') }}</button>
            <ArchiveToggle v-if="archivable" :model-value="query.archived" @update:model-value="change({ archived: $event })" />
            <p role="status" aria-live="polite" aria-atomic="true" class="text-[12.5px] text-[#454C57]">{{ count === 1 ? t('list.count_one') : t('list.count', { count }) }}</p>
        </div>
        <UndoMessage v-if="undo && undoable" :key="undo.id" :message="undo.message" :refusal="undo.refusal" @undo="emit('undo')" @close="emit('dismissUndo')" />
        <BulkBar v-if="bulkActions?.length" :actions="bulkActions" :selected="selected.length" :result="bulkResult" @run="runBulk" />
        <FilterBar v-if="filters?.length" :filters="filters" :active="query.filters" @change="onFilters" />
        <table class="w-full border-collapse text-[13px]">
            <caption class="sr-only">{{ title }}</caption>
            <thead>
                <tr>
                    <th v-if="bulkActions?.length" scope="col" class="px-2 py-1.5"><input type="checkbox" :checked="allSelected" :indeterminate="selected.length > 0 && !allSelected" :aria-label="t('list.select_all')" @change="toggleAll(($event.target as HTMLInputElement).checked)" /></th>
                    <template v-for="column in columns" :key="column.key">
                        <SortHeader v-if="column.sortable" :label="column.label" :direction="directionOf(column.key)" @sort="sortBy(column.key)" />
                        <th v-else scope="col" class="px-2 py-1.5 text-left text-[12.5px] font-semibold">{{ column.label }}</th>
                    </template>
                    <th v-if="archivable" scope="col" class="sr-only">{{ t('list.restore') }}</th>
                    <th v-if="canDelete" scope="col" class="sr-only">{{ t('list.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.id" class="border-t border-[#D5D9DF]">
                    <td v-if="bulkActions?.length" class="px-2 py-1.5"><input v-model="selected" type="checkbox" :value="row.id" :aria-label="t('list.select_row', { name: row.name })" /></td>
                    <td v-for="(column, index) in columns" :key="column.key" class="px-2 py-1.5">
                        <button v-if="index === 0 && $slots.form" type="button" class="rounded-md px-1 py-0.5 text-left underline" @click="editing = row">{{ row[column.key] }}</button>
                        <slot v-else-if="$slots.cell" name="cell" :row="row" :column="column" />
                        <template v-else>{{ row[column.key] }}</template>
                        <span v-if="index === 0 && row.archived_at" class="ml-2 rounded border border-[#848B95] px-1 text-[11.5px]">{{ t('list.archived') }}</span>
                    </td>
                    <td v-if="archivable" class="px-2 py-1.5">
                        <button v-if="row.archived_at" type="button" :aria-label="`${t('list.restore')} ${row.name}`" class="rounded-md border border-[#848B95] px-2 py-1 text-[12.5px]" @click="emit('restore', row)">{{ t('list.restore') }}</button>
                    </td>
                    <td v-if="canDelete" class="px-2 py-1.5">
                        <button type="button" :aria-label="`${t('list.delete')} ${row.name}`" class="rounded-md border border-[#848B95] px-2 py-1 text-[12.5px]" @click="deleting = row">{{ t('list.delete') }}</button>
                    </td>
                </tr>
                <tr v-if="rows.length === 0"><td :colspan="columns.length + 3" class="px-2 py-3 text-[#454C57]">{{ t('list.empty') }}</td></tr>
            </tbody>
        </table>
        <ConfirmDelete v-if="deleting" v-model:open="deleteOpen" :name="deleting.name" :consequence="consequence?.(deleting) ?? null" :require-name="requireName" @confirm="onDelete(deleting)" />
        <ListForm v-if="$slots.form" v-model:open="formOpen" :title="editing === 'new' || editing === null ? (createTitle ?? t('list.new')) : editing.name" v-slot="{ close, setDirty }">
            <slot name="form" :row="editing === 'new' ? null : editing" :close="close" :set-dirty="setDirty" />
        </ListForm>
    </section>
</template>
