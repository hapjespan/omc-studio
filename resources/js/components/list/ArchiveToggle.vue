<script setup lang="ts">
import { t } from '../../i18n';

const shown = defineModel<boolean>({ default: false });
</script>

<template>
    <div class="flex items-center gap-2">
        <input id="list-archived" v-model="shown" type="checkbox" />
        <label for="list-archived" class="text-[13px]">{{ t('list.show_archived') }}</label>
    </div>
</template>
