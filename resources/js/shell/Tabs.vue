<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { t } from '../i18n';
import { isActive, visibleTabs } from './tabs';
import type { ShellCanvas } from './TopBar.vue';

const props = defineProps<{ role: string; canvas?: ShellCanvas | null; url: string }>();
const tabs = computed(() => visibleTabs(props.role, Boolean(props.canvas)));
</script>

<template>
    <nav :aria-label="t('shell.tabs')">
        <ul class="flex gap-4 text-[13px]">
            <li v-for="tab in tabs" :key="tab.key">
                <Link :href="tab.href(canvas ?? null)" :aria-current="isActive(tab, url) ? 'page' : undefined" class="inline-block border-b-2 border-transparent py-2 aria-[current=page]:border-[#1F3A5F] aria-[current=page]:font-semibold">{{ t(tab.label) }}</Link>
            </li>
        </ul>
    </nav>
</template>
