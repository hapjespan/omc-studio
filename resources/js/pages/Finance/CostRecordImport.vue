<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ImportReport, { type ImportReportData } from '../../components/ImportReport.vue';
import { announce } from '../../components/feedback/status';
import { t } from '../../i18n';
import { routes } from '../../routes';

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
    announce(errors === 0 ? t('import.checked_clean') : t('import.checked_errors', { count: errors }));
}

async function commit(): Promise<void> {
    const response = await send(routes.costRecordImport(props.organisation.id));
    if (!response) return;
    if (!response.ok) return refused(response);
    const totals = (await response.json()).totals;
    report.value = null;
    announce(t('import.done', { new: totals.new, updated: totals.updated, unchanged: totals.unchanged }));
}

function pick(event: Event): void {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
    report.value = null;
    refusal.value = '';
}
</script>

<template>
    <Head :title="t('import.title')" />
    <h1 class="text-[20px] font-semibold">{{ t('import.title') }}</h1>
    <form novalidate class="mt-3 flex max-w-xl flex-col gap-3" @submit.prevent="check">
        <div class="flex flex-col gap-1">
            <label for="import-file" class="text-[13px] font-semibold">{{ t('import.file') }}</label>
            <p id="import-file-hint" class="text-[11.5px] text-[#5F6772]">{{ t('import.file_hint', { columns: columns.join(';') }) }}</p>
            <input id="import-file" type="file" accept=".csv,text/csv,text/plain" :aria-describedby="refusal ? 'import-file-hint import-file-error' : 'import-file-hint'" :aria-invalid="refusal ? 'true' : undefined" @change="pick" />
            <p v-if="refusal" id="import-file-error" class="text-[12.5px] font-medium text-[#A12A1E]">{{ refusal }}</p>
        </div>
        <div class="flex gap-2">
            <button type="submit" :disabled="!file || busy" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('import.check') }}</button>
            <button type="button" :disabled="!canCommit" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="commit">{{ t('import.commit') }}</button>
        </div>
    </form>
    <ImportReport v-if="report" :report="report" />
</template>
