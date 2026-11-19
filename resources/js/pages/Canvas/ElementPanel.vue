<script setup lang="ts">
import { computed, nextTick, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import SelectField from '../../components/fields/SelectField.vue';
import TextArea from '../../components/fields/TextArea.vue';
import TextField from '../../components/fields/TextField.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import { t } from '../../i18n';
import { routes } from '../../routes';
import { detail } from '../../shell/detail';
import type { EntryElement } from './entry';

const props = defineProps<{ canvasId: number; element: EntryElement; path: string; list: boolean; icons: string[] }>();
const emit = defineEmits<{ saved: []; changed: [] }>();
const form = useForm({ name: props.element.name, description: props.element.description ?? '', icon: props.element.icon ?? '' });
const errors = computed(() => form.errors as Record<string, string>);
const iconOptions = computed(() => props.icons.map((icon) => ({ value: icon, label: t(`structure.icon.${icon}`) })));

watch(() => form.isDirty, (dirty) => {
    detail.dirty = dirty;
    if (dirty) emit('changed');
});

function save(): void {
    form.transform((data) => ({ name: data.name, description: data.description, ...(props.list ? { icon: data.icon || null } : {}) })).patch(routes.element(props.canvasId, props.element.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.defaults().reset();
            detail.dirty = false;
            emit('saved');
            void announce(t('entry.saved'));
        },
        onError: () => nextTick(focusFirstInvalid),
    });
}
</script>

<template>
    <Teleport to="#detail-body" defer>
        <form :key="element.id" novalidate class="flex flex-col gap-3" @submit.prevent="save">
            <div role="alert"><ErrorSummary :errors="errors" /></div>
            <p class="text-[12.5px] text-[#454C57]"><span class="font-semibold">{{ t('entry.path') }}:</span> {{ path }}</p>
            <TextField id="element-name" v-model="form.name" :label="t('fields.name')" :error="errors.name" autocomplete="off" />
            <TextArea id="element-description" v-model="form.description" :label="t('fields.description')" :error="errors.description" />
            <SelectField v-if="list" id="element-icon" v-model="form.icon" :label="t('entry.icon')" :options="iconOptions" :placeholder="t('entry.no_icon')" :error="errors.icon" />
            <div><button type="submit" :disabled="form.processing" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('entry.save') }}</button></div>
        </form>
    </Teleport>
</template>
