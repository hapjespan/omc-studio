<script setup lang="ts">
import Panel from '../../components/ui/Panel.vue';
import { computed, nextTick, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import CountsReport, { type CountsReportData } from '../../components/CountsReport.vue';
import { announce } from '../../components/feedback/status';
import FileField from '../../components/fields/FileField.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { plural, t } from '../../i18n';
import { exportFull } from '../../canvas/csvDownload';
import { routes } from '../../routes';
import { useQuietStatus } from '../../shell/layout';
import { xsrfToken } from '../../xsrf';

useQuietStatus();

const totalKeys = ['organisations', 'canvases', 'cost_records', 'templates'] as const;
const file = ref<File | null>(null);
const report = ref<CountsReportData | null>(null);
const refusal = ref('');
const busy = ref(false);
const exported = ref('');
const canCommit = computed(() => report.value !== null && report.value.count === 0 && !busy.value);

async function send(url: string): Promise<Response | null> {
    if (!file.value) return null;
    const token = xsrfToken();
    const body = new FormData();
    body.append('file', file.value);
    busy.value = true;
    refusal.value = '';
    try {
        return await fetch(url, { method: 'POST', body, credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': token, Accept: 'application/json' } });
    } catch {
        return new Response(null, { status: 503 });
    } finally {
        busy.value = false;
    }
}

async function refused(response: Response): Promise<void> {
    const data = response.status === 422 ? await response.json().catch(() => null) : null;
    refusal.value = data?.errors?.file?.[0] ?? data?.errors?.rule?.[0] ?? t('fullx.refused_failed');
    report.value = null;
    announce(refusal.value);
    await nextTick();
    document.getElementById('full-file')?.focus();
}

async function exportAll(): Promise<void> {
    exported.value = await exportFull(routes.fullExport(), 'full-export.json');
    announce(exported.value);
}

async function check(): Promise<void> {
    const response = await send(routes.fullImportPreview());
    if (!response) return;
    if (!response.ok) return refused(response);
    report.value = await response.json();
    const count = report.value!.count;
    announce(count === 0 ? t('fullx.checked_clean') : plural('fullx.checked_errors', count));
}

async function commit(): Promise<void> {
    const response = await send(routes.fullImport());
    if (!response) return;
    if (!response.ok) return refused(response);
    report.value = null;
    announce(t('fullx.done'));
}

function pick(picked: File | null): void {
    file.value = picked;
    report.value = null;
    refusal.value = '';
}
</script>

<template>
    <Head :title="t('fullx.title')" />
    <div class="flex max-w-3xl flex-col gap-4">
        <h1 class="font-display text-[20px] font-semibold tracking-[-0.2px]">{{ t('fullx.title') }}</h1>
        <Panel :padding="24" role="region" aria-labelledby="full-export">
            <h2 id="full-export" class="font-display text-[16px] font-semibold">{{ t('fullx.export_heading') }}</h2>
            <p class="mt-1 text-[13px] text-secondary">{{ t('fullx.export_hint') }}</p>
            <AppButton class="mt-4" @click="exportAll">{{ t('fullx.export') }}</AppButton>
            <p v-if="exported" class="mt-2 text-[13px]" data-testid="full-export-result">{{ exported }}</p>
        </Panel>
        <Panel :padding="24" role="region" aria-labelledby="full-import">
            <h2 id="full-import" class="font-display text-[16px] font-semibold">{{ t('fullx.import_heading') }}</h2>
            <form novalidate class="mt-4 flex flex-col gap-4" @submit.prevent="check">
                <FileField id="full-file" :label="t('fullx.file')" accept=".json,application/json" :hint="t('fullx.file_hint')" :error="refusal" @pick="pick" />
                <div class="flex gap-2">
                    <AppButton type="submit" variant="primary" :disabled="!file || busy">{{ t('fullx.check') }}</AppButton>
                    <AppButton :disabled="!canCommit" @click="commit">{{ t('fullx.commit') }}</AppButton>
                </div>
            </form>
        </Panel>
        <Panel :padding="24" v-if="report"><CountsReport id="full" prefix="fullx" :keys="totalKeys" :report="report" /></Panel>
    </div>
</template>
