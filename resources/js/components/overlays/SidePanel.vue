<script setup lang="ts">
import Dialog from './Dialog.vue';

defineProps<{ title: string; description?: string; kind?: string; dirty?: boolean }>();
const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <Dialog v-model:open="open" :title="title" :description="description" :kind="kind" :dirty="dirty" variant="panel" v-slot="{ requestClose }"><slot :request-close="requestClose" /></Dialog>
</template>
