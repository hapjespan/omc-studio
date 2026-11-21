<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { t } from '../../i18n';
import ArchiveToggle from './ArchiveToggle.vue';
import BulkBar, { type BulkAction, type BulkSummary } from './BulkBar.vue';
import ConfirmDelete from './ConfirmDelete.vue';
import FilterBar, { type FilterDef } from './FilterBar.vue';
import UndoMessage from './UndoMessage.vue';
import ListDock from './ListDock.vue';
import ListForm from './ListForm.vue';
import { SearchEcho } from './searchEcho';
import SortHeader from './SortHeader.vue';
import AppButton from '../ui/AppButton.vue';
import CheckBox from '../ui/CheckBox.vue';
import Pill from '../ui/Pill.vue';
import SearchField from '../ui/SearchField.vue';

export type ListRow = { id: number | string; name: string; archived_at?: string | null; [key: string]: unknown };
export type ListColumn = { key: string; label: string; sortable?: boolean; align?: 'right'; secondary?: string };
export type UndoState = { id: number | string; message: string; refusal?: string | null };
export type ListState = { search: string; sort: string | null; direction: 'asc' | 'desc'; filters: Record<string, string>; archived: boolean };

const props = withDefaults(defineProps<{ title: string; columns: ListColumn[]; rows: ListRow[]; count: number; query: ListState; filters?: FilterDef[]; canCreate?: boolean; createTitle?: string; kind?: string; canDelete?: boolean; consequence?: (row: ListRow) => string | null; requireName?: boolean; undo?: UndoState | null; bulkActions?: BulkAction[]; bulkResult?: BulkSummary | null; archivable?: boolean; undoable?: boolean; createLabel?: string; searchPlaceholder?: string; dock?: HTMLElement | null; eyebrow?: (row: ListRow | null) => string | null }>(), { canCreate: true, undoable: true });
const emit = defineEmits<{ change: [query: ListState]; delete: [row: ListRow]; undo: []; dismissUndo: []; bulk: [key: string, rows: ListRow[]]; restore: [row: ListRow] }>();

const editing = ref<ListRow | 'new' | null>(null);
const formOpen = computed({ get: () => editing.value !== null, set: (open) => { if (!open) editing.value = null; } });
const dirtyKeys = ref<string[]>([]);
const pending = ref<{ action: () => void; onKeep?: () => void } | null>(null);
const opener = ref<HTMLElement | null>(null);
const deleting = ref<ListRow | null>(null);
const deleteOpen = computed({ get: () => deleting.value !== null, set: (open) => { if (!open) deleting.value = null; } });
const selected = ref<(number | string)[]>([]);
const FIRST_CHUNK = 60;
const NEXT_CHUNK = 200;
const shown = ref(FIRST_CHUNK);
const visibleRows = computed(() => props.rows.slice(0, shown.value));
let growing: ReturnType<typeof setTimeout> | undefined;
let framing: number | undefined;
function stopGrowing(): void {
    clearTimeout(growing);
    if (framing !== undefined) cancelAnimationFrame(framing);
    framing = undefined;
}
function grow(): void {
    stopGrowing();
    if (shown.value >= props.rows.length) return;
    const step = (): void => {
        framing = undefined;
        shown.value += NEXT_CHUNK;
        grow();
    };
    if (shown.value === FIRST_CHUNK) framing = requestAnimationFrame(step);
    else growing = setTimeout(step, 40);
}
const allSelected = computed(() => props.rows.length > 0 && selected.value.length === props.rows.length);
const allChecked = computed({ get: () => allSelected.value, set: (checked: boolean) => toggleAll(checked) });
const searchId = useId();
const SEARCH_DELAY_MS = 250;
const search = ref(props.query.search);
const searchField = ref<InstanceType<typeof SearchField>>();
const echo = new SearchEcho();
let timer: ReturnType<typeof setTimeout> | undefined;

