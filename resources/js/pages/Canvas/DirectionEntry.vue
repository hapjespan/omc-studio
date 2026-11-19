<script setup lang="ts">
import { nextTick, reactive, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { announce } from '../../components/feedback/status';
import TextArea from '../../components/fields/TextArea.vue';
import TextField from '../../components/fields/TextField.vue';
import UndoMessage from '../../components/list/UndoMessage.vue';
import { t } from '../../i18n';
import { routes } from '../../routes';

interface Goal {
    id: number;
    title: string;
    body: string;
}

const LIMIT = 2000;
const kinds = ['mission', 'vision'] as const;

const props = defineProps<{ canvasId: number; statements: { mission: string | null; vision: string | null; goals: Goal[] } }>();
const texts = reactive({ mission: props.statements.mission ?? '', vision: props.statements.vision ?? '' });
const errors = reactive<Record<string, string>>({ mission: '', vision: '' });
const goalForm = useForm({ title: '', body: '' });
const undo = ref<{ token: string; name: string; refusal: string | null } | null>(null);
const options = { preserveScroll: true, preserveState: true };

kinds.forEach((kind) => {
    watch(() => texts[kind], (text, before) => {
        if (text.length >= LIMIT && before.length < LIMIT) void announce(t('direction_entry.counter_limit', { label: t(`direction_entry.${kind}`) }));
    });
    watch(() => props.statements[kind], (value) => (texts[kind] = value ?? ''));
});

function saveText(kind: (typeof kinds)[number]): void {
    errors[kind] = '';
    router.put(routes.statement(props.canvasId, kind), { body: texts[kind] }, {
        ...options,
        onSuccess: () => void announce(t('direction_entry.saved', { label: t(`direction_entry.${kind}`) })),
        onError: (found) => (errors[kind] = found.body ?? found.rule ?? ''),
    });
}

function addGoal(): void {
    goalForm.post(routes.goals(props.canvasId), {
        ...options,
        onSuccess: () => {
            goalForm.reset();
            void announce(t('direction_entry.saved', { label: t('direction_entry.goals') }));
        },
    });
}

function move(goal: Goal, direction: 'up' | 'down'): void {
    router.post(routes.goalReorder(props.canvasId, goal.id), { direction }, {
        ...options,
        onSuccess: async () => {
            await nextTick();
            const same = document.getElementById(`goal-${direction}-${goal.id}`) as HTMLButtonElement | null;
            (same?.disabled ? document.getElementById(`goal-${direction === 'up' ? 'down' : 'up'}-${goal.id}`) : same)?.focus();
            void announce(t('direction_entry.moved', { name: goal.title }));
        },
    });
}

function remove(goal: Goal): void {
    router.delete(routes.goal(props.canvasId, goal.id), {
        ...options,
        onSuccess: () => {
            const token = (usePage().props.flash as { undoToken?: string | null } | undefined)?.undoToken;
            if (token) undo.value = { token, name: goal.title, refusal: null };
            void announce(t('direction_entry.deleted', { name: goal.title }));
        },
    });
}

function undoDeletion(): void {
    const current = undo.value;
    if (!current) return;
    router.post(routes.undo(current.token), {}, {
        ...options,
        onSuccess: () => {
            undo.value = null;
            void announce(t('direction_entry.undone', { name: current.name }));
        },
        onError: (found) => (current.refusal = found.rule ?? ''),
    });
}

const buttonClass = 'rounded-md border border-[#848B95] px-2 py-1.5 text-[12.5px] font-semibold disabled:opacity-60';
</script>

<template>
    <div class="mt-3 flex flex-col gap-4">
        <UndoMessage v-if="undo" :message="t('direction_entry.deleted', { name: undo.name })" :refusal="undo.refusal" @undo="undoDeletion" @close="undo = null" />
        <form v-for="kind in kinds" :key="kind" novalidate class="flex flex-col gap-2" @submit.prevent="saveText(kind)">
            <TextArea :id="`direction-${kind}`" v-model="texts[kind]" :label="t(`direction_entry.${kind}`)" :error="errors[kind] || undefined" :rows="4" />
            <p v-if="!texts[kind]" class="text-[12.5px] text-[#454C57]">{{ t(`direction_entry.empty_${kind}`) }}</p>
            <button type="submit" :class="[buttonClass, 'self-start']">{{ t('direction_entry.save') }}</button>
        </form>
        <section :aria-labelledby="'direction-goals'" class="flex flex-col gap-2">
            <h3 id="direction-goals" class="text-[13px] font-semibold">{{ t('direction_entry.goals') }}</h3>
            <p v-if="!statements.goals.length" class="text-[12.5px] text-[#454C57]">{{ t('direction_entry.empty_goals') }}</p>
            <ol v-else class="flex flex-col gap-2">
                <li v-for="(goal, index) in statements.goals" :key="goal.id" class="flex flex-wrap items-start gap-2 rounded-md border border-[#848B95] p-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-semibold">{{ goal.title }}</p>
                        <p class="text-[12.5px] text-[#454C57]">{{ goal.body }}</p>
                    </div>
                    <button :id="`goal-up-${goal.id}`" type="button" :disabled="index === 0" :aria-label="t('direction_entry.up_named', { name: goal.title })" :class="buttonClass" @click="move(goal, 'up')">{{ t('direction_entry.up') }}</button>
                    <button :id="`goal-down-${goal.id}`" type="button" :disabled="index === statements.goals.length - 1" :aria-label="t('direction_entry.down_named', { name: goal.title })" :class="buttonClass" @click="move(goal, 'down')">{{ t('direction_entry.down') }}</button>
                    <button type="button" :aria-label="t('direction_entry.delete_named', { name: goal.title })" :class="buttonClass" @click="remove(goal)">{{ t('direction_entry.delete') }}</button>
                </li>
            </ol>
            <form novalidate class="flex flex-col gap-2" @submit.prevent="addGoal">
                <TextField id="goal-title" v-model="goalForm.title" :label="t('direction_entry.add_goal')" :error="goalForm.errors.title" autocomplete="off" />
                <TextArea id="goal-body" v-model="goalForm.body" :label="t('direction_entry.goal_body')" :error="goalForm.errors.body" :rows="3" />
                <button type="submit" :class="[buttonClass, 'self-start']">{{ t('direction_entry.add') }}</button>
            </form>
        </section>
    </div>
</template>
