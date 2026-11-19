<script lang="ts">
import type { ListRow, ListState } from './ListView.vue';

export type EntryRow = ListRow & { cost_records_count: number; supplier?: string | null };
export type EntriesProps = { rows: EntryRow[]; count: number; query: ListState; organisation: { id: number; name: string; archived_at: string | null }; organisations: { id: number; name: string; archived_at: string | null }[]; others: { id: number; name: string }[] };
</script>

<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ErrorSummary from '../fields/ErrorSummary.vue';
import SelectField from '../fields/SelectField.vue';
import TextField from '../fields/TextField.vue';
import { focusFirstInvalid } from '../fields/focusFirstInvalid';
import { announce } from '../feedback/status';
import Dialog from '../overlays/Dialog.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';
import ListView, { type UndoState } from './ListView.vue';

type Kind = 'owners' | 'applications';
type Row = EntryRow;

const props = defineProps<EntriesProps & { kind: Kind }>();

const page = usePage();
const owners = computed(() => props.kind === 'owners');
const readonly = computed(() => props.organisation.archived_at !== null);
const errors = computed(() => page.props.errors as Record<string, string>);
const listError = computed(() => (errors.value.rule ? { rule: errors.value.rule } : {}));
const title = computed(() => t(`lists.${props.kind}.title`));
const columns = computed(() => [
    { key: 'name', label: t('fields.name'), sortable: true },
    ...(owners.value ? [] : [{ key: 'supplier', label: t('fields.supplier') }]),
    { key: 'cost_records_count', label: t('lists.cost_records') },
]);
const undo = ref<UndoState | null>(null);

watch(() => page.props.flash, (flash) => {
    const current = flash as { status?: string | null; undo?: string | null } | undefined;
    if (current?.status) announce(current.status);
    if (current?.undo) undo.value = { id: current.undo, message: current.status ?? '' };
});

function load(query: ListState): void {
    router.get(routes.organisationEntries(props.organisation.id, props.kind), { search: query.search || undefined, sort: query.sort ?? undefined, direction: query.sort ? query.direction : undefined }, { preserveState: true, preserveScroll: true, replace: true });
}

function restore(): void {
    if (!undo.value) return;
    router.post(routes.undo(String(undo.value.id)), {}, { preserveScroll: true, onSuccess: () => (undo.value = null), onError: (bag) => undo.value && (undo.value = { ...undo.value, refusal: bag.rule ?? t('rule.undo_unavailable') }) });
}

const replacing = ref<Row | null>(null);
const replaceOpen = computed({ get: () => replacing.value !== null, set: (open) => { if (!open) replacing.value = null; } });
const replacement = ref('');
const NONE = 'none';
const choices = computed(() => [
    ...props.others.filter((other) => other.id !== replacing.value?.id).map((other) => ({ value: String(other.id), label: other.name })),
    ...(owners.value ? [] : [{ value: NONE, label: t('lists.no_application') }]),
]);

function remove(row: ListRow): void {
    const entry = row as Row;
    if (entry.cost_records_count > 0) {
        replacement.value = '';
        replacing.value = entry;
        return;
    }
    router.delete(routes.organisationEntry(props.organisation.id, props.kind, entry.id), { preserveScroll: true });
}

function replace(): void {
    const row = replacing.value;
    if (!row || replacement.value === '') return;
    router.delete(routes.organisationEntry(props.organisation.id, props.kind, row.id), { data: { replace_with: replacement.value }, preserveScroll: true, onSuccess: () => (replacing.value = null) });
}

const consequence = (row: ListRow): string | null => {
    const used = (row as Row).cost_records_count;
    return used > 0 ? t(`lists.${props.kind}.in_use`, { count: used }) : null;
};

const draft = reactive({ name: '', supplier: '' });
const initial = ref('');
const busy = ref(false);

function open(row: Row | null, setDirty: (value: boolean) => void): void {
    draft.name = row?.name ?? '';
    draft.supplier = row?.supplier ?? '';
    initial.value = JSON.stringify(draft);
    setDirty(false);
}

function submit(row: Row | null, close: () => void): void {
    const options = { preserveScroll: true, preserveState: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false), onSuccess: close, onError: () => nextTick(focusFirstInvalid) };
    const data = owners.value ? { name: draft.name } : { name: draft.name, supplier: draft.supplier };
    if (row) router.patch(routes.organisationEntry(props.organisation.id, props.kind, row.id), data, options);
    else router.post(routes.organisationEntries(props.organisation.id, props.kind), data, options);
}
</script>

<template>
    <Head :title="title" />
    <h1 class="text-[20px] font-semibold">{{ title }}</h1>
    <nav :aria-label="t('lists.choose_organisation')" class="mt-2">
        <ul class="flex flex-wrap gap-2 text-[13px]">
            <li v-for="option in organisations" :key="option.id"><Link :href="routes.organisationEntries(option.id, kind)" :aria-current="option.id === organisation.id ? 'page' : undefined" class="rounded-md border border-[#848B95] px-2 py-1" :class="{ 'font-semibold underline': option.id === organisation.id }">{{ option.name }}<template v-if="option.archived_at"> ({{ t('list.archived') }})</template></Link></li>
        </ul>
    </nav>
    <div v-if="listError.rule" role="alert" class="mt-3"><ErrorSummary :errors="listError" /></div>
    <div class="mt-3">
        <ListView :title="title" :columns="columns" :rows="rows" :count="count" :query="query" :can-create="!readonly" :can-delete="!readonly" :undo="undo" :consequence="consequence" :create-title="t(`lists.${kind}.new`)" @change="load" @delete="remove" @undo="restore" @dismiss-undo="undo = null">
            <template #form="{ row, close, setDirty }">
                <form :key="row?.id ?? 'new'" novalidate class="flex flex-col gap-3" @vue:mounted="open(row as Row | null, setDirty)" @submit.prevent="submit(row as Row | null, close)">
                    <ErrorSummary :errors="errors" />
                    <TextField id="name" v-model="draft.name" :label="t('fields.name')" :hint="owners ? t('lists.owners.name_hint') : undefined" :error="errors.name" @update:model-value="setDirty(JSON.stringify(draft) !== initial)" />
                    <TextField v-if="!owners" id="supplier" v-model="draft.supplier" :label="`${t('fields.supplier')} (${t('form.optional')})`" :error="errors.supplier" @update:model-value="setDirty(JSON.stringify(draft) !== initial)" />
                    <div class="flex gap-2">
                        <button v-if="!readonly" type="submit" :disabled="busy" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('organisations.save') }}</button>
                    </div>
                </form>
            </template>
        </ListView>
    </div>
    <Dialog v-if="replacing" v-model:open="replaceOpen" :title="t('lists.replace_title', { name: replacing.name })" :description="t(`lists.${kind}.in_use`, { count: replacing.cost_records_count })">
        <SelectField id="replace-with" v-model="replacement" :label="t('lists.replace_with')" :options="choices" :error="errors.rule || errors.replace_with" />
        <div class="mt-3 flex gap-2">
            <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="replaceOpen = false">{{ t('list.cancel') }}</button>
            <button type="button" :disabled="replacement === ''" class="rounded-md border border-[#C0645A] px-3 py-1.5 text-[13px] text-[#A12A1E] disabled:opacity-60" @click="replace">{{ t('lists.replace_confirm') }}</button>
        </div>
    </Dialog>
</template>
