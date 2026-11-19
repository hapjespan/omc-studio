<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { plural } from '../i18n';
import { routes } from '../routes';

defineProps<{ count: number | null }>();
</script>

<template>
    <p role="status" class="text-[13px]">
        <Link v-if="count" :href="routes.systemNotices()" class="rounded-md bg-[#FFF3CD] px-2 py-1 font-semibold text-[#5C3B00] underline">{{ plural('notices.warning', count) }}</Link>
    </p>
</template>
