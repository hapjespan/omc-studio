<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import StatusRegion from '../components/feedback/StatusRegion.vue';
import { t } from '../i18n';
import SkipLink from './SkipLink.vue';

const page = usePage<{ appName: string }>();
</script>

<template>
    <SkipLink />
    <div class="flex min-h-screen flex-col bg-white text-[#171B21]">
        <header class="border-b border-[#848B95] px-4 py-2">
            <span class="font-semibold">{{ page.props.appName }}</span>
        </header>
        <div class="flex flex-1">
            <aside id="left-panel" :aria-label="t('shell.left_panel')" class="empty:hidden" />
            <main id="main" tabindex="-1" class="min-w-0 flex-1 p-4">
                <slot />
                <StatusRegion />
            </main>
        </div>
    </div>
</template>
