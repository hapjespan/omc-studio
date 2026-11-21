<script setup lang="ts">
import { SelectContent, SelectItem, SelectItemText, SelectPortal, SelectRoot, SelectTrigger, SelectValue} from 'reka-ui';
import Icon from '../ui/Icon.vue';
import Field from './Field.vue';
import { invalidEdge } from './edge';

defineProps<{ id: string; label: string; options: { value: string; label: string }[]; hint?: string; error?: string; placeholder?: string; disabled?: boolean }>();
const model = defineModel<string>();
</script>

<template>
    <Field :id="id" :label="label" :hint="hint" :error="error" v-slot="{ describedby, invalid }">
        <SelectRoot v-model="model" :disabled="disabled">
            <SelectTrigger :id="id" :aria-describedby="describedby" :aria-invalid="invalid || undefined" :class="['flex h-control items-center justify-between gap-2 rounded-control border bg-white px-2.5 text-left text-[13px] text-ink', invalidEdge(invalid)]">
                <SelectValue :placeholder="placeholder ?? ''" />
                <Icon name="chevron-down" class="text-muted" />
            </SelectTrigger>
            <SelectPortal>
                <SelectContent position="popper" :side-offset="4" class="z-50 min-w-[var(--reka-select-trigger-width)] rounded-control border border-hairline bg-white p-1 shadow-[0_1px_3px_rgba(0,0,0,.12)]">
                    <div class="max-h-60 overflow-y-auto">
                        <SelectItem v-for="option in options" :key="option.value" :value="option.value" class="cursor-pointer rounded px-2 py-1.5 text-[13px] text-ink data-[highlighted]:bg-accent-soft">
                            <SelectItemText>{{ option.label }}</SelectItemText>
                        </SelectItem>
                    </div>
                </SelectContent>
            </SelectPortal>
        </SelectRoot>
    </Field>
</template>
