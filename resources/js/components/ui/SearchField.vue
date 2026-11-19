<script setup lang="ts">
import { ref } from 'vue';
import Icon from './Icon.vue';

defineOptions({ inheritAttrs: false });
defineProps<{ id: string; label: string; placeholder?: string }>();
const model = defineModel<string>({ default: '' });
const element = ref<HTMLInputElement>();

defineExpose({ focus: () => element.value?.focus() });
</script>

<template>
    <div class="relative">
        <label :for="id" class="sr-only">{{ label }}</label>
        <Icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-muted" />
        <input :id="id" ref="element" v-model="model" type="search" autocomplete="off" :placeholder="placeholder ?? label" v-bind="$attrs" class="h-control w-full rounded-control border border-edge bg-white pl-8 pr-2.5 text-[13px] text-ink placeholder:text-muted" />
    </div>
</template>
