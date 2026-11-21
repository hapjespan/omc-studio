<script setup lang="ts">
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui';
import AppButton from '../components/ui/AppButton.vue';
import { t } from '../i18n';

export type CardAction = 'copy' | 'rename' | 'archive' | 'restore' | 'delete' | 'save_template';

defineProps<{ name: string; archived: boolean; template?: boolean }>();
const emit = defineEmits<{ select: [action: CardAction] }>();

const item = 'cursor-pointer rounded px-3 py-1.5 data-[highlighted]:bg-accent-soft';
</script>

<template>
    <DropdownMenuRoot :modal="false">
        <DropdownMenuTrigger as-child :aria-label="t('canvas_card.actions', { name })"><AppButton class="self-start">{{ t('canvas_card.actions_label') }}</AppButton></DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent align="start" class="z-40 min-w-44 rounded-md border border-edge bg-white p-1 text-[13px] shadow-float">
                <DropdownMenuItem v-if="!template" :class="item" @select="emit('select', 'copy')">{{ t('canvas_card.copy') }}</DropdownMenuItem>
                <DropdownMenuItem v-if="!archived" :class="item" @select="emit('select', 'rename')">{{ t('canvas_card.rename') }}</DropdownMenuItem>
                <DropdownMenuItem v-if="!template" :class="item" @select="emit('select', 'save_template')">{{ t('entry.save_template') }}</DropdownMenuItem>
                <DropdownMenuItem v-if="archived" :class="item" @select="emit('select', 'restore')">{{ t('canvas_card.restore') }}</DropdownMenuItem>
                <DropdownMenuItem v-else-if="!template" :class="item" @select="emit('select', 'archive')">{{ t('canvas_card.archive') }}</DropdownMenuItem>
                <DropdownMenuItem :class="item" @select="emit('select', 'delete')">{{ t('canvas_card.delete') }}</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>
