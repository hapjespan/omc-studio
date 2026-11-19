<script setup lang="ts">
import { SelectContent, SelectItem, SelectItemText, SelectPortal, SelectRoot, SelectTrigger, SelectValue} from 'reka-ui';
import Field from './Field.vue';

defineProps<{ id: string; label: string; options: { value: string; label: string }[]; hint?: string; error?: string; placeholder?: string; disabled?: boolean }>();
const model = defineModel<string>();
</script>

<template>
    <Field :id="id" :label="label" :hint="hint" :error="error" v-slot="{ describedby, invalid }">
        <SelectRoot v-model="model" :disabled="disabled">
            <SelectTrigger :id="id" :aria-describedby="describedby" :aria-invalid="invalid || undefined" class="flex items-center justify-between rounded-md border border-[#848B95] bg-white px-2 py-1.5 text-left text-[13px] text-[#171B21]">
                <SelectValue :placeholder="placeholder ?? ''" />
            </SelectTrigger>
            <SelectPortal>
                <SelectContent position="popper" :side-offset="4" class="z-50 min-w-[var(--reka-select-trigger-width)] rounded-md border border-[#848B95] bg-white p-1 shadow-md">
                    <div class="max-h-60 overflow-y-auto">
                        <SelectItem v-for="option in options" :key="option.value" :value="option.value" class="cursor-pointer rounded px-2 py-1.5 text-[13px] text-[#171B21] data-[highlighted]:bg-[#E3EBFB]">
                            <SelectItemText>{{ option.label }}</SelectItemText>
                        </SelectItem>
                    </div>
                </SelectContent>
            </SelectPortal>
        </SelectRoot>
    </Field>
</template>
