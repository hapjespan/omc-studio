<script setup lang="ts">
import OrganisationEntries, { type EntriesProps } from '../../components/list/OrganisationEntries.vue';
import { useQuietStatus } from '../../shell/layout';

useQuietStatus();

defineProps<EntriesProps>();
</script>

<template>
    <OrganisationEntries kind="applications" :rows="rows" :count="count" :query="query" :organisation="organisation" :organisations="organisations" :others="others" />
</template>
