<script setup lang="ts">
import { message } from './status';
</script>

<template>
    <div role="status" aria-live="polite" aria-atomic="true" class="text-[12.5px] text-secondary">{{ message }}</div>
</template>
