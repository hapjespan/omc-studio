<script setup lang="ts">
import { computed } from 'vue';
import SegmentedControl from '../components/ui/SegmentedControl.vue';
import { t } from '../i18n';
import { visibleModes } from './modes';
import type { ShellCanvas } from './TopBar.vue';

const props = defineProps<{ role: string; canvas: ShellCanvas; url: string }>();
const items = computed(() => visibleModes(props.role, props.canvas).map((mode) => ({ key: mode.key, label: t(mode.label), href: mode.href(props.canvas.id) })));
const active = computed(() => (/^\/snapshots\/[^/]+\/view$/.test(props.url.split('?')[0]) ? 'view' : items.value.find((item) => props.url.split('?')[0] === item.href)?.key));
</script>

<template>
    <SegmentedControl v-if="active" :items="items" :active="active" :label="t('shell.mode_group')" />
</template>
