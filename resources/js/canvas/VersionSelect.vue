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
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppButton from '../components/ui/AppButton.vue';
import { t } from '../i18n';
import { routes } from '../routes';
import ChipSelect from './ChipSelect.vue';

const props = defineProps<{ canvasId: number; versions: VersionOption[]; current: number | null }>();
const initial = props.current === null ? '' : String(props.current);
const chosen = ref(initial);
const changed = computed(() => chosen.value !== initial);
const label = (version: VersionOption): string => t(version.archived ? 'snap.option_archived' : 'snap.option', { name: version.name, year: version.year, date: version.date });
const open = (): void => {
    router.get(chosen.value === '' ? routes.view(props.canvasId) : routes.snapshotView(chosen.value));
};
</script>

<template>
    <div class="flex items-center gap-2">
        <ChipSelect id="version-select" v-model="chosen" :label="t('snap.version')">
            <option value="">{{ t('snap.version_live') }}</option>
            <option v-for="version in versions" :key="version.id" :value="String(version.id)">{{ label(version) }}</option>
        </ChipSelect>
        <AppButton v-if="changed" @click="open">{{ t('snap.open') }}</AppButton>
    </div>
</template>
