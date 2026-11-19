<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { t } from '../../i18n';
import FilterBar, { type FilterDef } from './FilterBar.vue';
import SortHeader from './SortHeader.vue';

export type ListRow = { id: number | string; name: string; archived_at?: string | null; [key: string]: unknown };
export type ListColumn = { key: string; label: string; sortable?: boolean };
export type ListState = { search: string; sort: string | null; direction: 'asc' | 'desc'; filters: Record<string, string>; archived: boolean };

const props = defineProps<{ title: string; columns: ListColumn[]; rows: ListRow[]; count: number; query: ListState; filters?: FilterDef[] }>();
const emit = defineEmits<{ change: [query: ListState] }>();

const SEARCH_DELAY_MS = 250;
const search = ref(props.query.search);
const searchField = ref<HTMLInputElement>();
let timer: ReturnType<typeof setTimeout> | undefined;

watch(() => props.query.search, (value) => (search.value = value));
onBeforeUnmount(() => clearTimeout(timer));

function change(patch: Partial<ListState>): void {
    emit('change', { ...props.query, ...patch });
}

function onSearch(): void {
    clearTimeout(timer);
    timer = setTimeout(() => change({ search: search.value }), SEARCH_DELAY_MS);
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
                <label for="list-search" class="text-[13px] font-semibold">{{ t('list.search') }}</label>
                <input id="list-search" ref="searchField" v-model="search" type="search" class="rounded-md border border-[#848B95] bg-white px-2 py-1.5 text-[13px]" @input="onSearch" />
            </div>
            <p role="status" aria-live="polite" aria-atomic="true" class="text-[12.5px] text-[#454C57]">{{ count === 1 ? t('list.count_one') : t('list.count', { count }) }}</p>
        </div>
        <FilterBar v-if="filters?.length" :filters="filters" :active="query.filters" @change="change({ filters: $event })" />
        <table class="w-full border-collapse text-[13px]">
            <caption class="sr-only">{{ title }}</caption>
            <thead>
                <tr>
                    <template v-for="column in columns" :key="column.key">
                        <SortHeader v-if="column.sortable" :label="column.label" :direction="directionOf(column.key)" @sort="sortBy(column.key)" />
                        <th v-else scope="col" class="px-2 py-1.5 text-left text-[12.5px] font-semibold">{{ column.label }}</th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.id" class="border-t border-[#D5D9DF]">
                    <td v-for="column in columns" :key="column.key" class="px-2 py-1.5">{{ row[column.key] }}</td>
                </tr>
                <tr v-if="rows.length === 0"><td :colspan="columns.length" class="px-2 py-3 text-[#454C57]">{{ t('list.empty') }}</td></tr>
            </tbody>
        </table>
    </section>
</template>
