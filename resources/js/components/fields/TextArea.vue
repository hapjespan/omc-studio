<script setup lang="ts">
import Field from './Field.vue';
import { invalidEdge } from './edge';

defineProps<{ id: string; label: string; hint?: string; error?: string; rows?: number }>();
const model = defineModel<string>({ default: '' });
</script>

<template>
    <Field :id="id" :label="label" :hint="hint" :error="error" v-slot="{ describedby, invalid }">
        <textarea :id="id" v-model="model" :rows="rows ?? 4" :aria-describedby="describedby" :aria-invalid="invalid || undefined" :class="['rounded-control border bg-white px-2.5 py-2 text-[13px] text-ink', invalidEdge(invalid)]" />
    </Field>
</template>
