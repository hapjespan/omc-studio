<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { announce } from './feedback/status';
import ImportReport, { type ImportReportData } from './ImportReport.vue';
import { postFile, refusalOf } from './upload';
import { t } from '../i18n';

const props = defineProps<{ id: string; heading: string; hint: string; previewUrl: string; commitUrl: string }>();
const emit = defineEmits<{ done: [] }>();

const open = ref(false);
const file = ref<File | null>(null);
const report = ref<ImportReportData | null>(null);
const refusal = ref('');
const busy = ref(false);
const inputKey = ref(0);
const canCommit = computed(() => report.value !== null && report.value.totals.errors === 0 && !busy.value);

async function send(url: string): Promise<Response | null> {
    if (!file.value) return null;
    busy.value = true;
    refusal.value = '';
    try {
        return await postFile(url, { file: file.value });
    } finally {
        busy.value = false;
    }
}

async function refused(response: Response): Promise<void> {
    refusal.value = await refusalOf(response);
    report.value = null;
    announce(refusal.value);
    await nextTick();
    document.getElementById(`${props.id}-file`)?.focus();
}

async function check(): Promise<void> {
    const response = await send(props.previewUrl);
    if (!response) return;
    if (!response.ok) return refused(response);
    report.value = await response.json();
    const errors = report.value!.totals.errors;
    announce(errors === 0 ? t('import.checked_clean') : t('import.checked_errors', { count: errors }));
}

async function commit(): Promise<void> {
    const response = await send(props.commitUrl);
    if (!response) return;
    if (!response.ok) return refused(response);
    const totals = (await response.json()).totals;
    report.value = null;
    file.value = null;
    inputKey.value++;
    announce(t('import.done', { new: totals.new, updated: totals.updated, unchanged: totals.unchanged }));
    emit('done');
}

function pick(event: Event): void {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
    report.value = null;
    refusal.value = '';
}
</script>

<template>
    <div class="mt-3">
        <button type="button" :aria-expanded="open" :aria-controls="`${id}-panel`" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="open = !open">{{ heading }}</button>
        <section v-show="open" :id="`${id}-panel`" role="region" :aria-labelledby="`${id}-heading`" class="mt-2 max-w-xl">
            <h2 :id="`${id}-heading`" class="text-[16px] font-semibold">{{ heading }}</h2>
            <form novalidate class="mt-2 flex flex-col gap-3" @submit.prevent="check">
                <div class="flex flex-col gap-1">
                    <label :for="`${id}-file`" class="text-[13px] font-semibold">{{ t('import.file') }}</label>
                    <p :id="`${id}-hint`" class="text-[11.5px] text-[#5F6772]">{{ hint }}</p>
                    <input :id="`${id}-file`" :key="inputKey" type="file" accept=".csv,text/csv,text/plain" :aria-describedby="refusal ? `${id}-hint ${id}-error` : `${id}-hint`" :aria-invalid="refusal ? 'true' : undefined" @change="pick" />
                    <p v-if="refusal" :id="`${id}-error`" class="text-[12.5px] font-medium text-[#A12A1E]">{{ refusal }}</p>
                </div>
                <div class="flex gap-2">
                    <button type="submit" :disabled="!file || busy" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('import.check') }}</button>
                    <button type="button" :disabled="!canCommit" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="commit">{{ t('import.commit') }}</button>
                </div>
            </form>
            <ImportReport v-if="report" :report="report" heading="h3" />
        </section>
    </div>
</template>
