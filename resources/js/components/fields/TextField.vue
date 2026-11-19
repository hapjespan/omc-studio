<script setup lang="ts">
import Field from './Field.vue';

defineProps<{ id: string; label: string; hint?: string; error?: string; type?: string }>();
const model = defineModel<string>({ default: '' });
</script>

<template>
    <Field :id="id" :label="label" :hint="hint" :error="error" v-slot="{ describedby, invalid }">
        <input :id="id" v-model="model" :type="type ?? 'text'" :aria-describedby="describedby" :aria-invalid="invalid || undefined" class="rounded-md border border-[#848B95] bg-white px-2 py-1.5 text-[13px] text-[#171B21]" />
    </Field>
</template>
