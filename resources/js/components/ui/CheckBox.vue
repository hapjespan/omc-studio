<script setup lang="ts">
defineOptions({ inheritAttrs: false });
defineProps<{ value?: string | number }>();
const model = defineModel<unknown>();
</script>

<template>
    <label :class="['check-target inline-flex shrink-0 items-center justify-center', $slots.default ? 'min-h-6 min-w-6 gap-2' : 'size-6']">
        <input v-model="model" type="checkbox" :value="value" v-bind="$attrs" class="size-4" /><slot />
    </label>
</template>
