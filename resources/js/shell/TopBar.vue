<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LogoMark from '../components/ui/LogoMark.vue';
import Pill from '../components/ui/Pill.vue';
import { t } from '../i18n';
import { routes } from '../routes';
import UserMenu from './UserMenu.vue';

export interface ShellCanvas {
    id: number;
    name: string;
    kind: string;
    archived: boolean;
    sample?: boolean;
    organisation: { id: number; name: string } | null;
}

export interface ShellFiscalYear {
    label: string;
    state?: string;
}

defineProps<{ appName: string; role: string; canvas?: ShellCanvas | null; fiscalYear?: ShellFiscalYear | null }>();
</script>

<template>
    <div class="flex w-full flex-wrap items-center gap-x-4 gap-y-2">
        <Link :href="routes.home()" class="flex items-center gap-2 font-display text-[15px] font-semibold text-ink"><LogoMark /><span>{{ appName }}</span></Link>
        <nav v-if="canvas" :aria-label="t('shell.breadcrumb')" class="min-w-0 max-w-full text-[13px] [overflow-wrap:anywhere]">
            <ol class="flex flex-wrap items-center gap-1.5">
                <li v-if="canvas.organisation" class="text-muted"><Link :href="routes.home()" class="underline">{{ canvas.organisation.name }}</Link><span aria-hidden="true"> /</span></li>
                <li aria-current="page" class="font-semibold text-ink">{{ canvas.name }}</li>
                <li v-if="canvas.sample"><Pill>{{ t('shell.sample_data') }}</Pill></li>
            </ol>
        </nav>
        <slot name="menu" />
        <slot name="tabs" />
        <div class="ml-auto flex min-w-0 max-w-full flex-wrap items-center gap-x-3 gap-y-1 text-[13px]">
            <slot name="fiscal-year">
                <span v-if="fiscalYear" class="inline-flex h-dense items-center gap-1 rounded-control border border-edge px-2.5"><span class="font-medium text-ink">{{ fiscalYear.label }}</span><span v-if="fiscalYear.state" class="text-muted">{{ fiscalYear.state }}</span></span>
            </slot>
            <UserMenu :role="role" />
        </div>
    </div>
</template>
