<script setup lang="ts">
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui';
import AppButton from './AppButton.vue';
import Icon from './Icon.vue';

export interface MenuAction {
    key: string;
    label: string;
    disabled?: boolean;
}

withDefaults(defineProps<{ label?: string; items: MenuAction[]; variant?: 'primary' | 'secondary'; chevron?: boolean; triggerLabel?: string; align?: 'start' | 'end' }>(), { variant: 'secondary', chevron: true, align: 'end' });
const emit = defineEmits<{ select: [key: string] }>();
</script>

<template>
    <DropdownMenuRoot :modal="false">
        <DropdownMenuTrigger as-child :aria-label="triggerLabel">
            <slot name="trigger"><AppButton :variant="variant">{{ label }}<Icon v-if="chevron" name="chevron-down" :class="variant === 'primary' ? 'text-white' : 'text-muted'" /></AppButton></slot>
        </DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent :align="align" :side-offset="4" class="z-40 min-w-44 rounded-control border border-hairline bg-white p-1 text-[13px] text-ink shadow-float">
                <DropdownMenuItem v-for="item in items" :key="item.key" :disabled="item.disabled" class="flex min-h-dense cursor-pointer items-center rounded-badge px-3 data-[disabled]:cursor-default data-[disabled]:text-muted data-[highlighted]:bg-accent-soft" @select="emit('select', item.key)">{{ item.label }}</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>
