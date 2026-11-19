<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import SelectField from '../../components/fields/SelectField.vue';
import TextField from '../../components/fields/TextField.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import { postFile } from '../../components/upload';
import { t } from '../../i18n';
import { routes } from '../../routes';

interface Report {
    errors: { location: string; message: string }[];
    count: number;
    totals: Record<(typeof totalKeys)[number], number>;
}

const props = defineProps<{ organisations: { id: number; name: string }[] }>();

const totalKeys = ['cost_records', 'reused', 'elements', 'statements', 'allocation_sets', 'finalised_years'] as const;
const open = ref(false);
const file = ref<File | null>(null);
const target = ref('new');
const organisationName = ref('');
const canvasName = ref('');
const report = ref<Report | null>(null);
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

function pick(event: Event): void {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
    report.value = null;
    refusal.value = '';
}
</script>

<template>
    <div class="mt-3">
        <button type="button" :aria-expanded="open" aria-controls="import-canvas-panel" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="open = !open">{{ t('jsonx.import_open') }}</button>
        <section v-show="open" id="import-canvas-panel" ref="panel" role="region" aria-labelledby="import-canvas-heading" class="mt-2 max-w-xl">
            <h2 id="import-canvas-heading" class="text-[16px] font-semibold">{{ t('jsonx.import_open') }}</h2>
            <form novalidate class="mt-2 flex flex-col gap-3" @submit.prevent="check">
                <div class="flex flex-col gap-1">
                    <label for="import-canvas-file" class="text-[13px] font-semibold">{{ t('jsonx.file') }}</label>
                    <p id="import-canvas-hint" class="text-[11.5px] text-[#5F6772]">{{ t('jsonx.file_hint') }}</p>
                    <input id="import-canvas-file" :key="inputKey" type="file" accept=".json,application/json" :aria-describedby="refusal ? 'import-canvas-hint import-canvas-error' : 'import-canvas-hint'" :aria-invalid="refusal ? 'true' : undefined" @change="pick" />
                    <p v-if="refusal" id="import-canvas-error" class="text-[12.5px] font-medium text-[#A12A1E]">{{ refusal }}</p>
                </div>
                <SelectField id="import-canvas-target" v-model="target" :label="t('jsonx.target')" :options="options" />
                <TextField v-if="target === 'new'" id="import-canvas-organisation" v-model="organisationName" :label="t('jsonx.organisation_name')" :error="organisationError" />
                <TextField id="import-canvas-name" v-model="canvasName" :label="t('jsonx.canvas_name')" :error="canvasError" />
                <div class="flex gap-2">
                    <button type="submit" :disabled="!canCheck" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold">{{ t('import.check') }}</button>
                    <button type="button" :disabled="!canCommit" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="commit">{{ t('import.commit') }}</button>
                </div>
            </form>
            <section v-if="report" role="region" aria-labelledby="import-canvas-summary" class="mt-4">
                <h3 id="import-canvas-summary" class="text-[16px] font-semibold">{{ t('jsonx.summary') }}</h3>
                <table class="mt-2 text-[13px]">
                    <caption class="sr-only">{{ t('jsonx.summary') }}</caption>
                    <thead><tr><th scope="col" class="pr-6 text-left">{{ t('jsonx.col_kind') }}</th><th scope="col" class="text-right">{{ t('jsonx.col_count') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="key in totalKeys" :key="key"><th scope="row" class="pr-6 text-left font-normal">{{ t(`jsonx.total_${key}`) }}</th><td class="text-right">{{ report.totals[key] }}</td></tr>
                        <tr><th scope="row" class="pr-6 text-left font-normal">{{ t('jsonx.total_errors') }}</th><td class="text-right">{{ report.count }}</td></tr>
                    </tbody>
                </table>
                <table v-if="report.errors.length" class="mt-4 w-full text-[13px]">
                    <caption class="sr-only">{{ t('jsonx.errors') }}</caption>
                    <thead><tr><th scope="col" class="text-left">{{ t('jsonx.col_location') }}</th><th scope="col" class="text-left">{{ t('jsonx.col_message') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="(error, index) in report.errors" :key="index" class="align-top"><td class="pr-4">{{ error.location }}</td><td class="text-[#A12A1E]">{{ error.message }}</td></tr>
                    </tbody>
                </table>
            </section>
        </section>
    </div>
</template>
