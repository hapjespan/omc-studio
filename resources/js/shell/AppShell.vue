<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import StatusRegion from '../components/feedback/StatusRegion.vue';
import ModeSwitch from './ModeSwitch.vue';
import DetailPanel from './DetailPanel.vue';
import LeftPanel from './LeftPanel.vue';
import Tabs from './Tabs.vue';
import SkipLink from './SkipLink.vue';
import TopBar, { type ShellCanvas } from './TopBar.vue';

const page = usePage<{ appName: string; auth: { role: string }; canvas?: ShellCanvas | null }>();
</script>

<template>
    <SkipLink />
    <div class="flex min-h-screen flex-col bg-white text-[#171B21]">
        <header class="border-b border-[#848B95] px-4 pt-2">
            <TopBar :app-name="page.props.appName" :role="page.props.auth.role" :canvas="page.props.canvas" />
            <Tabs :role="page.props.auth.role" :canvas="page.props.canvas" :url="page.url" />
        </header>
        <div class="flex flex-1">
            <LeftPanel />
            <main id="main" tabindex="-1" class="min-w-0 flex-1 p-4">
                <ModeSwitch v-if="page.props.canvas" :role="page.props.auth.role" :canvas="page.props.canvas.id" :url="page.url" class="mb-4" />
                <slot />
                <StatusRegion />
            </main>
            <DetailPanel />
        </div>
    </div>
</template>
