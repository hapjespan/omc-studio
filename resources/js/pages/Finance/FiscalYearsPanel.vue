<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import TextField from '../../components/fields/TextField.vue';
import ConfirmDelete from '../../components/list/ConfirmDelete.vue';
import AppButton from '../../components/ui/AppButton.vue';
import Dialog from '../../components/overlays/Dialog.vue';
import SidePanel from '../../components/overlays/SidePanel.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

const props = defineProps<{ organisationId: number; years: { id: number; year: number }[]; writable: boolean; canvases?: { id: number; name: string }[]; finalised?: { canvas_id: number; year: number; at: string; by: string | null }[] }>();
const open = defineModel<boolean>('open', { default: false });

const page = usePage();
const errors = computed(() => ((page.props.errors as Record<string, unknown>).fiscalYears ?? {}) as Record<string, string>);
const year = ref('');
const removing = ref<{ id: number; year: number } | null>(null);
const confirmOpen = computed({ get: () => removing.value !== null, set: (value) => { if (!value) removing.value = null; } });
const reopening = ref<{ canvas: { id: number; name: string }; year: number } | null>(null);
const reopenOpen = computed({ get: () => reopening.value !== null, set: (value) => { if (!value) reopening.value = null; } });
const stateOf = (canvasId: number, year: number) => props.finalised?.find((row) => row.canvas_id === canvasId && row.year === year);
function reopen(): void {
    if (reopening.value) router.post(routes.reopenYear(reopening.value.canvas.id, reopening.value.year), { confirm: true }, { ...options, onFinish: () => (reopening.value = null) });
}
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
            <div><AppButton type="submit" variant="primary">{{ t('fiscal_years.add') }}</AppButton></div>
        </form>
        <ul class="mt-4 flex flex-col gap-2">
            <li v-for="item in years" :key="item.id" class="flex items-center gap-3 text-[13px]">
                <span>{{ item.year }}</span>
                <AppButton v-if="writable" size="small" variant="danger" :aria-label="`${t('list.delete')} ${item.year}`" @click="removing = item">{{ t('list.delete') }}</AppButton>
                <ul v-if="canvases?.length" :aria-label="`${t('fiscal_years.canvases')} ${item.year}`" class="flex flex-col gap-1">
                    <li v-for="canvas in canvases" :key="canvas.id" class="flex items-center gap-2">
                        <span>{{ canvas.name }}:</span>
                        <template v-if="stateOf(canvas.id, item.year)">
                            <span>{{ stateOf(canvas.id, item.year)?.by ? t('fiscal_years.canvas_final', { date: stateOf(canvas.id, item.year)?.at ?? '', name: stateOf(canvas.id, item.year)?.by ?? '' }) : t('fiscal_years.canvas_final_imported', { date: stateOf(canvas.id, item.year)?.at ?? '' }) }}</span>
                            <AppButton v-if="writable" size="small" :aria-label="`${t('fiscal_years.reopen')} ${item.year} ${canvas.name}`" @click="reopening = { canvas, year: item.year }">{{ t('fiscal_years.reopen') }}</AppButton>
                        </template>
                        <span v-else>{{ t('fiscal_years.canvas_open') }}</span>
                    </li>
                </ul>
            </li>
        </ul>
        <ConfirmDelete v-if="removing" v-model:open="confirmOpen" :name="String(removing.year)" :consequence="null" @confirm="remove" />
        <Dialog v-if="reopening" v-model:open="reopenOpen" :title="t('fiscal_years.reopen_title', { year: reopening.year, canvas: reopening.canvas.name })" :description="t('fiscal_years.reopen_description')">
            <div class="mt-3 flex gap-2">
                <AppButton @click="reopenOpen = false">{{ t('list.cancel') }}</AppButton>
                <AppButton variant="primary" @click="reopen">{{ t('fiscal_years.reopen_confirm') }}</AppButton>
            </div>
        </Dialog>
    </SidePanel>
</template>
