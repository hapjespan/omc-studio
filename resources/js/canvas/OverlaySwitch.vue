<script setup lang="ts">
import { t } from '../i18n';

const model = defineModel<string>({ required: true });
</script>

<template>
    <div role="group" :aria-label="t('overlay.label')" class="flex items-center gap-2 text-[13px]">
        <span class="font-semibold" aria-hidden="true">{{ t('overlay.label') }}</span>
        <div class="flex overflow-hidden rounded-md border border-[#848B95]">
            <button
                v-for="key in ['none', 'modality', 'financial']"
                :key="key"
                type="button"
                :aria-pressed="model === key"
                class="px-3 py-1.5 aria-pressed:bg-[#171B21] aria-pressed:text-white"
                @click="model = key"
            >{{ t(`overlay.${key}`) }}</button>
        </div>
    </div>
</template>
