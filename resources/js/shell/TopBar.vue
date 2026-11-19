<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { t } from '../i18n';
import { routes } from '../routes';
import UserMenu from './UserMenu.vue';

export interface ShellCanvas {
    id: number;
    name: string;
    organisation: { id: number; name: string };
}

defineProps<{ appName: string; role: string; canvas?: ShellCanvas | null }>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <Link :href="routes.home()" class="font-[Space_Grotesk] font-semibold">{{ appName }}</Link>
        <nav v-if="canvas" :aria-label="t('shell.breadcrumb')" class="text-[13px]">
            <ol class="flex flex-wrap gap-1">
                <li><Link :href="routes.home()" class="underline">{{ canvas.organisation.name }}</Link><span aria-hidden="true"> /</span></li>
                <li aria-current="page">{{ canvas.name }}</li>
            </ol>
        </nav>
        <div class="ml-auto"><UserMenu :role="role" /></div>
    </div>
</template>
