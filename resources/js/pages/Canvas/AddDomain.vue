<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import TextField from '../../components/fields/TextField.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';
import { firstError } from './entry';

const props = defineProps<{ canvasId: number; area: string; kindLabel: string }>();
const emit = defineEmits<{ added: [name: string] }>();
const form = useForm({ name: '' });
const error = computed(() => firstError(form.errors));

function submit(): void {
    const name = form.name.trim();
    form.transform((data) => ({ kind: 'domain', area: props.area, name: data.name })).post(routes.elements(props.canvasId), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            emit('added', name);
        },
    });
}
</script>

<template>
    <form novalidate class="mt-3" @submit.prevent="submit">
        <TextField id="add-domain" v-model="form.name" :label="t('entry.add_kind', { kind: kindLabel })" :error="error" autocomplete="off" />
    </form>
</template>
