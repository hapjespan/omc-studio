<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import TextField from '../../components/fields/TextField.vue';
import ConfirmDelete from '../../components/list/ConfirmDelete.vue';
import SidePanel from '../../components/overlays/SidePanel.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

const props = defineProps<{ organisationId: number; years: { id: number; year: number }[]; writable: boolean }>();
const open = defineModel<boolean>('open', { default: false });

const page = usePage();
const errors = computed(() => ((page.props.errors as Record<string, unknown>).fiscalYears ?? {}) as Record<string, string>);
const year = ref('');
const removing = ref<{ id: number; year: number } | null>(null);
const confirmOpen = computed({ get: () => removing.value !== null, set: (value) => { if (!value) removing.value = null; } });
const options = { errorBag: 'fiscalYears', preserveScroll: true, preserveState: true };

function add(): void {
    router.post(routes.fiscalYears(props.organisationId), { year: year.value }, { ...options, onSuccess: () => (year.value = ''), onError: () => nextTick(focusFirstInvalid) });
}
const remove = (): void => {
    if (removing.value) router.delete(routes.fiscalYear(removing.value.id), options);
};
</script>

<template>
    <SidePanel v-model:open="open" :title="t('fiscal_years.title')">
        <div role="alert"><ErrorSummary :errors="errors" /></div>
        <form v-if="writable" novalidate class="mt-3 flex flex-col gap-3" @submit.prevent="add">
            <TextField id="fiscal-year-new" v-model="year" :label="t('fiscal_years.year_label')" :error="errors.year" />
            <div><button type="submit" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('fiscal_years.add') }}</button></div>
        </form>
        <ul class="mt-4 flex flex-col gap-2">
            <li v-for="item in years" :key="item.id" class="flex items-center gap-3 text-[13px]">
                <span>{{ item.year }}</span>
                <button v-if="writable" type="button" :aria-label="`${t('list.delete')} ${item.year}`" class="rounded-md border border-[#848B95] px-2 py-1 text-[12.5px]" @click="removing = item">{{ t('list.delete') }}</button>
            </li>
        </ul>
        <ConfirmDelete v-if="removing" v-model:open="confirmOpen" :name="String(removing.year)" :consequence="null" @confirm="remove" />
    </SidePanel>
</template>
