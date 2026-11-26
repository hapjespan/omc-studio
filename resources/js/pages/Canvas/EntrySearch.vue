<script setup lang="ts">
import SearchField from '../../components/ui/SearchField.vue';
import { t } from '../../i18n';

const model = defineModel<string>({ required: true });
</script>

<template>
    <div class="w-[220px] max-w-full"><SearchField id="entry-search" v-model="model" :label="t('entry.search')" /></div>
</template>
