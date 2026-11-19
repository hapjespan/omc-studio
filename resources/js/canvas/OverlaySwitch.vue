<script setup lang="ts">
import { t } from '../i18n';

const model = defineModel<string>({ required: true });
</script>

<template>
    <div class="flex items-center gap-2 text-[13px]">
        <label for="overlay-switch" class="font-semibold">{{ t('overlay.label') }}</label>
        <select id="overlay-switch" v-model="model" class="rounded-md border border-[#848B95] bg-white px-2 py-1.5">
            <option v-for="key in ['none', 'modality', 'financial']" :key="key" :value="key">{{ t(`overlay.${key}`) }}</option>
        </select>
    </div>
</template>
