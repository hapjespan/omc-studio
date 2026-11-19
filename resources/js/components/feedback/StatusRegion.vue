<script setup lang="ts">
import { message } from './status';
</script>

<template>
    <div role="status" aria-live="polite" aria-atomic="true" class="text-[12.5px] text-[#454C57]">{{ message }}</div>
</template>
