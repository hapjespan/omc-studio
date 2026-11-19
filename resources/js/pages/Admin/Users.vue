<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import SelectField from '../../components/fields/SelectField.vue';
import TextField from '../../components/fields/TextField.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import { announce } from '../../components/feedback/status';
import ConfirmDelete from '../../components/list/ConfirmDelete.vue';
import ListView, { type ListRow, type ListState } from '../../components/list/ListView.vue';
import Dialog from '../../components/overlays/Dialog.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

const props = defineProps<{ rows: ListRow[]; count: number; query: ListState; oneTimePassword: string | null }>();

const page = usePage();
const list = ref<InstanceType<typeof ListView>>();
watch(() => page.props.flash, (flash) => (flash as { status?: string | null } | undefined)?.status && announce((flash as { status: string }).status));
const errors = computed(() => page.props.errors as Record<string, string>);
const listError = computed(() => (errors.value.rule ? { rule: errors.value.rule } : {}));
const columns = [
    { key: 'name', label: t('fields.name'), sortable: true },
    { key: 'email', label: t('fields.email'), sortable: true },
    { key: 'roleLabel', label: t('users.role') },
];
const roleOptions = [
    { value: 'administrator', label: t('users.role_administrator') },
    { value: 'reader', label: t('users.role_reader') },
];
const rows = computed(() => props.rows.map((row) => ({ ...row, roleLabel: t(`users.role_${String(row.role)}`) })));

const forget = (): void => {
    if (props.oneTimePassword) router.clearHistory();
};
onMounted(() => window.addEventListener('pagehide', forget));
onBeforeUnmount(() => {
    window.removeEventListener('pagehide', forget);
    forget();
});

const isSelf = (row: ListRow | null): boolean => Boolean(row?.isSelf);
const hasSecondFactor = (row: ListRow): boolean => Boolean(row.hasSecondFactor);

function load(query: ListState): void {
    router.get(routes.users(), { search: query.search || undefined, sort: query.sort ?? undefined, direction: query.sort ? query.direction : undefined }, { preserveState: true, preserveScroll: true, replace: true });
}

const focusList = async (): Promise<void> => {
    await nextTick();
    list.value?.focusSearch();
};

const draft = reactive({ name: '', email: '', role: 'reader', currentPassword: '' });
const initial = reactive({ name: '', email: '', role: 'reader' });
const busy = ref(false);
const confirmingDelete = ref(false);
const confirmingSessions = ref(false);
const confirmingFactor = ref(false);
const changed = (): boolean => draft.name !== initial.name || draft.email !== initial.email || draft.role !== initial.role;
const reauthenticating = (row: ListRow | null): boolean => isSelf(row) && draft.email !== initial.email;

function open(row: ListRow | null, setDirty: (value: boolean) => void): void {
    initial.name = String(row?.name ?? '');
    initial.email = String(row?.email ?? '');
    initial.role = String(row?.role ?? 'reader');
    Object.assign(draft, initial, { currentPassword: '' });
    confirmingDelete.value = false;
    confirmingSessions.value = false;
    confirmingFactor.value = false;
    setDirty(false);
}

function submit(row: ListRow | null, close: () => void): void {
    const data = { name: draft.name, email: draft.email, role: draft.role, current_password: draft.currentPassword };
    const options = { preserveScroll: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false), onSuccess: close, onError: () => nextTick(focusFirstInvalid) };
    if (row) router.patch(routes.user(row.id), data, options);
    else router.post(routes.users(), data, options);
}

function remove(row: ListRow, close: () => void): void {
    router.delete(routes.user(row.id), { preserveScroll: true, onSuccess: () => { close(); focusList(); } });
}

function resetPassword(row: ListRow, close: () => void): void {
    router.post(routes.userPasswordReset(row.id), {}, { preserveScroll: true, onSuccess: close });
}

function resetSecondFactor(row: ListRow, close: () => void): void {
    confirmingFactor.value = false;
    router.delete(routes.userSecondFactor(row.id), { preserveScroll: true, onSuccess: close });
}

function endSessions(row: ListRow, close: () => void): void {
    confirmingSessions.value = false;
    router.delete(routes.userSessions(row.id), { preserveScroll: true, onSuccess: close });
}

