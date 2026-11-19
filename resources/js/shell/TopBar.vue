<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { t } from '../i18n';
import { routes } from '../routes';
import NoticeWarning from './NoticeWarning.vue';
import UserMenu from './UserMenu.vue';
import YearSelector from './YearSelector.vue';

export interface ShellCanvas {
    id: number;
    name: string;
    kind: string;
    archived: boolean;
    organisation: { id: number; name: string } | null;
}

defineProps<{ appName: string; role: string; canvas?: ShellCanvas | null }>();
const page = usePage<{ pendingNotices: number | null }>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <Link :href="routes.home()" class="font-[Space_Grotesk] font-semibold">{{ appName }}</Link>
        <nav v-if="canvas" :aria-label="t('shell.breadcrumb')" class="text-[13px]">
            <ol class="flex flex-wrap gap-1">
                <li v-if="canvas.organisation"><Link :href="routes.home()" class="underline">{{ canvas.organisation.name }}</Link><span aria-hidden="true"> /</span></li>
                <li aria-current="page">{{ canvas.name }}</li>
            </ol>
        </nav>
        <div class="ml-auto flex flex-wrap items-center gap-x-4 gap-y-2"><NoticeWarning v-if="role === 'administrator'" :count="page.props.pendingNotices" /><YearSelector /><UserMenu :role="role" /></div>
    </div>
</template>
