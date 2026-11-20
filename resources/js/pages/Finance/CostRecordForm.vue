<script setup lang="ts">
import { computed, nextTick, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import NativeSelect from '../../components/fields/NativeSelect.vue';
import Field from '../../components/fields/Field.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import TextArea from '../../components/fields/TextArea.vue';
import TextField from '../../components/fields/TextField.vue';
import type { ListRow } from '../../components/list/ListView.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

type Option = { id: number; name: string };

const props = defineProps<{ row: ListRow | null; organisationId: number; owners: Option[]; applications: Option[]; costKinds: string[] }>();
const emit = defineEmits<{ close: []; cancel: []; dirty: [value: boolean] }>();

const page = usePage();
const errors = computed(() => Object.fromEntries(Object.entries(page.props.errors as Record<string, unknown>).filter(([key]) => key !== 'figures')) as Record<string, string>);
const text = (key: string): string => String(props.row?.[key] ?? '');
const draft = reactive({ code: text('code'), name: text('name'), cost_kind: text('cost_kind'), supplier: text('supplier'), owner_id: text('owner_id'), cost_centre: text('cost_centre'), application_id: text('application_id'), description: text('description') });
const initial = JSON.stringify(draft);
const busy = ref(false);
const archived = computed(() => Boolean(props.row?.archived_at));
const touch = (): void => emit('dirty', JSON.stringify(draft) !== initial);

function submit(): void {
    const data = { ...draft, owner_id: draft.owner_id || null, application_id: draft.application_id || null };
    const options = { preserveScroll: true, preserveState: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false), onSuccess: () => emit('close'), onError: () => nextTick(focusFirstInvalid) };
    if (props.row) router.patch(routes.costRecord(props.row.id), data, options);
    else router.post(routes.costRecords(props.organisationId), data, options);
}
</script>

<template>
    <form novalidate class="flex flex-col gap-3" @submit.prevent="submit" @input="touch" @change="touch">
        <ErrorSummary :errors="errors" />
        <p v-if="archived" role="note" class="text-[13px]">{{ t('cost_records.archived_readonly') }}</p>
        <fieldset :disabled="archived" class="flex flex-col gap-3">
            <TextField id="name" v-model="draft.name" :label="t('fields.name')" :error="errors.name" @update:model-value="touch" />
            <div class="grid items-end gap-3 sm:grid-cols-2">
                <TextField id="code" v-model="draft.code" :label="`${t('fields.code')} (${t('form.optional')})`" :error="errors.code" @update:model-value="touch" />
                <Field id="cost_kind" :label="t('fields.cost_kind')" :error="errors.cost_kind" v-slot="{ describedby, invalid }">
                    <NativeSelect id="cost_kind" v-model="draft.cost_kind" :aria-describedby="describedby" :aria-invalid="invalid || undefined">
                            <option value="" disabled>{{ t('form.choose') }}</option>
                            <option v-for="kind in costKinds" :key="kind" :value="kind">{{ t(`cost_kind.${kind}`) }}</option>
                        </NativeSelect>
                </Field>
            </div>
            <Field id="owner_id" :label="t('fields.owner')" :hint="t('cost_records.owner_hint')" :error="errors.owner_id" v-slot="{ describedby, invalid }">
                <NativeSelect id="owner_id" v-model="draft.owner_id" :aria-describedby="describedby" :aria-invalid="invalid || undefined">
                        <option value="" disabled>{{ t('form.choose') }}</option>
                        <option v-for="owner in owners" :key="owner.id" :value="String(owner.id)">{{ owner.name }}</option>
                    </NativeSelect>
            </Field>
            <Field id="application_id" :label="`${t('fields.application')} (${t('form.optional')})`" :error="errors.application_id" v-slot="{ describedby, invalid }">
                <NativeSelect id="application_id" v-model="draft.application_id" :aria-describedby="describedby" :aria-invalid="invalid || undefined">
                        <option value="">{{ t('form.none') }}</option>
                        <option v-for="application in applications" :key="application.id" :value="String(application.id)">{{ application.name }}</option>
                    </NativeSelect>
            </Field>
            <div class="grid items-end gap-3 sm:grid-cols-2">
                <TextField id="supplier" v-model="draft.supplier" :label="`${t('fields.supplier')} (${t('form.optional')})`" :error="errors.supplier" @update:model-value="touch" />
                <TextField id="cost_centre" v-model="draft.cost_centre" :label="`${t('fields.cost_centre')} (${t('form.optional')})`" :error="errors.cost_centre" @update:model-value="touch" />
            </div>
            <TextArea id="description" v-model="draft.description" :rows="3" :label="`${t('fields.description')} (${t('form.optional')})`" :error="errors.description" @update:model-value="touch" />
        </fieldset>
        <div v-if="!archived" class="flex gap-2">
            <AppButton type="submit" variant="primary" :disabled="busy">{{ t('cost_records.save') }}</AppButton>
            <AppButton @click="emit('cancel')">{{ t('list.cancel') }}</AppButton>
        </div>
    </form>
</template>
