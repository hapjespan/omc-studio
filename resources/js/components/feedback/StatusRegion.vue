<script setup lang="ts">
import { message } from './status';
</script>

<template>
    <div role="status" aria-live="polite" aria-atomic="true" class="sr-only">{{ message }}</div>
</template>
