<script setup lang="ts">
import { nextTick, reactive, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import Field from '../../components/fields/Field.vue';
import TextField from '../../components/fields/TextField.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

interface Notice {
    id: number;
    kind: string;
    canvas: string | null;
    details: { year?: number; filter?: Record<string, string>; check?: string; differenceCents?: number; snapshot?: string };
    count: number;
    date: string;
    last_time: string;
    seen: boolean;
}
type Filters = Partial<Record<'kind' | 'canvas_id' | 'from' | 'to', string | number>>;

const props = defineProps<{
    notices: Notice[];
    total: number;
    filters: Filters;
    filterErrors: Record<string, string>;
    options: { kinds: string[]; canvases: { id: number; name: string }[] };
}>();

const form = reactive({
    kind: String(props.filters.kind ?? ''),
    canvas_id: String(props.filters.canvas_id ?? ''),
    from: String(props.filters.from ?? ''),
    to: String(props.filters.to ?? ''),
});

const selects = [
    { id: 'kind', label: 'notices.filter.kind', options: () => props.options.kinds.map((kind) => ({ value: kind, label: t(`notices.kind.${kind}`) })) },
    { id: 'canvas_id', label: 'notices.filter.canvas', options: () => props.options.canvases.map((o) => ({ value: String(o.id), label: o.name })) },
] as const;

watch(() => props.filterErrors, (errors) => Object.keys(errors).length && nextTick(focusFirstInvalid), { immediate: true });

function apply(): void {
    router.get(routes.systemNotices(), Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '')));
}

function reset(): void {
    Object.keys(form).forEach((key) => (form[key as keyof typeof form] = ''));
    apply();
}

const filterText = (filter?: Record<string, string>): string => {
    const pairs = Object.entries(filter ?? {}).map(([key, value]) => `${key}: ${value}`);

    return pairs.length ? pairs.join(', ') : t('notices.no_filter');
};
const showCount = (notice: Notice): boolean => notice.count > 1 || notice.kind === 'repeated_lockouts';
</script>

<template>
    <Head :title="t('notices.title')" />
    <h1 class="text-[20px] font-semibold">{{ t('notices.title') }}</h1>
    <ErrorSummary v-if="Object.keys(filterErrors).length" :errors="filterErrors" />
    <form class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" novalidate @submit.prevent="apply">
        <Field v-for="select in selects" :id="select.id" :key="select.id" :label="t(select.label)" :error="filterErrors[select.id]" v-slot="{ describedby, invalid }">
            <select :id="select.id" v-model="form[select.id]" :aria-describedby="describedby" :aria-invalid="invalid || undefined" class="rounded-md border border-[#848B95] bg-white px-2 py-1.5 text-[13px] text-[#171B21]">
                <option value="">{{ t('audit_log.filter.all') }}</option>
                <option v-for="option in select.options()" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
        </Field>
        <TextField id="from" v-model="form.from" :label="t('audit_log.filter.from')" type="date" :error="filterErrors.from" />
        <TextField id="to" v-model="form.to" :label="t('audit_log.filter.to')" type="date" :error="filterErrors.to" />
        <div class="flex items-end gap-2">
            <button type="submit" class="min-h-6 rounded-md bg-[#1F3A5F] px-3 py-2 text-[13px] font-semibold text-white">{{ t('audit_log.filter.apply') }}</button>
            <button type="button" class="min-h-6 rounded-md border border-[#848B95] px-3 py-2 text-[13px]" @click="reset">{{ t('audit_log.filter.reset') }}</button>
        </div>
    </form>
    <p role="status" class="mt-4 text-[13px]" data-testid="notice-count">{{ t('notices.count', { count: total }) }}</p>
    <ol class="mt-2 flex flex-col gap-3">
        <li v-for="notice in notices" :key="notice.id" class="rounded-md border border-[#848B95] p-3 text-[13px]">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-[max-content_1fr]">
                <dt class="font-semibold">{{ t('notices.column.time') }}</dt>
                <dd><time>{{ notice.date }} {{ notice.last_time }}</time></dd>
                <dt class="font-semibold">{{ t('notices.column.kind') }}</dt>
                <dd>{{ t(`notices.kind.${notice.kind}`) }}</dd>
                <template v-if="notice.canvas">
                    <dt class="font-semibold">{{ t('notices.column.canvas') }}</dt>
                    <dd>{{ notice.canvas }}</dd>
                </template>
                <template v-if="notice.details.snapshot">
                    <dt class="font-semibold">{{ t('notices.column.snapshot') }}</dt>
                    <dd>{{ notice.details.snapshot }}</dd>
                </template>
                <template v-if="notice.details.year">
                    <dt class="font-semibold">{{ t('notices.column.year') }}</dt>
                    <dd>{{ notice.details.year }}</dd>
                </template>
                <template v-if="notice.kind === 'closing_check_failed'">
                    <dt class="font-semibold">{{ t('notices.column.filter') }}</dt>
                    <dd>{{ filterText(notice.details.filter) }}</dd>
                    <dt class="font-semibold">{{ t('notices.column.check') }}</dt>
                    <dd>{{ notice.details.check }}</dd>
                    <dt class="font-semibold">{{ t('notices.column.difference') }}</dt>
                    <dd>{{ t('notices.cents', { cents: notice.details.differenceCents ?? 0 }) }}</dd>
                </template>
                <template v-if="showCount(notice)">
                    <dt class="font-semibold">{{ t('notices.column.count') }}</dt>
                    <dd>{{ t('notices.times', { count: notice.count, time: notice.last_time }) }}</dd>
                </template>
            </dl>
            <p v-if="notice.seen" class="mt-2 text-[#4A5260]">{{ t('notices.seen') }}</p>
            <button v-else type="button" class="mt-2 min-h-6 rounded-md border border-[#848B95] px-3 py-2 text-[13px]" :aria-label="t('notices.mark_seen_for', { kind: t(`notices.kind.${notice.kind}`), date: notice.date, time: notice.last_time })" @click="router.post(routes.systemNoticeSeen(notice.id), {}, { preserveScroll: true })">{{ t('notices.mark_seen') }}</button>
        </li>
    </ol>
    <p v-if="!notices.length" class="mt-2 text-[13px]">{{ t('notices.empty') }}</p>
</template>
