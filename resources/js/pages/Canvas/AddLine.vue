<script setup lang="ts">
import { computed, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { t } from '../../i18n';
import { routes } from '../../routes';
import { firstError, type EntryElement } from './entry';

const props = defineProps<{ canvasId: number; parent: EntryElement; itemLabel: string; clusters: boolean }>();
const form = useForm({ name: '', cluster: false });
const error = computed(() => firstError(form.errors));
const inputId = computed(() => `add-line-${props.parent.id}`);
const label = computed(() => (props.clusters ? t('entry.add_line_activities', { parent: props.parent.name }) : t('entry.add_line_item', { kind: props.itemLabel, parent: props.parent.name })));
const choiceClass = 'inline-flex h-7 items-center rounded-badge px-2.5 text-[12.5px] text-secondary peer-checked:bg-ink peer-checked:font-semibold peer-checked:text-white peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ink';

function submit(): void {
    form.transform((data) => ({ kind: data.cluster ? 'cluster' : 'activity', area: props.parent.area, parent_id: props.parent.id, name: data.name })).post(routes.elements(props.canvasId), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: async () => {
            form.reset('name');
            await nextTick();
            document.getElementById(inputId.value)?.focus();
        },
    });
}
</script>

<template>
    <form novalidate @submit.prevent="submit">
        <div class="flex flex-wrap items-center gap-2 rounded-control border border-dashed bg-white p-1">
            <label :for="inputId" class="sr-only">{{ label }}</label>
            <input :id="inputId" v-model="form.name" type="text" autocomplete="off" :placeholder="label" :aria-describedby="error ? `${inputId}-error` : undefined" :aria-invalid="error ? true : undefined" class="h-dense min-w-0 flex-1 rounded-control bg-transparent px-2.5 text-[13px] text-ink placeholder:text-secondary" />
            <fieldset v-if="clusters" class="flex shrink-0 gap-1">
                <legend class="sr-only">{{ t('entry_design.kind_choice') }}</legend>
                <label class="relative"><input v-model="form.cluster" type="radio" name="kind" :value="false" class="peer absolute inset-0 cursor-pointer opacity-0" /><span :class="choiceClass">{{ itemLabel }}</span></label>
                <label class="relative"><input v-model="form.cluster" type="radio" name="kind" :value="true" class="peer absolute inset-0 cursor-pointer opacity-0" /><span :class="choiceClass">{{ t('entry.kind.cluster') }}</span></label>
            </fieldset>
        </div>
        <p v-if="error" :id="`${inputId}-error`" class="mt-1 text-[12.5px] font-medium text-danger">{{ error }}</p>
    </form>
</template>
