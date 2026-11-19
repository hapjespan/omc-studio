<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import Field from '../../components/fields/Field.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import { announce } from '../../components/feedback/status';
import TextField from '../../components/fields/TextField.vue';
import ListView, { type ListRow, type ListState } from '../../components/list/ListView.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

defineProps<{ rows: ListRow[]; count: number; query: ListState }>();

const page = usePage();
const list = ref<InstanceType<typeof ListView>>();
watch(() => page.props.flash, (flash) => (flash as { status?: string | null } | undefined)?.status && announce((flash as { status: string }).status));
const errors = computed(() => page.props.errors as Record<string, string>);
const listError = computed(() => (errors.value.rule ? { rule: errors.value.rule } : {}));
const columns = [{ key: 'name', label: t('fields.name'), sortable: true }];

function load(query: ListState): void {
    router.get(routes.organisations(), { search: query.search || undefined, sort: query.sort ?? undefined, direction: query.sort ? query.direction : undefined, archived: query.archived ? 1 : undefined }, { preserveState: true, preserveScroll: true, replace: true });
}

const remove = (row: ListRow): void => router.delete(routes.organisation(row.id), { preserveScroll: true });
const focusList = async (): Promise<void> => {
    await nextTick();
    list.value?.focusSearch();
};
const restore = (row: ListRow): void => router.post(routes.organisationRestore(row.id), {}, { preserveScroll: true, onSuccess: focusList });
const consequence = (): string => t('organisations.delete_consequence');

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
    const options = { forceFormData: true, preserveScroll: true, preserveState: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false), onSuccess: close, onError: () => nextTick(focusFirstInvalid) };
    const data = { name: draft.name, logo: draft.logo };
    if (row) router.post(routes.organisation(row.id), { ...data, _method: 'patch' }, options);
    else router.post(routes.organisations(), data, options);
}

function archive(row: ListRow, close: () => void): void {
    router.post(routes.organisationArchive(row.id), {}, { preserveScroll: true, onSuccess: () => { close(); focusList(); } });
}
</script>

<template>
    <Head :title="t('organisations.title')" />
    <h1 class="text-[20px] font-semibold">{{ t('organisations.title') }}</h1>
    <div v-if="listError.rule" role="alert" class="mt-3"><ErrorSummary :errors="listError" /></div>
    <div class="mt-3">
        <ListView ref="list" :title="t('organisations.title')" :columns="columns" :rows="rows" :count="count" :query="query" can-delete archivable require-name :undoable="false" :consequence="consequence" :create-title="t('organisations.new')" @change="load" @delete="remove" @restore="restore">
            <template #form="{ row, close, setDirty }">
                <form :key="row?.id ?? 'new'" novalidate class="flex flex-col gap-3" enctype="multipart/form-data" @vue:mounted="open(row, setDirty)" @submit.prevent="submit(row, close)">
                    <ErrorSummary :errors="errors" />
                    <TextField id="name" v-model="draft.name" :label="t('fields.name')" :error="errors.name" @update:model-value="setDirty(draft.name !== initial)" />
                    <Field id="logo" :label="`${t('organisations.logo')} (${t('form.optional')})`" :hint="t('organisations.logo_hint')" :error="errors.logo" v-slot="{ describedby, invalid }">
                        <img v-if="row?.has_logo" :src="routes.organisationLogo(row.id)" :alt="row.name" class="h-16 w-16 object-contain" />
                        <input id="logo" type="file" accept="image/png,image/jpeg" :aria-describedby="describedby" :aria-invalid="invalid || undefined" @change="draft.logo = ($event.target as HTMLInputElement).files?.[0] ?? null; setDirty(true)" />
                    </Field>
                    <div class="flex gap-2">
                        <button type="submit" :disabled="busy" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('organisations.save') }}</button>
                        <button v-if="row && !row.archived_at" type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="archive(row, close)">{{ t('organisations.archive') }}</button>
                    </div>
                </form>
            </template>
        </ListView>
    </div>
</template>
