<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import CountsReport, { type CountsReportData } from '../../components/CountsReport.vue';
import { announce } from '../../components/feedback/status';
import FileField from '../../components/fields/FileField.vue';
import SelectField from '../../components/fields/SelectField.vue';
import TextField from '../../components/fields/TextField.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import AppButton from '../../components/ui/AppButton.vue';
import { postFile } from '../../components/upload';
import { t } from '../../i18n';
import { routes } from '../../routes';

const props = defineProps<{ organisations: { id: number; name: string }[] }>();

const totalKeys = ['cost_records', 'reused', 'elements', 'statements', 'allocation_sets', 'finalised_years'] as const;
const open = ref(false);
const file = ref<File | null>(null);
const target = ref('new');
const organisationName = ref('');
const canvasName = ref('');
const report = ref<CountsReportData | null>(null);
const refusal = ref('');
const busy = ref(false);
const inputKey = ref(0);
const canvasError = ref('');
const organisationError = ref('');
const panel = ref<HTMLElement>();
const options = computed(() => [{ value: 'new', label: t('jsonx.target_new') }, ...props.organisations.map(({ id, name }) => ({ value: String(id), label: name }))]);
const canCheck = computed(() => file.value !== null && canvasName.value.trim() !== '' && (target.value !== 'new' || organisationName.value.trim() !== '') && !busy.value);
const canCommit = computed(() => report.value !== null && report.value.count === 0 && !busy.value);

async function send(url: string): Promise<Response> {
    busy.value = true;
    refusal.value = '';
    canvasError.value = '';
    organisationError.value = '';
    const fields: Record<string, string | Blob> = { file: file.value as File, canvas_name: canvasName.value };
    if (target.value === 'new') fields.organisation_name = organisationName.value;
    else fields.organisation_id = target.value;
    try {
        return await postFile(url, fields);
    } finally {
        busy.value = false;
    }
}

async function refused(response: Response): Promise<void> {
    const data = await response.json();
    const errors = data.errors ?? {};
    canvasError.value = errors.canvas_name?.[0] ?? '';
    organisationError.value = errors.organisation_name?.[0] ?? errors.organisation_id?.[0] ?? '';
    refusal.value = errors.file?.[0] ?? (canvasError.value || organisationError.value ? '' : (Object.values(errors).flat()[0] as string | undefined) ?? data.message ?? '');
    report.value = null;
    announce([refusal.value, organisationError.value, canvasError.value].filter(Boolean).join(' '));
    await nextTick();
    focusFirstInvalid(panel.value);
}

async function check(): Promise<void> {
    const response = await send(routes.canvasImportPreview());
    if (!response.ok) return refused(response);
    report.value = await response.json();
    const count = report.value!.count;
    announce(count === 0 ? t('jsonx.checked_clean') : t('jsonx.checked_errors', { count }));
}

async function commit(): Promise<void> {
    const response = await send(routes.canvasImport());
    if (!response.ok) return refused(response);
    report.value = null;
    file.value = null;
    inputKey.value++;
    canvasName.value = '';
    announce(t('jsonx.done'));
    router.reload();
}

function pick(picked: File | null): void {
    file.value = picked;
    report.value = null;
    refusal.value = '';
}
</script>

<template>
    <div class="mt-3">
        <AppButton :aria-expanded="open" aria-controls="import-canvas-panel" @click="open = !open">{{ t('jsonx.import_open') }}</AppButton>
        <section v-show="open" id="import-canvas-panel" ref="panel" role="region" aria-labelledby="import-canvas-heading" class="mt-3 max-w-xl rounded-panel border border-hairline bg-white p-6">
            <h2 id="import-canvas-heading" class="font-display text-[16px] font-semibold">{{ t('jsonx.import_open') }}</h2>
            <form novalidate class="mt-4 flex flex-col gap-4" @submit.prevent="check">
                <FileField :key="inputKey" id="import-canvas-file" :label="t('jsonx.file')" accept=".json,application/json" :hint="t('jsonx.file_hint')" :error="refusal" @pick="pick" />
                <SelectField id="import-canvas-target" v-model="target" :label="t('jsonx.target')" :options="options" />
                <TextField v-if="target === 'new'" id="import-canvas-organisation" v-model="organisationName" :label="t('jsonx.organisation_name')" :error="organisationError" />
                <TextField id="import-canvas-name" v-model="canvasName" :label="t('jsonx.canvas_name')" :error="canvasError" />
                <div class="flex gap-2">
                    <AppButton type="submit" variant="primary" :disabled="!canCheck">{{ t('import.check') }}</AppButton>
                    <AppButton :disabled="!canCommit" @click="commit">{{ t('import.commit') }}</AppButton>
                </div>
            </form>
            <CountsReport v-if="report" id="import-canvas" prefix="jsonx" :keys="totalKeys" :report="report" heading="h3" />
        </section>
    </div>
</template>
