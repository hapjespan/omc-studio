<script setup lang="ts">
import Panel from './ui/Panel.vue';
import { computed, nextTick, ref } from 'vue';
import { announce } from './feedback/status';
import FileField from './fields/FileField.vue';
import SampleGuide from './SampleGuide.vue';
import AppButton from './ui/AppButton.vue';
import ImportReport, { type ImportReportData } from './ImportReport.vue';
import { postFile, refusalOf } from './upload';
import { plural, t } from '../i18n';

const props = defineProps<{ id: string; heading: string; hint: string; previewUrl: string; commitUrl: string; sampleUrl: string; sampleGroup: string }>();
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
    announce(errors === 0 ? t('import.checked_clean') : plural('import.checked_errors', errors));
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

function pick(picked: File | null): void {
    file.value = picked;
    report.value = null;
    refusal.value = '';
}
</script>

<template>
    <div class="mt-3">
        <AppButton :aria-expanded="open" :aria-controls="`${id}-panel`" @click="open = !open">{{ heading }}</AppButton>
        <Panel :padding="24" class="mt-3 max-w-xl" v-show="open" :id="`${id}-panel`" role="region" :aria-labelledby="`${id}-heading`">
            <h2 :id="`${id}-heading`" class="font-display text-[16px] font-semibold">{{ heading }}</h2>
            <form novalidate class="mt-4 flex flex-col gap-4" @submit.prevent="check">
                <FileField :key="inputKey" :id="`${id}-file`" :label="t('import.file')" accept=".csv,text/csv,text/plain" :hint="hint" :error="refusal" @pick="pick" />
                <SampleGuide :group="sampleGroup" :url="sampleUrl" />
                <div class="flex gap-2">
                    <AppButton type="submit" variant="primary" :disabled="!file || busy">{{ t('import.check') }}</AppButton>
                    <AppButton :disabled="!canCommit" @click="commit">{{ t('import.commit') }}</AppButton>
                </div>
            </form>
            <ImportReport v-if="report" :report="report" heading="h3" />
        </Panel>
    </div>
</template>
