<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import TextArea from '../../components/fields/TextArea.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

interface PreviewLine {
    line: number;
    level: number;
    kind: string;
    name: string;
    status: 'new' | 'exists';
}

const props = defineProps<{ canvasId: number; area: string; areaLabel: string }>();
const open = ref(false);
const text = ref('');
const lines = ref<PreviewLine[]>([]);
const errors = ref<{ line: number; message: string }[]>([]);
const refusal = ref('');
const pending = ref(false);
const opener = ref<HTMLButtonElement>();
const root = ref<HTMLElement>();
const field = (): HTMLTextAreaElement | null => root.value?.querySelector('textarea') ?? null;
let escaped = false;
let timer: ReturnType<typeof setTimeout> | undefined;
let sequence = 0;

const canSave = computed(() => !pending.value && errors.value.length === 0 && lines.value.some(({ status }) => status === 'new'));

async function show(): Promise<void> {
    open.value = true;
    await nextTick();
    field()?.focus();
}

async function close(): Promise<void> {
    open.value = false;
    text.value = '';
    await nextTick();
    opener.value?.focus();
}

function token(): string {
    return decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');
}

async function preview(): Promise<void> {
    const current = ++sequence;
    const response = await fetch(routes.quickEntryPreview(props.canvasId), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-XSRF-TOKEN': token(), Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ area: props.area, text: text.value }),
    }).catch(() => null);
    if (current !== sequence) return;
    const body = await response?.json().catch(() => ({})) ?? {};
    const ok = response?.ok === true;
    lines.value = ok ? body.lines : [];
    errors.value = ok ? body.errors : [];
    refusal.value = ok ? '' : (body.errors?.rule?.[0] ?? t('quick_entry.preview_failed'));
    pending.value = false;
}

watch(text, () => {
    pending.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => void preview(), 250);
});

onBeforeUnmount(() => clearTimeout(timer));

function keydown(event: KeyboardEvent): void {
    const target = event.target as HTMLTextAreaElement;
    if (target.id !== 'quick-entry-text') return;
    if (event.key === 'Escape') {
        escaped = true;
        return;
    }
    const leaving = event.key === 'Tab' && (escaped || event.shiftKey);
    escaped = false;
    if (event.key !== 'Tab' || leaving) return;
    event.preventDefault();
    const { selectionStart: from, selectionEnd: to, value } = target;
    if (from === to) {
        target.setRangeText('  ', from, to, 'end');
    } else {
        const start = value.lastIndexOf('\n', from - 1) + 1;
        target.setRangeText(value.slice(start, to).replace(/^/gm, '  '), start, to, 'preserve');
    }
    target.dispatchEvent(new Event('input'));
}

function goToLine(number: number): void {
    const element = field();
    if (!element) return;
    const before = text.value.split(/\r\n|\r|\n/).slice(0, number - 1);
    const start = before.reduce((offset, row) => offset + row.length + 1, 0);
    const length = text.value.split(/\r\n|\r|\n/)[number - 1]?.length ?? 0;
    element.focus();
    element.setSelectionRange(start, start + length);
}

function save(): void {
    refusal.value = '';
    router.post(routes.quickEntry(props.canvasId), { area: props.area, text: text.value }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: async () => {
            const count = lines.value.filter(({ status }) => status === 'new').length;
            await close();
            void announce(t('quick_entry.saved', { count }));
        },
        onError: (problems) => (refusal.value = problems.rule ?? t('quick_entry.preview_failed')),
    });
}
</script>

<template>
    <div ref="root" class="mt-3">
        <button v-if="!open" ref="opener" type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="show">{{ t('quick_entry.open') }}</button>
        <section v-else :aria-labelledby="`quick-entry-title-${area}`" class="rounded-[10px] border border-[#848B95] p-3" @keydown="keydown">
            <h3 :id="`quick-entry-title-${area}`" class="font-[Space_Grotesk] text-[15px] font-semibold">{{ t('quick_entry.title', { area: areaLabel }) }}</h3>
            <TextArea id="quick-entry-text" v-model="text" :label="t('quick_entry.label')" :hint="t('quick_entry.hint')" :rows="10" :error="errors.length ? t('quick_entry.error_count', { count: errors.length }) : undefined" />
            <h4 class="mt-3 text-[13px] font-semibold">{{ t('quick_entry.preview') }}</h4>
            <ul :aria-label="t('quick_entry.preview')" class="mt-1 flex flex-col gap-1">
                <li v-for="line in lines" :key="line.line" data-testid="quick-entry-line" class="flex gap-2 text-[13px]" :style="{ paddingLeft: `${line.level * 20}px` }">
                    <span>{{ line.name }}</span>
                    <span class="text-[12.5px] text-[#454C57]">{{ t(`quick_entry.kind.${line.kind}`) }}</span>
                    <span class="text-[12.5px] text-[#454C57]">{{ t(`quick_entry.${line.status}`) }}</span>
                </li>
            </ul>
            <div v-if="errors.length" role="group" :aria-label="t('quick_entry.errors')" class="mt-3">
                <h4 class="text-[13px] font-semibold text-[#A12A1E]">{{ t('quick_entry.errors') }}</h4>
                <ul class="mt-1 flex flex-col gap-1">
                    <li v-for="error in errors" :key="error.line" class="text-[12.5px] font-medium text-[#A12A1E]">
                        <a href="#quick-entry-text" class="underline" @click.prevent="goToLine(error.line)">{{ error.message }}</a>
                    </li>
                </ul>
            </div>
            <p v-if="refusal" role="alert" class="mt-2 text-[12.5px] font-medium text-[#A12A1E]">{{ refusal }}</p>
            <div class="mt-3 flex gap-2">
                <button type="button" :disabled="!canSave" class="rounded-md bg-[#1B1F27] px-3 py-1.5 text-[13px] font-semibold text-white disabled:opacity-50" @click="save">{{ t('quick_entry.save') }}</button>
                <button type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="close">{{ t('quick_entry.close') }}</button>
            </div>
        </section>
    </div>
</template>
