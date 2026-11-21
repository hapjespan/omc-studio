<script setup lang="ts">
import Icon from '../ui/Icon.vue';
import Field from './Field.vue';
import { invalidEdge } from './edge';

defineOptions({ inheritAttrs: false });
defineProps<{ id: string; label?: string; options?: { value: string; label: string }[]; emptyLabel?: string; hint?: string; error?: string; disabled?: boolean; bare?: boolean }>();
const model = defineModel<string>({ default: '' });
</script>

<template>
    <span v-if="bare" class="relative flex">
        <select v-bind="$attrs" :id="id" v-model="model" :disabled="disabled" :aria-label="label" class="h-control appearance-none rounded-control border border-edge bg-white pl-2.5 pr-8 text-[13px] text-ink disabled:bg-disabled disabled:text-muted">
            <slot>
                <option v-if="emptyLabel !== undefined" value="">{{ emptyLabel }}</option>
                <option v-for="option in options ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
            </slot>
        </select>
        <Icon name="chevron-down" :class="['pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2', disabled ? 'text-muted' : 'text-ink']" />
    </span>
    <Field v-else v-bind="$attrs" :id="id" :label="label ?? ''" :hint="hint" :error="error" v-slot="{ describedby, invalid }">
        <span class="relative flex">
            <select :id="id" v-model="model" :disabled="disabled" :aria-describedby="describedby" :aria-invalid="invalid || undefined" :class="['h-control w-full appearance-none rounded-control border bg-white pl-2.5 pr-8 text-[13px] text-ink disabled:bg-disabled disabled:text-muted', invalidEdge(invalid)]">
                <option v-if="emptyLabel !== undefined" value="">{{ emptyLabel }}</option>
                <option v-for="option in options ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <Icon name="chevron-down" :class="['pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2', disabled ? 'text-muted' : 'text-ink']" />
        </span>
    </Field>
</template>
