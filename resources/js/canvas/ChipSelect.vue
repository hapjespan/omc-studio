<script setup lang="ts">
import Icon from '../components/ui/Icon.vue';

defineProps<{ id: string; label: string; name?: string }>();
const model = defineModel<string>({ required: true });
</script>

<template>
    <span class="relative inline-flex h-control items-center gap-1.5 rounded-control border border-edge bg-white pl-2.5 text-[13px] font-medium text-ink">
        <label :for="id" class="text-muted">{{ label }}</label>
        <select :id="id" v-model="model" :aria-label="name" class="h-full max-w-[12rem] cursor-pointer appearance-none truncate [field-sizing:content] bg-transparent pr-7 font-medium"><slot /></select>
        <Icon name="chevron-down" class="pointer-events-none absolute right-2 text-muted" />
    </span>
</template>
