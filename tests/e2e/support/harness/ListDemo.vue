<script setup lang="ts">
import { computed, ref } from 'vue';
import ListDemoForm from './ListDemoForm.vue';
import ListView, { type ListRow, type ListState, type UndoState } from '../../../../resources/js/components/list/ListView.vue';

const all = ref<ListRow[]>(Array.from({ length: 12 }, (_, index) => ({ id: index + 1, name: index === 0 ? 'Anna Zorg' : `Persoon ${String.fromCharCode(66 + index)}`, email: `p${index + 1}@example.test`, group: index % 3 === 0 ? 'a' : 'b' })));
const columns = [{ key: 'name', label: 'Naam', sortable: true }, { key: 'email', label: 'E-mailadres', sortable: true }];
const filterDefs = [{ name: 'group', label: 'Groep', options: [{ value: 'a', label: 'Groep A' }, { value: 'b', label: 'Groep B' }] }];
const typedName = new URLSearchParams(location.search).get('typed') === '1';
const consequence = (row: ListRow): string | null => (row.id === 1 ? 'Verwijdert ook 4 jaarwaarden en 6 toewijzingssets' : null);
const withUndo = new URLSearchParams(location.search).get('undo') === '1';
const conflict = new URLSearchParams(location.search).get('conflict') === '1';
const undo = ref<UndoState | null>(null);
let removed: ListRow | null = null;
const query = ref<ListState>({ search: '', sort: null, direction: 'asc', filters: {}, archived: false });

function save(row: ListRow | null, name: string): void {
    if (row) row.name = name;
    else all.value.push({ id: all.value.length + 1, name, email: 'nieuw@example.test', group: 'b' });
}

function remove(row: ListRow): void {
    all.value = all.value.filter((candidate) => candidate.id !== row.id);
    if (!withUndo) return;
    removed = row;
    undo.value = { id: row.id, message: `${row.name} verwijderd` };
}

function restore(): void {
    if (conflict && undo.value) {
        undo.value = { ...undo.value, refusal: 'Ongedaan maken kan niet meer' };
        return;
    }
    if (removed) all.value.push(removed);
    undo.value = null;
}

const visible = computed(() => {
    const needle = query.value.search.toLowerCase();
    const rows = all.value.filter((row) => (!query.value.filters.group || row.group === query.value.filters.group) && (!needle || [row.name, row.email].some((value) => String(value).toLowerCase().includes(needle))));
    const key = query.value.sort;
    if (!key) return rows;
    const factor = query.value.direction === 'asc' ? 1 : -1;
    return [...rows].sort((a, b) => String(a[key]).localeCompare(String(b[key])) * factor);
});
</script>

<template>
    <main class="p-4">
        <h1>Lijst</h1>
        <ListView title="Personen" :columns="columns" :rows="visible" :count="visible.length" :query="query" :filters="filterDefs" can-delete :consequence="consequence" :require-name="typedName" @delete="remove" :undo="undo" @undo="restore" @dismiss-undo="undo = null" @change="query = $event">
            <template #form="{ row, close, setDirty }">
                <ListDemoForm :initial="row?.name ?? ''" @dirty="setDirty" @save="(name) => { save(row, name); close(); }" />
            </template>
        </ListView>
    </main>
</template>
