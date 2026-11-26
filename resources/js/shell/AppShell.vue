<script setup lang="ts">
import '../../css/shell.css';
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import StatusRegion from '../components/feedback/StatusRegion.vue';
import { detail, discardDetail } from './detail';
import DetailPanel from './DetailPanel.vue';
import LeftPanel from './LeftPanel.vue';
import MenuButton from './MenuButton.vue';
import { shellLayout } from './layout';
import { modeSwitchPlacement } from './modes';
import PageHeader from './PageHeader.vue';
import ModeSwitch from './ModeSwitch.vue';
import ReauthDialog from './ReauthDialog.vue';
import { reauth, scheduleReauth } from './session';
import SessionTimer from './SessionTimer.vue';
import SkipLink from './SkipLink.vue';
import Tabs from './Tabs.vue';
import TopBar, { type ShellCanvas, type ShellFiscalYear } from './TopBar.vue';

const page = usePage<{ appName: string; auth: { role: string; secondFactor: boolean; sessionEndsAt: number | null }; canvas?: ShellCanvas | null; fiscalYear?: ShellFiscalYear | null }>();
const menuOpen = ref(false);

watch(() => page.props.auth.sessionEndsAt, scheduleReauth, { immediate: true });

watch(
    () => page.url,
    () => {
        menuOpen.value = false;
        if (detail.open) discardDetail();
    },
);
</script>

<template>
    <SkipLink />
    <div class="shell bg-page text-ink">
        <header class="shell-top flex min-h-bar items-center border-b border-hairline bg-white px-4">
            <TopBar :app-name="page.props.appName" :role="page.props.auth.role" :canvas="page.props.canvas" :fiscal-year="page.props.fiscalYear">
                <template #tabs>
                    <div id="primary-tabs" class="shell-tabs" :data-open="menuOpen || undefined">
                        <Tabs :role="page.props.auth.role" :canvas="page.props.canvas" :url="page.url" />
                    </div>
                </template>
                <template #menu><MenuButton v-model="menuOpen" /></template>
            </TopBar>
        </header>
        <LeftPanel class="shell-left" />
        <main id="main" tabindex="-1" :class="['shell-main', shellLayout.widePadding ? 'p-6' : 'p-4']">
            <PageHeader v-if="shellLayout.headerSlots > 0">
                <ModeSwitch v-if="page.props.canvas && !modeSwitchPlacement.inPage" :role="page.props.auth.role" :canvas="page.props.canvas" :url="page.url" />
            </PageHeader>
            <ModeSwitch v-else-if="page.props.canvas && !modeSwitchPlacement.inPage" :role="page.props.auth.role" :canvas="page.props.canvas" :url="page.url" class="mb-4" />
            <slot />
            <StatusRegion />
        </main>
        <DetailPanel />
        <SessionTimer />
        <ReauthDialog v-if="reauth" />
    </div>
</template>
