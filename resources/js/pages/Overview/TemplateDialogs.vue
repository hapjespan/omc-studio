<script lang="ts">
export type TemplateDialogKind = 'save' | 'rename' | 'delete';

export interface TemplateTarget {
    id: number;
    name: string;
    counts: { elements: number; statements: number } | null;
}
</script>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import TextField from '../../components/fields/TextField.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import Dialog from '../../components/overlays/Dialog.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

const emit = defineEmits<{ done: [kind: TemplateDialogKind] }>();

const open = ref(false);
const kind = ref<TemplateDialogKind>('save');
const target = ref<TemplateTarget | null>(null);
const form = useForm({ name: '', confirmation: '' });

const titles: Record<TemplateDialogKind, () => string> = {
    save: () => t('entry.save_template'),
    rename: () => t('canvas_dialog.rename_title'),
    delete: () => t('canvas_dialog.delete_title', { name: target.value?.name ?? '' }),
};
const description = computed(() => {
    if (kind.value !== 'delete' || !target.value?.counts) return undefined;
    const { elements, statements } = target.value.counts;

    return t('canvas_dialog.delete_description', { counts: [t('canvas_dialog.count_elements', { count: elements }), t('canvas_dialog.count_statements', { count: statements })].join(', ') });
});
const canSubmit = computed(() => !form.processing && (kind.value !== 'delete' || (form.confirmation !== '' && form.confirmation === target.value?.name)));
const labels: Record<TemplateDialogKind, string> = { save: 'save', rename: 'save', delete: 'delete' };

function show(next: TemplateDialogKind, picked: TemplateTarget): void {
    kind.value = next;
    target.value = picked;
    form.clearErrors();
    form.name = next === 'rename' ? picked.name : '';
    form.confirmation = '';
    form.defaults();
    open.value = true;
}

function submit(): void {
    const id = target.value?.id ?? 0;
    const options = { preserveScroll: true, onSuccess: () => {
            open.value = false;
            emit('done', kind.value);
        }, onError: () => nextTick(focusFirstInvalid) };
    if (kind.value === 'save') form.transform((data) => ({ source: 'save_template', name: data.name, canvas_id: id })).post(routes.canvases(), options);
    else if (kind.value === 'rename') form.transform((data) => ({ name: data.name })).patch(routes.template(id), options);
    else form.transform((data) => ({ confirmation: data.confirmation })).delete(routes.template(id), options);
}

defineExpose({ show });
</script>

<template>
    <Dialog v-model:open="open" :title="titles[kind]()" :description="description" :dirty="form.isDirty && kind !== 'delete'">
        <form novalidate class="flex flex-col gap-3" @submit.prevent="submit">
            <div role="alert"><ErrorSummary :errors="form.errors" /></div>
            <TextField v-if="kind !== 'delete'" id="template_name" v-model="form.name" :label="t('fields.name')" :error="form.errors.name" autocomplete="off" />
            <TextField v-else id="template_confirmation" v-model="form.confirmation" :label="t('canvas_dialog.confirmation')" :hint="t('canvas_dialog.confirmation_hint', { name: target?.name ?? '' })" :error="form.errors.confirmation" autocomplete="off" />
            <div class="flex gap-2">
                <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="open = false">{{ t('canvas_dialog.cancel') }}</button>
                <button type="submit" :disabled="!canSubmit" class="rounded-md border px-3 py-1.5 text-[13px] font-semibold disabled:opacity-60" :class="kind === 'delete' ? 'border-[#C0645A] text-[#A12A1E]' : 'border-[#848B95]'">{{ t(`canvas_dialog.${labels[kind]}`) }}</button>
            </div>
        </form>
    </Dialog>
</template>
