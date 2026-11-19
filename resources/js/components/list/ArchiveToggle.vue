<script setup lang="ts">
import { useId } from 'vue';
import { t } from '../../i18n';

const id = useId();
const shown = defineModel<boolean>({ default: false });
</script>

<template>
    <div class="flex items-center gap-2">
        <input :id="id" v-model="shown" type="checkbox" />
        <label :for="id" class="text-[13px]">{{ t('list.show_archived') }}</label>
    </div>
</template>
