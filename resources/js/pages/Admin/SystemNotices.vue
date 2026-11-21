<script setup lang="ts">
import AppButton from '../../components/ui/AppButton.vue';
import { nextTick, reactive, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import NativeSelect from '../../components/fields/NativeSelect.vue';
import Pill from '../../components/ui/Pill.vue';
import TextField from '../../components/fields/TextField.vue';
import { plural, t } from '../../i18n';
import { routes } from '../../routes';
import { useQuietStatus } from '../../shell/layout';

useQuietStatus();

interface Notice {
    id: number;
    kind: string;
    canvas: string | null;
    details: { year?: number; filter?: string; check?: string; differenceCents?: number; snapshot?: string };
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

function markSeen(notice: Notice): void {
    router.post(routes.systemNoticeSeen(notice.id), {}, {
        preserveScroll: true,
        onSuccess: async () => {
            await nextTick();
            document.getElementById(`notice-seen-${notice.id}`)?.focus();
        },
    });
}
const showCount = (notice: Notice): boolean => notice.count > 1 || notice.kind === 'repeated_lockouts';
</script>

<template>
    <Head :title="t('notices.title')" />
    <div class="flex flex-col gap-4">
        <h1 class="font-display text-page-title font-semibold tracking-[-0.2px]">{{ t('notices.title') }}</h1>
        <ErrorSummary v-if="Object.keys(filterErrors).length" :errors="filterErrors" />
        <form class="grid grid-cols-1 gap-4 rounded-panel border border-hairline bg-white p-4 sm:grid-cols-2 lg:grid-cols-3" novalidate @submit.prevent="apply">
            <NativeSelect v-for="select in selects" :id="select.id" :key="select.id" v-model="form[select.id]" :label="t(select.label)" :error="filterErrors[select.id]" :empty-label="t('audit_log.filter.all')" :options="select.options()" />
            <TextField id="from" v-model="form.from" :label="t('audit_log.filter.from')" type="date" :error="filterErrors.from" />
            <TextField id="to" v-model="form.to" :label="t('audit_log.filter.to')" type="date" :error="filterErrors.to" />
            <div class="flex items-end gap-2">
                <AppButton variant="primary" type="submit">{{ t('audit_log.filter.apply') }}</AppButton>
                <AppButton @click="reset">{{ t('audit_log.filter.reset') }}</AppButton>
            </div>
        </form>
        <p role="status" class="text-[12.5px] text-secondary" data-testid="notice-count">{{ plural('notices.count', total) }}</p>
        <ol class="flex flex-col gap-3">
            <li v-for="notice in notices" :key="notice.id" class="rounded-panel border border-hairline bg-white p-4 text-[13px]">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-[max-content_1fr]">
                    <dt class="font-semibold text-secondary">{{ t('notices.column.time') }}</dt>
                    <dd><time>{{ notice.date }} {{ notice.last_time }}</time></dd>
                    <dt class="font-semibold text-secondary">{{ t('notices.column.kind') }}</dt>
                    <dd>{{ t(`notices.kind.${notice.kind}`) }}</dd>
                    <template v-if="notice.canvas">
                        <dt class="font-semibold text-secondary">{{ t('notices.column.canvas') }}</dt>
                        <dd>{{ notice.canvas }}</dd>
                    </template>
                    <template v-if="notice.details.snapshot">
                        <dt class="font-semibold text-secondary">{{ t('notices.column.snapshot') }}</dt>
                        <dd>{{ notice.details.snapshot }}</dd>
                    </template>
                    <template v-if="notice.details.year">
                        <dt class="font-semibold text-secondary">{{ t('notices.column.year') }}</dt>
                        <dd>{{ notice.details.year }}</dd>
                    </template>
                    <template v-if="notice.kind === 'closing_check_failed'">
                        <dt class="font-semibold text-secondary">{{ t('notices.column.filter') }}</dt>
                        <dd>{{ notice.details.filter }}</dd>
                        <dt class="font-semibold text-secondary">{{ t('notices.column.check') }}</dt>
                        <dd>{{ notice.details.check }}</dd>
                        <dt class="font-semibold text-secondary">{{ t('notices.column.difference') }}</dt>
                        <dd>{{ t('notices.cents', { cents: notice.details.differenceCents ?? 0 }) }}</dd>
                    </template>
                    <template v-if="showCount(notice)">
                        <dt class="font-semibold text-secondary">{{ t('notices.column.count') }}</dt>
                        <dd>{{ t('notices.times', { count: notice.count, time: notice.last_time }) }}</dd>
                    </template>
                </dl>
                <p v-if="notice.seen" :id="`notice-seen-${notice.id}`" tabindex="-1" class="mt-3"><Pill tone="success" icon="check">{{ t('notices.seen') }}</Pill></p>
                <AppButton v-else class="mt-3" size="small" :aria-label="t('notices.mark_seen_for', { kind: t(`notices.kind.${notice.kind}`), date: notice.date, time: notice.last_time })" @click="markSeen(notice)">{{ t('notices.mark_seen') }}</AppButton>
            </li>
        </ol>
        <p v-if="!notices.length" class="rounded-panel border border-hairline bg-white p-6 text-[13px] text-muted">{{ t('notices.empty') }}</p>
    </div>
</template>
