<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import { t } from '../../i18n';
import { routes } from '../../routes';

interface Report {
    errors: { location: string; message: string }[];
    count: number;
    totals: Record<'organisations' | 'canvases' | 'cost_records' | 'templates', number>;
}

const totalKeys = ['organisations', 'canvases', 'cost_records', 'templates'] as const;
const file = ref<File | null>(null);
const report = ref<Report | null>(null);
const refusal = ref('');
const busy = ref(false);
const canCommit = computed(() => report.value !== null && report.value.count === 0 && !busy.value);

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
    const data = response.status === 422 ? await response.json().catch(() => null) : null;
    refusal.value = data?.errors?.file?.[0] ?? data?.errors?.rule?.[0] ?? t('fullx.refused_failed');
    report.value = null;
    announce(refusal.value);
    await nextTick();
    document.getElementById('full-file')?.focus();
}

async function check(): Promise<void> {
    const response = await send(routes.fullImportPreview());
    if (!response) return;
    if (!response.ok) return refused(response);
    report.value = await response.json();
    const count = report.value!.count;
    announce(count === 0 ? t('fullx.checked_clean') : t('fullx.checked_errors', { count }));
}

async function commit(): Promise<void> {
    const response = await send(routes.fullImport());
    if (!response) return;
    if (!response.ok) return refused(response);
    report.value = null;
    announce(t('fullx.done'));
}

function pick(event: Event): void {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
    report.value = null;
    refusal.value = '';
}
</script>

<template>
    <Head :title="t('fullx.title')" />
    <h1 class="text-[20px] font-semibold">{{ t('fullx.title') }}</h1>
    <section aria-labelledby="full-export" class="mt-4 max-w-xl">
        <h2 id="full-export" class="text-[16px] font-semibold">{{ t('fullx.export_heading') }}</h2>
        <p class="mt-1 text-[13px]">{{ t('fullx.export_hint') }}</p>
        <a :href="routes.fullExport()" download class="mt-2 inline-block rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('fullx.export') }}</a>
    </section>
    <section aria-labelledby="full-import" class="mt-6 max-w-xl">
        <h2 id="full-import" class="text-[16px] font-semibold">{{ t('fullx.import_heading') }}</h2>
        <form novalidate class="mt-2 flex flex-col gap-3" @submit.prevent="check">
            <div class="flex flex-col gap-1">
                <label for="full-file" class="text-[13px] font-semibold">{{ t('fullx.file') }}</label>
                <p id="full-file-hint" class="text-[11.5px] text-[#5F6772]">{{ t('fullx.file_hint') }}</p>
                <input id="full-file" type="file" accept=".json,application/json" :aria-describedby="refusal ? 'full-file-hint full-file-error' : 'full-file-hint'" :aria-invalid="refusal ? 'true' : undefined" @change="pick" />
                <p v-if="refusal" id="full-file-error" class="text-[12.5px] font-medium text-[#A12A1E]">{{ refusal }}</p>
            </div>
            <div class="flex gap-2">
                <button type="submit" :disabled="!file || busy" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('fullx.check') }}</button>
                <button type="button" :disabled="!canCommit" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="commit">{{ t('fullx.commit') }}</button>
            </div>
        </form>
    </section>
    <section v-if="report" role="region" aria-labelledby="full-summary" class="mt-4">
        <h2 id="full-summary" class="text-[16px] font-semibold">{{ t('fullx.summary') }}</h2>
        <table class="mt-2 text-[13px]">
            <caption class="sr-only">{{ t('fullx.summary') }}</caption>
            <thead><tr><th scope="col" class="pr-6 text-left">{{ t('fullx.col_kind') }}</th><th scope="col" class="text-right">{{ t('fullx.col_count') }}</th></tr></thead>
            <tbody>
                <tr v-for="key in totalKeys" :key="key"><th scope="row" class="pr-6 text-left font-normal">{{ t(`fullx.total_${key}`) }}</th><td class="text-right">{{ report.totals[key] }}</td></tr>
                <tr><th scope="row" class="pr-6 text-left font-normal">{{ t('fullx.total_errors') }}</th><td class="text-right">{{ report.count }}</td></tr>
            </tbody>
        </table>
        <table v-if="report.errors.length" class="mt-4 w-full text-[13px]">
            <caption class="sr-only">{{ t('fullx.errors') }}</caption>
            <thead><tr><th scope="col" class="text-left">{{ t('fullx.col_location') }}</th><th scope="col" class="text-left">{{ t('fullx.col_message') }}</th></tr></thead>
            <tbody>
                <tr v-for="(error, index) in report.errors" :key="index" class="align-top"><td class="pr-4">{{ error.location }}</td><td class="text-[#A12A1E]">{{ error.message }}</td></tr>
            </tbody>
        </table>
    </section>
</template>