watch(editing, async (now, before) => {
    dirtyKeys.value = [];
    pending.value = null;
    if (!props.dock || now !== null || before === null) return;
    await nextTick();
    if (opener.value?.isConnected) opener.value.focus();
});
watch(() => props.rows, (rows) => {
    selected.value = selected.value.filter((id) => rows.some((row) => row.id === id));
    shown.value = Math.max(FIRST_CHUNK, Math.min(shown.value, rows.length));
    grow();
}, { immediate: true });
watch(() => props.query.search, (value) => {
    if (echo.adopts(value)) search.value = value;
});
onBeforeUnmount(() => {
    clearTimeout(timer);
    stopGrowing();
});
watch(() => props.undo, async (now, before) => {
    if (before && !now) {
        await nextTick();
        searchField.value?.focus();
    }
});

function setDirty(value: boolean, key = 'form'): void {
    dirtyKeys.value = value ? [...new Set([...dirtyKeys.value, key])] : dirtyKeys.value.filter((candidate) => candidate !== key);
}

function guard(action: () => void, onKeep?: () => void): void {
    if (!props.dock || dirtyKeys.value.length === 0) return action();
    pending.value = { action, onKeep };
}

function discard(): void {
    const action = pending.value?.action;
    dirtyKeys.value = [];
    pending.value = null;
    action?.();
}

function keepEditing(): void {
    pending.value?.onKeep?.();
    pending.value = null;
}

function open(target: ListRow | 'new', from?: HTMLElement | null): void {
    if (target !== 'new' && editing.value !== null && editing.value !== 'new' && editing.value.id === target.id) return;
    guard(() => {
        if (editing.value === null) opener.value = from ?? (document.activeElement as HTMLElement | null);
        editing.value = target;
    });
}

function requestClose(): void {
    guard(() => (editing.value = null));
}

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
    if (editing.value !== null && editing.value !== 'new' && selected.value.includes(editing.value.id)) editing.value = null;
    emit('bulk', key, props.rows.filter((row) => selected.value.includes(row.id)));
    selected.value = [];
    focusSearchUnlessUndo();
}

