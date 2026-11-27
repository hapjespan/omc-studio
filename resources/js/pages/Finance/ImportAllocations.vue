<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import CsvImportPanel from '../../components/CsvImportPanel.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

defineProps<{ canvasId: number }>();
</script>

<template>
    <CsvImportPanel id="import-allocations" :heading="t('csvx.allocations_import')" :hint="t('csvx.allocations_hint')" :preview-url="routes.allocationImportPreview(canvasId)" :commit-url="routes.allocationImport(canvasId)" :sample-url="routes.sampleFile('allocation')" sample-group="allocation" @done="router.reload()" />
</template>
