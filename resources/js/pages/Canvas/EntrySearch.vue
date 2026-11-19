<script setup lang="ts">
import TextField from '../../components/fields/TextField.vue';
import { t } from '../../i18n';

const model = defineModel<string>({ required: true });
</script>

<template>
    <div class="mt-3"><TextField id="entry-search" v-model="model" :label="t('entry.search')" type="search" autocomplete="off" /></div>
</template>
