<script setup lang="ts">
import '../../css/shell.css';
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import StatusRegion from '../components/feedback/StatusRegion.vue';
import DetailPanel from './DetailPanel.vue';
import LeftPanel from './LeftPanel.vue';
import MenuButton from './MenuButton.vue';
import ModeSwitch from './ModeSwitch.vue';
import SkipLink from './SkipLink.vue';
import Tabs from './Tabs.vue';
import TopBar, { type ShellCanvas } from './TopBar.vue';

const page = usePage<{ appName: string; auth: { role: string }; canvas?: ShellCanvas | null }>();
const menuOpen = ref(false);

watch(() => page.url, () => (menuOpen.value = false));
</script>

<template>
    <SkipLink />
    <div class="shell bg-white text-[#171B21]">
        <header class="shell-top border-b border-[#848B95] px-4 pt-2">
            <div class="flex items-start gap-2">
                <div class="min-w-0 flex-1"><TopBar :app-name="page.props.appName" :role="page.props.auth.role" :canvas="page.props.canvas" /></div>
                <MenuButton v-model="menuOpen" />
            </div>
            <div id="primary-tabs" class="shell-tabs" :data-open="menuOpen || undefined">
                <Tabs :role="page.props.auth.role" :canvas="page.props.canvas" :url="page.url" />
            </div>
        </header>
        <LeftPanel class="shell-left" />
        <main id="main" tabindex="-1" class="shell-main p-4">
            <ModeSwitch v-if="page.props.canvas" :role="page.props.auth.role" :canvas="page.props.canvas.id" :url="page.url" class="mb-4" />
            <slot />
            <StatusRegion />
        </main>
        <DetailPanel />
    </div>
</template>
