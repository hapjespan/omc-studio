<script setup lang="ts">
import limits from '../../../data/field-limits.json';
import { nextTick, reactive, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import { focusFirstInvalid } from '../../components/fields/focusFirstInvalid';
import ConfirmDelete from '../../components/list/ConfirmDelete.vue';
import TextArea from '../../components/fields/TextArea.vue';
import TextField from '../../components/fields/TextField.vue';
import AppButton from '../../components/ui/AppButton.vue';
import IconButton from '../../components/ui/IconButton.vue';
import UndoMessage from '../../components/list/UndoMessage.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

interface Goal {
    id: number;
    title: string;
    body: string;
}

const LIMIT = limits.text;
const TITLE_LIMIT = limits.name;
const kinds = ['mission', 'vision'] as const;

const props = defineProps<{ canvasId: number; statements: { mission: string | null; vision: string | null; goals: Goal[] } }>();
const texts = reactive({ mission: props.statements.mission ?? '', vision: props.statements.vision ?? '' });
const errors = reactive<Record<string, string>>({ mission: '', vision: '' });
const goalForm = useForm({ title: '', body: '' });
const undo = ref<{ token: string; name: string; refusal: string | null } | null>(null);
const deleting = ref<Goal | null>(null);
const confirming = ref(false);
const options = { preserveScroll: true, preserveState: true };

function watchLimit(source: () => string, limit: number, label: string): void {
    watch(source, (text, before) => {
        if (text.length >= limit && before.length < limit) void announce(t('direction_entry.counter_limit', { label, limit: String(limit) }));
    });
}

kinds.forEach((kind) => {
    watchLimit(() => texts[kind], LIMIT, t(`direction_entry.${kind}`));
    watch(() => props.statements[kind], (value) => (texts[kind] = value ?? ''));
});
watchLimit(() => goalForm.title, TITLE_LIMIT, t('direction_entry.goal_title'));
watchLimit(() => goalForm.body, LIMIT, t('direction_entry.goal_body'));

function saveText(kind: (typeof kinds)[number]): void {
    errors[kind] = '';
    router.put(routes.statement(props.canvasId, kind), { body: texts[kind] }, {
        ...options,
        onSuccess: () => void announce(t('direction_entry.saved', { label: t(`direction_entry.${kind}`) })),
        onError: (found) => {
            errors[kind] = found.body ?? found.rule ?? '';
            void nextTick(() => document.getElementById(`direction-${kind}`)?.focus());
        },
    });
}

function addGoal(): void {
    goalForm.post(routes.goals(props.canvasId), {
        ...options,
        onSuccess: () => {
            goalForm.reset();
            void announce(t('direction_entry.saved', { label: t('direction_entry.goals') }));
        },
        onError: () => nextTick(focusFirstInvalid),
    });
}

function move(goal: Goal, direction: 'up' | 'down'): void {
    refusal.value = '';
    router.post(routes.goalReorder(props.canvasId, goal.id), { direction }, {
        ...options,
        onError: (found) => (refusal.value = found.rule ?? ''),
        onSuccess: async () => {
            await nextTick();
            const same = document.getElementById(`goal-${direction}-${goal.id}`) as HTMLButtonElement | null;
            (same?.disabled ? document.getElementById(`goal-${direction === 'up' ? 'down' : 'up'}-${goal.id}`) : same)?.focus();
            void announce(t('direction_entry.moved', { name: goal.title }));
        },
    });
}

function remove(goal: Goal): void {
    refusal.value = '';
    router.delete(routes.goal(props.canvasId, goal.id), {
        ...options,
        onError: (found) => (refusal.value = found.rule ?? ''),
        onSuccess: () => {
            const token = (usePage().props.flash as { undoToken?: string | null } | undefined)?.undoToken;
            if (token) undo.value = { token, name: goal.title, refusal: null };
            void announce(t('direction_entry.deleted', { name: goal.title }));
        },
    });
}

const refusal = ref('');
const afterUndo = async (): Promise<void> => {
    await nextTick();
    document.getElementById('goal-title')?.focus();
};

function closeUndo(): void {
    const refused = Boolean(undo.value?.refusal);
    undo.value = null;
    if (refused) void afterUndo();
}

function undoDeletion(): void {
    const current = undo.value;
    if (!current) return;
    router.post(routes.undo(current.token), {}, {
        ...options,
        onSuccess: () => {
            undo.value = null;
            void afterUndo();
            void announce(t('direction_entry.undone', { name: current.name }));
        },
        onError: (found) => (current.refusal = found.rule ?? ''),
    });
}
</script>

<template>
    <div class="mt-3 flex flex-col gap-4">
        <UndoMessage v-if="undo" :message="t('direction_entry.deleted', { name: undo.name })" :refusal="undo.refusal" @undo="undoDeletion" @close="closeUndo" />
        <p v-if="refusal" role="alert" class="text-[12.5px] font-medium text-danger">{{ refusal }}</p>
        <form v-for="kind in kinds" :key="kind" novalidate class="flex flex-col gap-2" @submit.prevent="saveText(kind)">
            <TextArea :id="`direction-${kind}`" v-model="texts[kind]" :label="t(`direction_entry.${kind}`)" :error="errors[kind] || undefined" :rows="4" />
            <p class="text-[12.5px] text-secondary" aria-hidden="true">{{ texts[kind].length }} / {{ LIMIT }}</p>
            <p v-if="!statements[kind]" class="text-[12.5px] text-secondary">{{ t(`direction_entry.empty_${kind}`) }}</p>
            <AppButton type="submit" variant="primary" class="self-start">{{ t('direction_entry.save') }}</AppButton>
        </form>
        <section :aria-label="t('direction_entry.goals')" class="flex flex-col gap-2">
            <h3 class="text-[13px] font-semibold">{{ t('direction_entry.goals') }}</h3>
            <p v-if="!statements.goals.length" class="text-[12.5px] text-secondary">{{ t('direction_entry.empty_goals') }}</p>
            <ol v-else class="flex flex-col gap-2">
                <li v-for="(goal, index) in statements.goals" :key="goal.id" class="flex flex-wrap items-start gap-2 rounded-control border border-hairline bg-white p-2.5">
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-semibold">{{ goal.title }}</p>
                        <p class="text-[12.5px] text-secondary">{{ goal.body }}</p>
                    </div>
                    <IconButton :id="`goal-up-${goal.id}`" icon="chevron-up" :disabled="index === 0" :label="t('direction_entry.up_named', { name: goal.title })" @click="move(goal, 'up')" />
                    <IconButton :id="`goal-down-${goal.id}`" icon="chevron-down" :disabled="index === statements.goals.length - 1" :label="t('direction_entry.down_named', { name: goal.title })" @click="move(goal, 'down')" />
                    <AppButton variant="danger" size="small" :aria-label="t('direction_entry.delete_named', { name: goal.title })" @click="deleting = goal; confirming = true">{{ t('direction_entry.delete') }}</AppButton>
                </li>
            </ol>
            <form novalidate class="flex flex-col gap-2" @submit.prevent="addGoal">
                <TextField id="goal-title" v-model="goalForm.title" :label="t('direction_entry.goal_title')" :error="goalForm.errors.title" autocomplete="off" />
                <p class="text-[12.5px] text-secondary" aria-hidden="true">{{ goalForm.title.length }} / {{ TITLE_LIMIT }}</p>
                <TextArea id="goal-body" v-model="goalForm.body" :label="t('direction_entry.goal_body')" :error="goalForm.errors.body" :rows="3" />
                <p class="text-[12.5px] text-secondary" aria-hidden="true">{{ goalForm.body.length }} / {{ LIMIT }}</p>
                <AppButton type="submit" class="self-start">{{ t('direction_entry.add_goal') }}</AppButton>
            </form>
        </section>
        <ConfirmDelete v-model:open="confirming" :name="deleting?.title ?? ''" :consequence="null" @confirm="deleting && remove(deleting)" />
    </div>
</template>
