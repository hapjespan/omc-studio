<script setup lang="ts">
import { t } from '../i18n';
import ModalityMark from './ModalityMark.vue';
import { MODALITIES, NO_MODALITY } from './modality';

defineProps<{ modelValue: string }>();
defineEmits<{ select: [key: string] }>();
</script>

<template>
    <div role="group" :aria-labelledby="'modality-label'" class="flex flex-col gap-1">
        <span id="modality-label" class="text-[13px] font-semibold text-[#171B21]">{{ t('modality_entry.group') }}</span>
        <div class="flex flex-wrap gap-2">
            <button v-for="modality in MODALITIES" :key="modality.key" type="button" :aria-pressed="modelValue === modality.key" class="flex items-center gap-1.5 rounded-md border border-[#848B95] px-2 py-1 text-[13px] aria-pressed:border-2 aria-pressed:border-[#1B1F27] aria-pressed:font-semibold" @click="$emit('select', modality.key)">
                <ModalityMark :letter="modality.letter" />{{ t(`modality_entry.name.${modality.key}`) }}
            </button>
            <button type="button" :aria-pressed="modelValue === NO_MODALITY" class="rounded-md border border-[#848B95] px-2 py-1 text-[13px] aria-pressed:border-2 aria-pressed:border-[#1B1F27] aria-pressed:font-semibold" @click="$emit('select', NO_MODALITY)">{{ t('modality_entry.none') }}</button>
        </div>
    </div>
</template>