function onDelete(row: ListRow): void {
    emit('delete', row);
    if (props.dock) {
        opener.value = null;
        editing.value = null;
    }
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

defineExpose({ focusSearch: () => searchField.value?.focus(), create: (from?: HTMLElement | null) => open('new', from), guard });
</script>

<template>
    <section :aria-label="title" :class="['flex flex-col gap-3', formOpen && !dock && 'min-[48rem]:pr-[calc(var(--spacing-detail-panel)+16px)]']">
        <div class="flex flex-wrap items-start gap-2">
            <div class="w-[220px] max-w-full"><SearchField :id="searchId" ref="searchField" v-model="search" :label="t('list.search')" :placeholder="searchPlaceholder ?? t('list.search')" @input="onSearch" /></div>
            <AppButton v-if="$slots.form && canCreate && !dock" variant="primary" icon="plus" @click="open('new', $event.currentTarget as HTMLElement)">{{ createLabel ?? t('list.new') }}</AppButton>
            <FilterBar v-if="filters?.length" :filters="filters" :active="query.filters" @change="onFilters" />
            <div class="ml-auto flex h-control items-center gap-3 whitespace-nowrap">
                <ArchiveToggle v-if="archivable" :model-value="query.archived" @update:model-value="change({ archived: $event })" />
                <p role="status" aria-live="polite" aria-atomic="true" class="text-[12.5px] text-secondary">{{ count === 1 ? t('list.count_one') : t('list.count', { count }) }}</p>
            </div>
        </div>
        <UndoMessage v-if="undo && undoable" :key="undo.id" :message="undo.message" :refusal="undo.refusal" @undo="emit('undo')" @close="emit('dismissUndo')" />
        <BulkBar v-if="bulkActions?.length" :actions="bulkActions" :selected="selected.length" :result="bulkResult" @run="runBulk" />
        <div class="overflow-x-auto rounded-panel border border-hairline bg-white">
            <table class="w-full border-collapse text-[13px]">
                <caption class="sr-only">{{ title }}</caption>
                <thead>
                    <tr class="border-b border-hairline">
                        <th v-if="bulkActions?.length" scope="col" class="pl-3 pr-2 py-2.5"><CheckBox v-model="allChecked" :indeterminate="selected.length > 0 && !allSelected" :aria-label="t('list.select_all')" /></th>
                        <template v-for="column in columns" :key="column.key">
                            <SortHeader v-if="column.sortable" :label="column.label" :direction="directionOf(column.key)" :align="column.align" @sort="sortBy(column.key)" />
                            <th v-else scope="col" :class="['pl-3 pr-2 py-2.5 text-[11.5px] font-semibold text-secondary', column.align === 'right' ? 'text-right' : 'text-left']">{{ column.label }}</th>
                        </template>
                        <th v-if="archivable" scope="col" class="sr-only">{{ t('list.restore') }}</th>
                        <th v-if="canDelete && !dock" scope="col" class="sr-only">{{ t('list.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in visibleRows" :key="row.id" :class="['border-t border-hairline first:border-t-0 hover:bg-surface', editing && editing !== 'new' && editing.id === row.id && 'bg-surface shadow-[inset_2px_0_0_var(--color-ink)]']">
                        <td v-if="bulkActions?.length" class="pl-3 pr-2 py-2.5"><CheckBox v-model="selected" :value="row.id" :aria-label="t('list.select_row', { name: row.name })" /></td>
                        <td v-for="(column, index) in columns" :key="column.key" :class="['pl-3 pr-2 py-2.5', index === 0 && dock && 'min-w-36', column.align === 'right' && 'text-right tabular-nums']">
                            <button v-if="index === 0 && $slots.form" type="button" class="rounded-control text-left font-semibold" @click="open(row, $event.currentTarget as HTMLElement)">{{ row[column.key] }}</button>
                            <slot v-else-if="$slots.cell" name="cell" :row="row" :column="column" />
                            <template v-else>{{ row[column.key] }}</template>
                            <Pill v-if="index === 0 && row.archived_at" class="ml-2">{{ t('list.archived') }}</Pill>
                            <span v-if="column.secondary && row[column.secondary]" class="block text-[12px] text-muted">{{ row[column.secondary] }}</span>
                        </td>
                        <td v-if="archivable" class="pl-3 pr-2 py-2.5">
                            <AppButton v-if="row.archived_at" size="small" :aria-label="`${t('list.restore')} ${row.name}`" @click="emit('restore', row)">{{ t('list.restore') }}</AppButton>
                        </td>
                        <td v-if="canDelete && !dock" class="pl-3 pr-2 py-2.5">
                            <AppButton size="small" variant="danger" :aria-label="`${t('list.delete')} ${row.name}`" @click="deleting = row">{{ t('list.delete') }}</AppButton>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0"><td :colspan="columns.length + 3" class="px-3 py-3 text-muted">{{ t('list.empty') }}</td></tr>
                </tbody>
            </table>
        </div>
        <ConfirmDelete v-if="deleting" v-model:open="deleteOpen" :name="deleting.name" :consequence="consequence?.(deleting) ?? null" :require-name="requireName" @confirm="onDelete(deleting)" />
        <Teleport v-if="$slots.form && dock && editing !== null" :to="dock">
            <ListDock :key="editing === 'new' ? 'new' : editing.id" :title="editing === 'new' ? (createTitle ?? t('list.new')) : editing.name" :eyebrow="eyebrow?.(editing === 'new' ? null : editing)" :confirming="pending !== null" @request-close="requestClose" @discard="discard" @keep="keepEditing">
                <slot name="form" :row="editing === 'new' ? null : editing" :close="() => (editing = null)" :request-close="requestClose" :set-dirty="setDirty" :request-delete="() => (deleting = editing === 'new' ? null : editing)" />
            </ListDock>
        </Teleport>
        <ListForm v-else-if="$slots.form && !dock" v-model:open="formOpen" :kind="kind ? t(editing === 'new' || editing === null ? 'list.kind_new' : 'list.kind_edit', { kind }) : undefined" :title="editing === 'new' || editing === null ? (createTitle ?? t('list.new')) : editing.name" v-slot="{ close, setDirty: markDirty, requestClose: requestPanelClose }">
            <slot name="form" :row="editing === 'new' ? null : editing" :close="close" :request-close="requestPanelClose" :set-dirty="(value: boolean, _key?: string) => markDirty(value)" :request-delete="() => undefined" />
        </ListForm>
    </section>
</template>
