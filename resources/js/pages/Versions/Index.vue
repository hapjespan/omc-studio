<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import ListView, { type ListColumn, type ListRow, type ListState } from '../../components/list/ListView.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';
import SnapshotForm from './SnapshotForm.vue';

const props = defineProps<{ rows: ListRow[]; count: number; query: ListState; canWrite: boolean; years: number[] }>();

const page = usePage();
const canvasId = computed(() => (page.props.canvas as { id: number } | null)?.id ?? 0);
const list = ref<InstanceType<typeof ListView>>();
const keep = { preserveScroll: true, preserveState: true };

watch(() => page.props.flash, (flash) => (flash as { status?: string | null } | undefined)?.status && announce((flash as { status: string }).status));

const columns: ListColumn[] = [
    { key: 'name', label: t('fields.name'), sortable: true },
    { key: 'fiscal_year', label: t('snap.column.year'), sortable: true },
    { key: 'created', label: t('snap.column.created') },
    { key: 'maker', label: t('snap.column.maker') },
    { key: 'remark', label: t('snap.column.remark') },
];

const shownRows = computed(() => props.rows.map((row) => ({ ...row, remark: row.remark ?? '', maker: row.maker ?? '' })));

const consequence = (row: ListRow): string => t('snap.delete_consequence', { name: row.name });
const focusList = async (): Promise<void> => {
    await nextTick();
    list.value?.focusSearch();
};
const restore = (row: ListRow): void => router.post(routes.snapshotRestore(row.id), {}, { ...keep, onSuccess: focusList });
const archive = (row: ListRow, close: () => void): void => router.post(routes.snapshotArchive(row.id), {}, { ...keep, onSuccess: () => { close(); focusList(); } });
const remove = (row: ListRow): void => router.delete(routes.snapshot(row.id), { ...keep, data: { confirm: true } });

function load(query: ListState): void {
    router.get(routes.versions(canvasId.value), { search: query.search || undefined, sort: query.sort ?? undefined, direction: query.sort ? query.direction : undefined, archived: query.archived ? 1 : undefined }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <Head :title="t('snap.title')" />
    <h1 class="text-[20px] font-semibold">{{ t('snap.title') }}</h1>
    <div class="mt-3">
        <ListView ref="list" :title="t('snap.title')" :columns="columns" :rows="shownRows" :count="count" :query="query" archivable :can-delete="canWrite" :can-create="canWrite" :create-label="t('snap.create')" :create-title="t('snap.create')" :undoable="false" :consequence="consequence" @change="load" @delete="remove" @restore="restore">
            <template #cell="{ row, column }">
                <template v-if="column.key === 'name'">{{ row.name }}<span v-if="row.imported" class="ml-2 rounded border border-[#848B95] px-1 text-[11.5px]">{{ t('snap.imported') }}</span></template>
                <template v-else>{{ row[column.key] }}</template>
            </template>
            <template v-if="canWrite" #form="{ row, close, setDirty }">
                <SnapshotForm :key="row?.id ?? 'new'" :row="row" :canvas-id="canvasId" :years="years" @close="close" @dirty="setDirty" />
                <button v-if="row && !row.archived_at" type="button" class="mt-3 rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="archive(row, close)">{{ t('snap.archive') }}</button>
            </template>
        </ListView>
    </div>
</template>
