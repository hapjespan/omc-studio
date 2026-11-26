<script setup lang="ts">
import { shellLayout } from '../../shell/layout';
import { message } from './status';
</script>

<template>
    <div role="status" aria-live="polite" aria-atomic="true" :class="shellLayout.quietStatus ? 'sr-only' : 'text-[12.5px] text-secondary'">{{ message }}</div>
</template>
