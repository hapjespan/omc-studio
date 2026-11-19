<script lang="ts">
export interface VersionOption {
    id: number;
    name: string;
    year: number;
    date: string;
    archived: boolean;
}
</script>

<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { t } from '../i18n';
import { routes } from '../routes';

const props = defineProps<{ canvasId: number; versions: VersionOption[]; current: number | null }>();
const chosen = ref(props.current === null ? '' : String(props.current));
const label = (version: VersionOption): string => t(version.archived ? 'snap.option_archived' : 'snap.option', { name: version.name, year: version.year, date: version.date });
const open = (): void => {
    router.get(chosen.value === '' ? routes.view(props.canvasId) : routes.snapshotView(chosen.value));
};
</script>

<template>
    <div class="flex items-center gap-2 text-[13px]">
        <label for="version-select">{{ t('snap.version') }}</label>
        <select id="version-select" v-model="chosen" class="rounded-md border border-[#848B95] bg-white px-2 py-1">
            <option value="">{{ t('snap.version_live') }}</option>
            <option v-for="version in versions" :key="version.id" :value="String(version.id)">{{ label(version) }}</option>
        </select>
        <button type="button" class="rounded-md border border-[#848B95] px-3 py-1" @click="open">{{ t('snap.open') }}</button>
    </div>
</template>
