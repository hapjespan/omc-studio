<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { t } from '../i18n';
import { visibleModes } from './modes';
import type { ShellCanvas } from './TopBar.vue';

const props = defineProps<{ role: string; canvas: ShellCanvas; url: string }>();
const modes = computed(() => visibleModes(props.role, props.canvas));
const current = (href: string): boolean => props.url.split('?')[0] === href;
</script>

<template>
    <div role="group" :aria-label="t('shell.mode_group')" class="flex gap-2 text-[13px]">
        <Link v-for="mode in modes" :key="mode.key" :href="mode.href(canvas.id)" :aria-current="current(mode.href(canvas.id)) ? 'page' : undefined" class="rounded-md border border-[#848B95] px-3 py-1.5 aria-[current=page]:bg-[#1F3A5F] aria-[current=page]:text-white">{{ t(mode.label) }}</Link>
    </div>
</template>
