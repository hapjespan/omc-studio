<script setup lang="ts">
import Icon from '../ui/Icon.vue';
import Field from './Field.vue';

defineProps<{ id: string; label: string; options: { value: string; label: string }[]; emptyLabel?: string; hint?: string; error?: string; disabled?: boolean }>();
const model = defineModel<string>({ default: '' });
</script>

<template>
    <Field :id="id" :label="label" :hint="hint" :error="error" v-slot="{ describedby, invalid }">
        <span class="relative flex">
            <select :id="id" v-model="model" :disabled="disabled" :aria-describedby="describedby" :aria-invalid="invalid || undefined" :class="['h-control w-full appearance-none rounded-control border bg-white pl-2.5 pr-8 text-[13px] text-ink disabled:bg-disabled disabled:text-muted', invalid ? 'border-danger-edge' : 'border-edge']">
                <option v-if="emptyLabel !== undefined" value="">{{ emptyLabel }}</option>
                <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <Icon name="chevron-down" class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-ink" />
        </span>
    </Field>
</template>
