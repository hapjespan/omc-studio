<script setup lang="ts">
import { useId } from 'vue';
import { t } from '../../i18n';
import CheckBox from '../../components/ui/CheckBox.vue';

const id = useId();
const model = defineModel<boolean>({ required: true });
</script>

<template>
    <div class="mt-3 flex items-center gap-2 text-[13px] text-secondary">
        <CheckBox :id="id" v-model="model" />
        <label :for="id">{{ t('list.show_archived') }}</label>
    </div>
</template>
