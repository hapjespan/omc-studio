<script setup lang="ts">
import { t } from '../i18n';

const model = defineModel<string>({ required: true });
</script>

<template>
    <div role="group" :aria-label="t('overlay.label')" class="flex items-center gap-2">
        <span class="text-[12.5px] text-secondary" aria-hidden="true">{{ t('overlay.label') }}</span>
        <div class="inline-flex h-control items-stretch rounded-control border border-edge bg-white p-px text-[13px]">
            <button
                v-for="key in ['none', 'modality', 'financial']"
                :key="key"
                type="button"
                :aria-pressed="model === key"
                class="inline-flex items-center rounded-[5px] px-3 font-medium text-secondary aria-pressed:bg-ink aria-pressed:text-white"
                @click="model = key"
            >{{ t(`overlay.${key}`) }}</button>
        </div>
    </div>
</template>
