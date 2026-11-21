<script setup lang="ts">
import Panel from '../../components/ui/Panel.vue';
import { computed, nextTick, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ImportReport, { type ImportReportData } from '../../components/ImportReport.vue';
import { announce } from '../../components/feedback/status';
import FileField from '../../components/fields/FileField.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { plural, t } from '../../i18n';
import { routes } from '../../routes';
import { useQuietStatus } from '../../shell/layout';

useQuietStatus();

const props = defineProps<{ organisation: { id: number; name: string }; columns: string[] }>();

const file = ref<File | null>(null);
const report = ref<ImportReportData | null>(null);
const refusal = ref('');
const busy = ref(false);
const canCommit = computed(() => report.value !== null && report.value.totals.errors === 0 && !busy.value);

async function send(url: string): Promise<Response | null> {
    if (!file.value) return null;
    const token = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');
    const body = new FormData();
    body.append('file', file.value);
    busy.value = true;
    refusal.value = '';
    try {
        return await fetch(url, { method: 'POST', body, credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': token, Accept: 'application/json' } });
    } finally {
        busy.value = false;
    }
}

async function refused(response: Response): Promise<void> {
    const data = await response.json();
    refusal.value = data.errors?.file?.[0] ?? data.errors?.rule?.[0] ?? data.message ?? '';
    report.value = null;
    announce(refusal.value);
    await nextTick();
    document.getElementById('import-file')?.focus();
}

async function check(): Promise<void> {
    const response = await send(routes.costRecordImportPreview(props.organisation.id));
    if (!response) return;
    if (!response.ok) return refused(response);
    report.value = await response.json();
    const errors = report.value!.totals.errors;
    announce(errors === 0 ? t('import.checked_clean') : plural('import.checked_errors', errors));
}

async function commit(): Promise<void> {
    const response = await send(routes.costRecordImport(props.organisation.id));
    if (!response) return;
    if (!response.ok) return refused(response);
    const totals = (await response.json()).totals;
    report.value = null;
    announce(t('import.done', { new: totals.new, updated: totals.updated, unchanged: totals.unchanged }));
}

function pick(picked: File | null): void {
    file.value = picked;
    report.value = null;
    refusal.value = '';
}
</script>

<template>
    <Head :title="t('import.title')" />
    <div class="flex max-w-3xl flex-col gap-4">
        <h1 class="font-display text-page-title font-semibold tracking-[-0.2px]">{{ t('import.title') }}</h1>
        <form novalidate class="flex flex-col gap-4 rounded-panel border border-hairline bg-white p-6" @submit.prevent="check">
            <FileField id="import-file" :label="t('import.file')" accept=".csv,text/csv,text/plain" :hint="t('import.file_hint', { columns: columns.join(';') })" :error="refusal" @pick="pick" />
            <div class="flex gap-2">
                <AppButton type="submit" variant="primary" :disabled="!file || busy">{{ t('import.check') }}</AppButton>
                <AppButton :disabled="!canCommit" @click="commit">{{ t('import.commit') }}</AppButton>
            </div>
        </form>
        <Panel :padding="24" v-if="report"><ImportReport :report="report" /></Panel>
    </div>
</template>