async function copyPassword(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.oneTimePassword ?? '');
        await announce(t('users.password_copied'));
    } catch {
        await announce(t('users.password_copy_failed'));
    }
}
</script>

<template>
    <Head :title="t('users.title')" />
    <h1 class="text-[20px] font-semibold">{{ t('users.title') }}</h1>
    <div v-if="listError.rule" role="alert" class="mt-3"><ErrorSummary :errors="listError" /></div>
    <section v-if="oneTimePassword" role="region" aria-labelledby="one-time-password-title" class="mt-3 flex flex-col gap-2 rounded-md border border-[#848B95] p-3">
        <h2 id="one-time-password-title" class="text-[14px] font-semibold">{{ t('users.one_time_password_title') }}</h2>
        <p class="text-[12.5px] text-[#454C57]">{{ t('users.one_time_password_hint') }}</p>
        <code data-testid="one-time-password" class="select-all font-mono text-[13px]">{{ oneTimePassword }}</code>
        <div><button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="copyPassword">{{ t('users.copy') }}</button></div>
    </section>
    <div class="mt-3">
        <ListView ref="list" :title="t('users.title')" :columns="columns" :rows="rows" :count="count" :query="query" :undoable="false" :can-delete="false" :create-title="t('users.new')" @change="load">
            <template #form="{ row, close, setDirty }">
                <form :key="row?.id ?? 'new'" novalidate class="flex flex-col gap-3" @vue:mounted="open(row, setDirty)" @submit.prevent="submit(row, close)">
                    <div role="alert"><ErrorSummary :errors="errors" /></div>
                    <TextField id="name" v-model="draft.name" :label="t('fields.name')" :error="errors.name" @update:model-value="setDirty(changed())" />
                    <TextField id="email" v-model="draft.email" :label="t('fields.email')" type="email" autocomplete="email" :error="errors.email" @update:model-value="setDirty(changed())" />
                    <TextField v-if="reauthenticating(row)" id="current_password" v-model="draft.currentPassword" :label="t('fields.current_password')" type="password" autocomplete="current-password" :error="errors.current_password" />
                    <SelectField id="role" v-model="draft.role" :label="t('users.role')" :options="roleOptions" :disabled="isSelf(row)" :hint="isSelf(row) ? t('users.own_account') : undefined" :error="errors.role" @update:model-value="setDirty(changed())" />
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" :disabled="busy" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('users.save') }}</button>
                        <template v-if="row && !isSelf(row)">
                            <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="resetPassword(row, close)">{{ t('users.reset_password') }}</button>
                            <button v-if="hasSecondFactor(row)" type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="confirmingFactor = true">{{ t('users.reset_second_factor') }}</button>
                            <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="confirmingSessions = true">{{ t('users.end_sessions') }}</button>
                        </template>
                        <button v-if="row" type="button" :disabled="isSelf(row)" :aria-describedby="isSelf(row) ? 'role-hint' : undefined" class="rounded-md border border-[#C0645A] px-3 py-1.5 text-[13px] text-[#A12A1E] disabled:opacity-60" @click="confirmingDelete = true">{{ t('list.delete') }}</button>
                    </div>
                    <ConfirmDelete v-if="row" v-model:open="confirmingDelete" :name="String(row.name)" :consequence="t('users.delete_consequence')" @confirm="remove(row, close)" />
                    <Dialog v-if="row" v-model:open="confirmingFactor" :title="t('users.reset_second_factor_title', { name: String(row.name) })" :description="t('users.reset_second_factor_consequence')">
                        <div class="flex gap-2">
                            <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="confirmingFactor = false">{{ t('list.cancel') }}</button>
                            <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="resetSecondFactor(row, close)">{{ t('users.reset_second_factor') }}</button>
                        </div>
                    </Dialog>
                    <Dialog v-if="row" v-model:open="confirmingSessions" :title="t('users.end_sessions_title', { name: String(row.name) })" :description="t('users.end_sessions_consequence')">
                        <div class="flex gap-2">
                            <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="confirmingSessions = false">{{ t('list.cancel') }}</button>
                            <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="endSessions(row, close)">{{ t('users.end_sessions') }}</button>
                        </div>
                    </Dialog>
                </form>
            </template>
        </ListView>
    </div>
</template>
