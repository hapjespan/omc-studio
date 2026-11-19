<script setup lang="ts">
import AppButton from '../../components/ui/AppButton.vue';
import { nextTick, reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ErrorSummary from '../../components/fields/ErrorSummary.vue';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import Field from '../../components/fields/Field.vue';
import TextField from '../../components/fields/TextField.vue';
import { hasMessage, t } from '../../i18n';

interface Change { field: string; old: string | null; new: string | null }
interface Entry {
    id: number;
    occurred_at: string;
    actor: { kind: 'user' | 'system' | 'deleted'; name: string | null };
    action: string;
    subject_type: string;
    subject_label: string;
    canvas: string | null;
    changes: Change[];
}
interface Option { id: number; name: string }
type Filters = Partial<Record<'organisation_id' | 'canvas_id' | 'subject_type' | 'actor_id' | 'from' | 'to', string | number>>;

const props = defineProps<{
    entries: Entry[];
    total: number;
    page: number;
    lastPage: number;
    filters: Filters;
    filterErrors: Record<string, string>;
    options: { organisations: Option[]; canvases: Option[]; users: Option[]; kinds: string[] };
}>();

const form = reactive({
    organisation_id: String(props.filters.organisation_id ?? ''),
    canvas_id: String(props.filters.canvas_id ?? ''),
    subject_type: String(props.filters.subject_type ?? ''),
    actor_id: String(props.filters.actor_id ?? ''),
    from: String(props.filters.from ?? ''),
    to: String(props.filters.to ?? ''),
});

const selects = [
    { id: 'organisation_id', label: 'audit_log.filter.organisation', options: () => props.options.organisations.map((o) => ({ value: String(o.id), label: o.name })) },
    { id: 'canvas_id', label: 'audit_log.filter.canvas', options: () => props.options.canvases.map((o) => ({ value: String(o.id), label: o.name })) },
    { id: 'subject_type', label: 'audit_log.filter.kind', options: () => props.options.kinds.map((kind) => ({ value: kind, label: t(`audit_log.kind.${kind}`) })) },
    { id: 'actor_id', label: 'audit_log.filter.user', options: () => props.options.users.map((o) => ({ value: String(o.id), label: o.name })) },
] as const;

watch(() => props.filterErrors, (errors) => Object.keys(errors).length && nextTick(focusFirstInvalid), { immediate: true });

function query(source: Filters, page?: number): Record<string, string | number> {
    const filled = Object.fromEntries(Object.entries(source).filter(([, value]) => value !== '' && value !== undefined)) as Record<string, string | number>;

    return page && page > 1 ? { ...filled, page } : filled;
}

function pageHref(page: number): string {
    return `/admin/audit-log?${new URLSearchParams(Object.entries(query(props.filters, page)).map(([key, value]) => [key, String(value)]))}`;
}

function apply(): void {
    router.get('/admin/audit-log', query(form));
}

function reset(): void {
    Object.keys(form).forEach((key) => (form[key as keyof typeof form] = ''));
    apply();
}

const label = (prefix: string, key: string): string => (hasMessage(`${prefix}.${key}`) ? t(`${prefix}.${key}`) : key);
const actor = (entry: Entry): string => (entry.actor.kind === 'user' ? (entry.actor.name ?? '') : t(`audit_log.actor.${entry.actor.kind}`));
const value = (text: string | null): string => (text === null || text === '' ? t('audit_log.empty_value') : text);
</script>

<template>
    <Head :title="t('audit_log.title')" />
    <h1 class="text-[20px] font-semibold">{{ t('audit_log.title') }}</h1>
    <ErrorSummary v-if="Object.keys(filterErrors).length" :errors="filterErrors" />
    <form class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" novalidate @submit.prevent="apply">
        <Field v-for="select in selects" :id="select.id" :key="select.id" :label="t(select.label)" :error="filterErrors[select.id]" v-slot="{ describedby, invalid }">
            <select :id="select.id" v-model="form[select.id]" :aria-describedby="describedby" :aria-invalid="invalid || undefined" class="rounded-md border border-edge bg-white px-2 py-1.5 text-[13px] text-ink">
                <option value="">{{ t('audit_log.filter.all') }}</option>
                <option v-for="option in select.options()" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
        </Field>
        <TextField id="from" v-model="form.from" :label="t('audit_log.filter.from')" type="date" :error="filterErrors.from" />
        <TextField id="to" v-model="form.to" :label="t('audit_log.filter.to')" type="date" :error="filterErrors.to" />
        <div class="flex items-end gap-2">
            <AppButton variant="primary" type="submit">{{ t('audit_log.filter.apply') }}</AppButton>
            <button type="button" class="min-h-6 rounded-md border border-edge px-3 py-2 text-[13px]" @click="reset">{{ t('audit_log.filter.reset') }}</button>
        </div>
    </form>
    <p role="status" class="mt-4 text-[13px]" data-testid="audit-count">{{ t('audit_log.count', { count: total }) }}</p>
    <ol class="mt-2 flex flex-col gap-3">
        <li v-for="entry in entries" :key="entry.id" class="rounded-md border border-edge p-3 text-[13px]">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-[max-content_1fr]">
                <dt class="font-semibold">{{ t('audit_log.column.time') }}</dt>
                <dd><time>{{ entry.occurred_at }}</time></dd>
                <dt class="font-semibold">{{ t('audit_log.column.user') }}</dt>
                <dd>{{ actor(entry) }}</dd>
                <dt class="font-semibold">{{ t('audit_log.column.action') }}</dt>
                <dd>{{ label('audit_log.action', entry.action) }}</dd>
                <dt class="font-semibold">{{ t('audit_log.column.kind') }}</dt>
                <dd>{{ label('audit_log.kind', entry.subject_type) }}: {{ entry.subject_label }}</dd>
                <dt class="font-semibold">{{ t('audit_log.column.canvas') }}</dt>
                <dd>{{ entry.canvas ?? t('audit_log.empty_value') }}</dd>
            </dl>
            <ul v-if="entry.changes.length" class="mt-2 flex flex-col gap-1 border-t border-edge pt-2">
                <li v-for="change in entry.changes" :key="change.field">
                    <span class="font-semibold">{{ label('audit_log.field', change.field) }}</span>:
                    {{ t('audit_log.old') }} <q class="break-words">{{ value(change.old) }}</q>, {{ t('audit_log.new') }} <q class="break-words">{{ value(change.new) }}</q>
                </li>
            </ul>
        </li>
    </ol>
    <p v-if="!entries.length" class="mt-2 text-[13px]">{{ t('audit_log.empty') }}</p>
    <nav v-if="lastPage > 1" :aria-label="t('audit_log.pagination')" class="mt-4 flex items-center gap-4 text-[13px]">
        <Link v-if="page > 1" :href="pageHref(page - 1)" class="underline">{{ t('audit_log.previous') }}</Link>
        <span>{{ t('audit_log.page', { page, last: lastPage }) }}</span>
        <Link v-if="page < lastPage" :href="pageHref(page + 1)" class="underline">{{ t('audit_log.next') }}</Link>
    </nav>
</template>
