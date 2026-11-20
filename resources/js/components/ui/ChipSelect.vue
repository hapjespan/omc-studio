<script setup lang="ts">
import Icon from './Icon.vue';

defineProps<{ id: string; label: string; options: { value: string; label: string }[] }>();
const model = defineModel<string>({ required: true });
</script>

<template>
    <span class="relative inline-flex h-control items-center gap-1.5 rounded-control border border-edge bg-white pl-2.5 text-[13px] font-medium text-ink">
        <label :for="id" class="text-muted">{{ label }}</label>
        <select :id="id" v-model="model" class="h-full max-w-[12rem] cursor-pointer appearance-none truncate [field-sizing:content] bg-transparent pr-7 font-medium">
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <Icon name="chevron-down" class="pointer-events-none absolute right-2 text-muted" />
    </span>
</template>
