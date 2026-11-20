<script setup lang="ts">
import { t } from '../../i18n';

const model = defineModel<boolean>({ required: true });
</script>

<template>
    <label class="mt-3 flex items-center gap-2 py-1 text-[13px] text-secondary"><input v-model="model" type="checkbox" class="accent-ink" />{{ t('list.show_archived') }}</label>
</template>
