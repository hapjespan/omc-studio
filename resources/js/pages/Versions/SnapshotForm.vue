<script setup lang="ts">
import { computed, nextTick, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import NativeSelect from '../../components/fields/NativeSelect.vue';
import TextArea from '../../components/fields/TextArea.vue';
import TextField from '../../components/fields/TextField.vue';
import AppButton from '../../components/ui/AppButton.vue';
import type { ListRow } from '../../components/list/ListView.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

const props = defineProps<{ row: ListRow | null; canvasId: number; years: number[] }>();
const emit = defineEmits<{ close: []; cancel: []; dirty: [value: boolean] }>();

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);
const draft = reactive({ name: String(props.row?.name ?? ''), fiscal_year: String(props.row?.fiscal_year ?? props.years.at(-1) ?? ''), remark: String(props.row?.remark ?? '') });
const initial = JSON.stringify(draft);
const busy = ref(false);
const touch = (): void => emit('dirty', JSON.stringify(draft) !== initial);

function submit(): void {
    const options = { preserveScroll: true, preserveState: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false), onSuccess: () => emit('close'), onError: () => nextTick(focusFirstInvalid) };
    if (props.row) router.patch(routes.snapshot(props.row.id), { name: draft.name, remark: draft.remark }, options);
    else router.post(routes.versions(props.canvasId), draft, options);
}
</script>

<template>
    <form novalidate class="flex flex-col gap-4" @submit.prevent="submit" @input="touch" @change="touch">
        <ErrorSummary :errors="errors" />
        <TextField id="name" v-model="draft.name" :label="t('fields.name')" :error="errors.name" @update:model-value="touch" />
        <NativeSelect v-if="!row" id="fiscal_year" v-model="draft.fiscal_year" :label="t('snap.field.year')" :error="errors.fiscal_year" :options="years.map((year) => ({ value: String(year), label: String(year) }))" />
        <TextArea id="remark" v-model="draft.remark" :label="`${t('fields.remark')} (${t('form.optional')})`" :error="errors.remark" @update:model-value="touch" />
        <div class="flex gap-2">
            <AppButton type="submit" variant="primary" :disabled="busy">{{ t('snap.save') }}</AppButton>
            <AppButton @click="emit('cancel')">{{ t('list.cancel') }}</AppButton>
        </div>
    </form>
</template>
