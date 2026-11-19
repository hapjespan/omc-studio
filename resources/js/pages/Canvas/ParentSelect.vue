<script setup lang="ts">
import SelectField from '../../components/fields/SelectField.vue';
import { t } from '../../i18n';
import type { ParentOption } from './entry';

defineProps<{ options: ParentOption[]; error?: string }>();
const model = defineModel<string>({ required: true });
</script>

<template>
    <SelectField id="element-parent" v-model="model" :label="t('entry.parent')" :options="options" :error="error" />
</template>
