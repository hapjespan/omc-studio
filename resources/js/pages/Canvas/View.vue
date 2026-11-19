<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ canvas: { id: number; name: string; archived: boolean }; canWrite: boolean }>();
</script>

<template>
    <Head :title="canvas.name" />
    <h1 class="text-[20px] font-semibold">{{ canvas.name }}</h1>
</template>
