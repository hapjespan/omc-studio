<script setup lang="ts">
import Field from './Field.vue';

defineProps<{ id: string; label: string; suffix?: string; hint?: string; error?: string; inputmode?: 'text' | 'decimal' | 'numeric'; disabled?: boolean }>();
const model = defineModel<string>({ default: '' });
</script>

<template>
    <Field :id="id" :label="label" :hint="hint" :error="error" hide-label v-slot="{ describedby, invalid }">
        <span class="relative inline-flex h-control w-21">
            <input :id="id" v-model="model" type="text" :inputmode="inputmode" :disabled="disabled" :aria-describedby="describedby" :aria-invalid="invalid || undefined" :class="['h-full w-full rounded-control border bg-white px-2.5 text-right font-display text-[13px] font-bold text-ink disabled:border-hairline disabled:bg-disabled disabled:text-muted', suffix ? 'pr-6' : '', invalid ? 'border-danger-edge' : 'border-edge']" />
            <span v-if="suffix" aria-hidden="true" class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-[13px] text-muted">{{ suffix }}</span>
        </span>
    </Field>
</template>
