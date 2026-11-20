<script setup lang="ts">
import Icon from '../ui/Icon.vue';

defineOptions({ inheritAttrs: false });
defineProps<{ bare?: boolean }>();
const model = defineModel<string>({ default: '' });
</script>

<template>
    <div :class="bare ? 'contents' : 'relative flex'">
        <select v-bind="$attrs" v-model="model" :class="bare ? 'field-sizing-content h-full appearance-none rounded-control bg-transparent pl-1.5 pr-8 text-[13px] font-medium text-ink' : 'h-control w-full appearance-none rounded-control border border-edge bg-white pl-2.5 pr-8 text-[13px] text-ink'"><slot /></select>
        <Icon name="chevron-down" :class="['pointer-events-none text-ink', bare ? 'absolute right-2.5' : 'absolute right-2.5 top-1/2 -translate-y-1/2']" />
    </div>
</template>
