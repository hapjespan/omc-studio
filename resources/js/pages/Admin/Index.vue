<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { t } from '../../i18n';
import { adminEntries } from '../../shell/adminEntries';
</script>

<template>
    <Head :title="t('shell.tab.admin')" />
    <h1 class="font-display text-[22px] font-semibold">{{ t('shell.tab.admin') }}</h1>
    <ul class="mt-4 flex max-w-sm flex-col rounded-panel border border-hairline bg-white text-[13px]">
        <li v-for="entry in adminEntries()" :key="entry.key" class="border-t border-hairline first:border-t-0"><Link :href="entry.href()" class="block px-4 py-3 font-medium">{{ t(entry.label) }}</Link></li>
    </ul>
</template>
