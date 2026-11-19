<script setup lang="ts">
import Field from './Field.vue';

defineProps<{ id: string; label: string; hint?: string; error?: string; type?: string; autocomplete?: string }>();
const model = defineModel<string>({ default: '' });
</script>

<template>
    <Field :id="id" :label="label" :hint="hint" :error="error" v-slot="{ describedby, invalid }">
        <input :id="id" v-model="model" :type="type ?? 'text'" :autocomplete="autocomplete" :aria-describedby="describedby" :aria-invalid="invalid || undefined" class="h-control rounded-control border border-edge bg-white px-2.5 text-[13px] text-ink placeholder:text-muted" />
    </Field>
</template>
