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
    <div role="group" :aria-label="t('shell.mode_group')" class="inline-flex h-control items-stretch rounded-control border border-edge bg-white p-px text-[13px]">
        <Link v-for="mode in modes" :key="mode.key" :href="mode.href(canvas.id)" :aria-current="current(mode.href(canvas.id)) ? 'page' : undefined" class="inline-flex items-center rounded-[5px] px-3 font-medium text-secondary aria-[current=page]:bg-ink aria-[current=page]:font-semibold aria-[current=page]:text-white">{{ t(mode.label) }}</Link>
    </div>
</template>
