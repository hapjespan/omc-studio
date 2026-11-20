<script setup lang="ts">
import type { AreaParts } from './FinancialCard.vue';

defineProps<{ parts: AreaParts }>();
</script>

<template>
    <p class="absolute right-3 top-3 whitespace-nowrap text-[12px] leading-[18px] text-secondary" data-area-total><strong class="font-semibold text-ink">{{ parts.amount }}</strong> · {{ parts.percentage }}</p>
</template>
