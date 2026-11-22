<script setup lang="ts">
import AppButton from '../../components/ui/AppButton.vue';
import Icon from '../../components/ui/Icon.vue';
import IconButton from '../../components/ui/IconButton.vue';
import Pill from '../../components/ui/Pill.vue';
import { t } from '../../i18n';
import type { SaveStatus } from './SaveQueue';

defineProps<{ status: SaveStatus; canUndo: boolean; canRedo: boolean }>();
defineEmits<{ undo: []; redo: []; fit: []; retry: []; reset: [] }>();
</script>

<template>
    <div class="contents">
        <div class="flex items-center gap-1.5">
            <IconButton :label="t('drawing.undo')" icon="undo" :disabled="!canUndo" @click="$emit('undo')" />
            <IconButton :label="t('drawing.redo')" icon="redo" :disabled="!canRedo" @click="$emit('redo')" />
        </div>
        <div class="min-w-[10rem]" data-save-slot>
            <p v-if="status === 'saving' || status === 'saved'" class="flex items-center gap-1 text-[13px] font-medium" :class="status === 'saved' ? 'text-success' : 'text-secondary'" data-save-state><Icon v-if="status === 'saved'" name="check" />{{ status === 'saving' ? t('drawing.saving') : t('drawing.saved') }}</p>
            <p v-else-if="status === 'failed'" class="flex items-center gap-2 text-[13px] text-danger" data-save-state>
                {{ t('drawing.save_failed') }}
                <AppButton size="dense" @click="$emit('retry')">{{ t('drawing.retry') }}</AppButton>
            </p>
        </div>
        <div class="ml-auto flex flex-wrap items-center gap-2">
            <Pill>{{ t('drawing.engine_note') }}</Pill>
            <AppButton @click="$emit('reset')">{{ t('drawing.auto_all') }}</AppButton>
            <AppButton @click="$emit('fit')">{{ t('drawing.fit') }}</AppButton>
        </div>
    </div>
</template>
