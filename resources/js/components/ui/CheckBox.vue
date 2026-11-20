<script setup lang="ts">
defineOptions({ inheritAttrs: false });
defineProps<{ value?: string | number }>();
const model = defineModel<unknown>();
</script>

<template>
    <label class="inline-flex size-6 shrink-0 items-center justify-center">
        <input v-model="model" type="checkbox" :value="value" v-bind="$attrs" class="size-4" />
    </label>
</template>
