<script setup lang="ts">
withDefaults(defineProps<{ padding?: 16 | 24; heading?: string; level?: 2 | 3 | 4 }>(), { padding: 16, heading: undefined, level: 2 });
</script>

<template>
    <div :class="['rounded-panel border border-hairline bg-white', padding === 24 ? 'p-6' : 'p-4']">
        <component :is="`h${level}`" v-if="heading" class="mb-3 font-display text-[16px] font-semibold text-ink">{{ heading }}</component>
        <slot />
    </div>
</template>
