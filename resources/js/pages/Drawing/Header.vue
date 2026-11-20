<script setup lang="ts">
import AppButton from '../../components/ui/AppButton.vue';
import { t } from '../../i18n';
import type { SaveStatus } from './SaveQueue';

defineProps<{ status: SaveStatus; canUndo: boolean; canRedo: boolean }>();
defineEmits<{ undo: []; redo: []; fit: []; retry: []; reset: [] }>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <div class="flex items-center gap-2">
            <AppButton size="dense" :disabled="!canUndo" @click="$emit('undo')">{{ t('drawing.undo') }}</AppButton>
            <AppButton size="dense" :disabled="!canRedo" @click="$emit('redo')">{{ t('drawing.redo') }}</AppButton>
        </div>
        <p v-if="status === 'saving' || status === 'saved'" class="text-[13px] text-secondary" data-save-state>{{ status === 'saving' ? t('drawing.saving') : t('drawing.saved') }}</p>
        <p v-else-if="status === 'failed'" class="flex items-center gap-2 text-[13px] text-danger" data-save-state>
            {{ t('drawing.save_failed') }}
            <AppButton size="dense" @click="$emit('retry')">{{ t('drawing.retry') }}</AppButton>
        </p>
        <p class="text-[13px] text-secondary">{{ t('drawing.engine_note') }}</p>
        <div class="ml-auto flex items-center gap-2">
            <AppButton size="dense" @click="$emit('reset')">{{ t('drawing.auto_all') }}</AppButton>
            <AppButton size="dense" @click="$emit('fit')">{{ t('drawing.fit') }}</AppButton>
        </div>
    </div>
</template>
