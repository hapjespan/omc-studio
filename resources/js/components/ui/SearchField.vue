<script setup lang="ts">
import Icon from './Icon.vue';

defineProps<{ id: string; label: string; placeholder?: string }>();
const model = defineModel<string>({ default: '' });
</script>

<template>
    <div class="relative">
        <label :for="id" class="sr-only">{{ label }}</label>
        <Icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-muted" />
        <input :id="id" v-model="model" type="search" autocomplete="off" :placeholder="placeholder ?? label" class="h-control w-full rounded-control border border-edge bg-white pl-8 pr-2.5 text-[13px] text-ink placeholder:text-muted" />
    </div>
</template>
