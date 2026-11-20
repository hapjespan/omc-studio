<script setup lang="ts">
import Field from './Field.vue';

defineProps<{ id: string; label: string; accept: string; hint?: string; error?: string }>();
const emit = defineEmits<{ pick: [file: File | null] }>();
</script>

<template>
    <Field :id="id" :label="label" :hint="hint" :error="error" v-slot="{ describedby, invalid }">
        <input :id="id" type="file" :accept="accept" :aria-describedby="describedby" :aria-invalid="invalid || undefined" class="min-h-control rounded-control border border-edge bg-white py-1 pl-1 pr-2.5 text-[13px] text-ink file:mr-3 file:min-h-mark file:rounded-badge file:border file:border-edge file:bg-surface file:px-2.5 file:text-[12.5px] file:font-medium file:text-ink" @change="emit('pick', ($event.target as HTMLInputElement).files?.[0] ?? null)" />
    </Field>
</template>
