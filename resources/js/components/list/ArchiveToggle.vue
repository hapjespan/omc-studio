<script setup lang="ts">
import { useId } from 'vue';
import { t } from '../../i18n';
import CheckBox from '../ui/CheckBox.vue';

const id = useId();
const shown = defineModel<boolean>({ default: false });
</script>

<template>
    <div class="flex items-center gap-2">
        <CheckBox :id="id" v-model="shown" />
        <label :for="id" class="text-[13px]">{{ t('list.show_archived') }}</label>
    </div>
</template>
