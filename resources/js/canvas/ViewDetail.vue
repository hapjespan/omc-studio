<script lang="ts">
import { t } from '../i18n';
import type { LayoutArea, LayoutElement, LayoutStatement } from '../layout/types';
import type { ModalityMark } from './CanvasView.vue';

export interface DetailField {
    label: string;
    value: string;
}

export interface DetailContent {
    title: string;
    kind: string;
    system: boolean;
    modality: (ModalityMark & { name: string }) | null;
    fields: DetailField[];
}

export interface DetailSource {
    areas: LayoutArea[];
    elements: (LayoutElement & { description?: string | null; rationale?: string | null })[];
    statements: LayoutStatement[];
    modalities: ModalityMark[];
}

export const statementText = (statement: LayoutStatement): string => (statement.title ? `${statement.title}${/[.!?:]$/.test(statement.title) ? '' : ':'} ${statement.body}` : statement.body);

export const statementLines = (statements: LayoutStatement[], kind: LayoutStatement['kind']): string[] =>
    statements.filter((statement) => statement.kind === kind).sort((a, b) => a.position - b.position).map((statement, index) => (kind === 'goal' ? `${index + 1}. ${statement.title ? `${statement.title} ` : ''}${statement.body}` : statementText(statement)));

export function detailOf(id: string, source: DetailSource): DetailContent | null {
    const area = source.areas.find((candidate) => candidate.key === id);
    if (area) {
        const fields: DetailField[] = [{ label: t('fields.name'), value: area.label }];
        if (area.key === 'direction') {
            for (const [kind, label] of [['mission', 'overview.mission'], ['vision', 'overview.vision'], ['goal', 'view.direction.goals']] as const) {
                const lines = statementLines(source.statements, kind);
                fields.push({ label: t(label), value: lines.length ? lines.join('\n') : t('view.empty') });
            }
        }
        return { title: area.label, kind: t('view.kind.area'), system: false, modality: null, fields };
    }

    const element = source.elements.find((candidate) => candidate.id === id);
    if (!element) return null;
    const mark = source.modalities.find((candidate) => candidate.letter === element.modality);
    const kind = element.kind === 'cluster' ? t('view.kind.cluster') : t(`areas.${element.area}.level_${element.kind === 'domain' ? 1 : 2}`);
    const modality = mark ? { ...mark, name: t(`view.modality.${mark.key}`) } : null;

    return {
        title: element.name,
        kind,
        system: !!element.unknownAllocation,
        modality,
        fields: [
            { label: t('fields.name'), value: element.name },
            { label: t('fields.description'), value: element.description || t('view.empty') },
            { label: t('view.field.rationale'), value: element.rationale || t('view.empty') },
            { label: t('view.field.modality'), value: modality?.name ?? t('view.empty') },
        ],
    };
}
</script>

<script setup lang="ts">
defineProps<{ content: DetailContent }>();
</script>

<template>
    <Teleport to="#detail-body">
        <div data-view-detail>
            <p class="text-[12.5px] text-secondary">
                {{ content.kind }}
                <span v-if="content.system" class="ml-1 font-semibold">{{ t('view.system') }}</span>
            </p>
            <p v-if="content.modality" class="mt-2 flex items-center gap-2 text-[13px]" data-modality>
                <span class="flex h-4 w-4 items-center justify-center rounded-[3px] text-[11px] font-bold leading-none text-white" :style="{ background: content.modality.colour }" aria-hidden="true">{{ content.modality.letter }}</span>
                <span>{{ content.modality.name }}</span>
            </p>
            <dl class="mt-3 flex flex-col gap-3 text-[13px]">
                <div v-for="field in content.fields" :key="field.label">
                    <dt class="font-semibold text-secondary">{{ field.label }}</dt>
                    <dd class="whitespace-pre-line">{{ field.value }}</dd>
                </div>
            </dl>
        </div>
    </Teleport>
</template>
