<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { t } from '../../i18n';
import { adminEntries } from '../../shell/adminEntries';
</script>

<template>
    <Head :title="t('shell.tab.admin')" />
    <h1 class="text-[20px] font-semibold">{{ t('shell.tab.admin') }}</h1>
    <ul class="mt-4 flex flex-col gap-2 text-[13px]">
        <li v-for="entry in adminEntries()" :key="entry.key"><Link :href="entry.href()" class="underline">{{ t(entry.label) }}</Link></li>
    </ul>
</template>
