<script setup lang="ts">
import { computed, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import TextField from '../../components/fields/TextField.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';
import { firstError, type EntryElement } from './entry';

const props = defineProps<{ canvasId: number; parent: EntryElement; itemLabel: string; clusters: boolean }>();
const form = useForm({ name: '', cluster: false });
const error = computed(() => firstError(form.errors));
const inputId = computed(() => `add-line-${props.parent.id}`);
const label = computed(() => (props.clusters ? t('entry.add_line_activities', { parent: props.parent.name }) : t('entry.add_line_item', { kind: props.itemLabel, parent: props.parent.name })));

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
    <form novalidate class="flex flex-wrap items-end gap-3" @submit.prevent="submit">
        <div class="min-w-0 flex-1"><TextField :id="inputId" v-model="form.name" :label="label" :error="error" autocomplete="off" /></div>
        <label v-if="clusters" class="flex items-center gap-2 py-2 text-[13px]"><input v-model="form.cluster" type="checkbox" />{{ t('entry.kind.cluster') }}</label>
    </form>
</template>
