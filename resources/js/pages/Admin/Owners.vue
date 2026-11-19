<script setup lang="ts">
import OrganisationEntries, { type EntriesProps } from '../../components/list/OrganisationEntries.vue';

defineProps<EntriesProps>();
</script>

<template>
    <OrganisationEntries kind="owners" :rows="rows" :count="count" :query="query" :organisation="organisation" :others="others" />
</template>
