<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import CsvImportPanel from '../../components/CsvImportPanel.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

defineProps<{ canvasId: number }>();
</script>

<template>
    <CsvImportPanel id="import-structure" :heading="t('csvx.structure_import')" :hint="t('csvx.structure_hint')" :preview-url="routes.structureImportPreview(canvasId)" :commit-url="routes.structureImport(canvasId)" :sample-url="routes.sampleFile('structure')" @done="router.reload()" />
</template>
