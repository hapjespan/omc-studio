<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import AppButton from '../../components/ui/AppButton.vue';
import Pill from '../../components/ui/Pill.vue';
import ListView, { type ListColumn, type ListRow, type ListState } from '../../components/list/ListView.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';
import SnapshotForm from './SnapshotForm.vue';

const props = defineProps<{ rows: ListRow[]; count: number; query: ListState; canWrite: boolean; years: number[] }>();

const page = usePage();
const canvasId = computed(() => (page.props.canvas as { id: number } | null)?.id ?? 0);
const list = ref<InstanceType<typeof ListView>>();
const dock = ref<HTMLElement>();
const keep = { preserveScroll: true, preserveState: true };

watch(() => page.props.flash, (flash) => (flash as { status?: string | null } | undefined)?.status && announce((flash as { status: string }).status));

const columns: ListColumn[] = [
    { key: 'name', label: t('fields.name'), sortable: true },
    { key: 'fiscal_year', label: t('snap.column.year'), sortable: true },
    { key: 'created', label: t('snap.column.created') },
    { key: 'maker', label: t('snap.column.maker') },
    { key: 'remark', label: t('snap.column.remark') },
    { key: 'importedText', label: t('snap.imported') },
    { key: 'open', label: t('snap.open') },
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
    <div class="grid items-start gap-x-4 gap-y-4 lg:grid-cols-[minmax(0,1fr)_auto]">
        <div class="flex min-w-0 flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="font-display text-[20px] font-semibold tracking-[-0.2px]">{{ t('snap.title') }}</h1>
                <AppButton v-if="canWrite" variant="primary" icon="plus" @click="list?.create($event.currentTarget as HTMLElement)">{{ t('snap.create') }}</AppButton>
            </div>
            <ListView ref="list" :title="t('snap.title')" :columns="columns" :rows="shownRows" :count="count" :query="query" :dock="dock" archivable :can-delete="canWrite" :can-create="canWrite" :create-title="t('snap.create')" :undoable="false" :consequence="consequence" @change="load" @delete="remove" @restore="restore">
                <template #cell="{ row, column }">
                    <template v-if="column.key === 'importedText'"><Pill v-if="row.imported">{{ t('snap.imported') }}</Pill></template>
                    <Link v-else-if="column.key === 'open'" :href="routes.snapshotView(row.id)" class="rounded-control font-medium text-accent underline">{{ t('snap.open') }}<span class="sr-only"> {{ row.name }}</span></Link>
                    <span v-else-if="column.key === 'name'" class="font-semibold">{{ row.name }}</span>
                    <span v-else-if="column.key === 'remark' || column.key === 'maker'" :class="row[column.key] ? '' : 'text-muted'">{{ row[column.key] || t('snap.none') }}</span>
                    <template v-else>{{ row[column.key] }}</template>
                </template>
                <template v-if="canWrite" #form="{ row, close, requestClose, setDirty, requestDelete }">
                    <SnapshotForm :key="row?.id ?? 'new'" :row="row" :canvas-id="canvasId" :years="years" @close="close" @cancel="requestClose" @dirty="setDirty" />
                    <div v-if="row" class="mt-4 border-t border-hairline pt-4">
                        <p class="text-[12.5px] text-secondary">{{ consequence(row) }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <AppButton v-if="!row.archived_at" @click="archive(row, close)">{{ t('snap.archive') }}</AppButton>
                            <AppButton variant="danger" @click="requestDelete">{{ t('list.delete') }}</AppButton>
                        </div>
                    </div>
                </template>
            </ListView>
        </div>
        <aside ref="dock" :aria-label="t('snap.panel')" class="w-full empty:hidden lg:w-detail-panel" />
    </div>
</template>
