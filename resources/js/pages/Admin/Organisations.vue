<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import TextField from '../../components/fields/TextField.vue';
import ListView, { type ListRow, type ListState } from '../../components/list/ListView.vue';
import { t } from '../../i18n';

defineProps<{ rows: ListRow[]; count: number; query: ListState }>();

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);
const listError = computed(() => (errors.value.rule ? { rule: errors.value.rule } : {}));
const columns = [{ key: 'name', label: t('fields.name'), sortable: true }];

function load(query: ListState): void {
    router.get('/admin/organisations', { search: query.search || undefined, sort: query.sort ?? undefined, direction: query.sort ? query.direction : undefined, archived: query.archived ? 1 : undefined }, { preserveState: true, preserveScroll: true, replace: true });
}

const base = (row: ListRow): string => `/admin/organisations/${row.id}`;
const remove = (row: ListRow): void => router.delete(base(row), { preserveScroll: true });
const restore = (row: ListRow): void => router.post(`${base(row)}/restore`, {}, { preserveScroll: true });

const draft = reactive<{ name: string; logo: File | null }>({ name: '', logo: null });
const busy = ref(false);
const initial = ref('');

function open(row: ListRow | null, setDirty: (value: boolean) => void): void {
    draft.name = row?.name ?? '';
    draft.logo = null;
    initial.value = draft.name;
    setDirty(false);
}

function submit(row: ListRow | null, close: () => void): void {
    const options = { forceFormData: true, preserveScroll: true, preserveState: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false), onSuccess: close };
    const data = { name: draft.name, logo: draft.logo };
    if (row) router.post(base(row), { ...data, _method: 'patch' }, options);
    else router.post('/admin/organisations', data, options);
}

function archive(row: ListRow, close: () => void): void {
    router.post(`${base(row)}/archive`, {}, { preserveScroll: true, onSuccess: close });
}
</script>

<template>
    <Head :title="t('organisations.title')" />
    <h1 class="text-[20px] font-semibold">{{ t('organisations.title') }}</h1>
    <ErrorSummary v-if="listError.rule" :errors="listError" class="mt-3" />
    <div class="mt-3">
        <ListView :title="t('organisations.title')" :columns="columns" :rows="rows" :count="count" :query="query" can-delete archivable require-name :undoable="false" :create-title="t('organisations.new')" @change="load" @delete="remove" @restore="restore">
            <template #form="{ row, close, setDirty }">
                <form :key="row?.id ?? 'new'" novalidate class="flex flex-col gap-3" enctype="multipart/form-data" @vue:mounted="open(row, setDirty)" @submit.prevent="submit(row, close)">
                    <ErrorSummary :errors="errors" />
                    <TextField id="name" v-model="draft.name" :label="t('fields.name')" :error="errors.name" @update:model-value="setDirty(draft.name !== initial)" />
                    <div class="flex flex-col gap-1">
                        <label for="logo" class="text-[13px] font-semibold">{{ t('organisations.logo') }} <span class="font-normal text-[#5F6772]">({{ t('form.optional') }})</span></label>
                        <p id="logo-hint" class="text-[11.5px] text-[#5F6772]">{{ t('organisations.logo_hint') }}</p>
                        <img v-if="row?.has_logo" :src="`/organisations/${row.id}/logo`" :alt="row.name" class="h-16 w-16 object-contain" />
                        <input id="logo" type="file" accept="image/png,image/jpeg" aria-describedby="logo-hint" :aria-invalid="errors.logo ? true : undefined" @change="draft.logo = ($event.target as HTMLInputElement).files?.[0] ?? null; setDirty(true)" />
                        <p v-if="errors.logo" id="logo-error" class="text-[12.5px] font-medium text-[#A12A1E]">{{ errors.logo }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" :disabled="busy" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('organisations.save') }}</button>
                        <button v-if="row && !row.archived_at" type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="archive(row, close)">{{ t('organisations.archive') }}</button>
                    </div>
                </form>
            </template>
        </ListView>
    </div>
</template>
