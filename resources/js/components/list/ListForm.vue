<script setup lang="ts">
import { ref, watch } from 'vue';
import SidePanel from '../overlays/SidePanel.vue';

defineProps<{ title: string; kind?: string }>();
const open = defineModel<boolean>('open', { default: false });
const dirty = ref(false);

watch(open, () => (dirty.value = false));
</script>

<template>
    <SidePanel v-model:open="open" :title="title" :kind="kind" :dirty="dirty" v-slot="{ requestClose }">
        <slot :close="() => (open = false)" :set-dirty="(value: boolean) => (dirty = value)" :request-close="requestClose" />
    </SidePanel>
</template>
