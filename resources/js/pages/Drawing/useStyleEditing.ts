import { computed, ref, type ComputedRef, type ShallowRef } from 'vue';
import { applyScope } from '../../layout/styleScope';
import type { DrawnCanvas } from '../../layout';
import type { BoxValues, DrawingEngine } from '../../edit-engine';
import type { CopiedStyle } from '../../edit-engine/commands/pasteStyle';
import type { StylePatch } from '../../edit-engine/commands/style';
import { t } from '../../i18n';
import type { InvalidValue } from './PropertiesPanel.vue';
import type { StyleRefusal, StyleTarget } from './StylePanel.vue';
import type { SaveFailure } from './SaveQueue';
import { styleTargetOf } from './styleTarget';

export interface StyleEditingDeps {
    engine: ShallowRef<DrawingEngine | null>;
    drawn: ShallowRef<DrawnCanvas | null>;
    single: ComputedRef<string | null>;
    names: ComputedRef<Map<string, string>>;
    say: (text: string) => void;
}

export function useStyleEditing({ engine, drawn, single, names, say }: StyleEditingDeps) {
    const box = ref<BoxValues | null>(null);
    const target = ref<StyleTarget | null>(null);
    const copied = ref<CopiedStyle | null>(null);
    const applying = ref(false);
    const invalid = ref<InvalidValue | null>(null);
    const refused = ref<StyleRefusal | null>(null);
    let typed: Omit<InvalidValue, 'message'> | null = null;
    let typedStyle: Omit<StyleRefusal, 'message'> | null = null;
    const scope = computed(() => (single.value && drawn.value && target.value ? applyScope(drawn.value, single.value) : null));

    const refreshBox = (): void => {
        box.value = single.value ? (engine.value?.getBox(single.value) ?? null) : null;
        target.value = styleTargetOf(drawn.value, single.value, engine.value);
    };
    const nameOf = (id: string): string => names.value.get(id) ?? id;
    const copyStyle = (): void => {
        copied.value = single.value ? (engine.value?.copyStyle(single.value) ?? null) : null;
        if (copied.value && single.value) say(t('drawing_style.copied', { name: nameOf(single.value) }));
    };
    const setStyle = (patch: StylePatch, mode: 'patch' | 'paste' | 'apply' = 'patch'): void => {
        applying.value = false;
        refused.value = null;
        typed = null;
        const colourKey = (['text_colour', 'fill', 'border_colour'] as const).find((key) => patch[key] !== undefined);
        typedStyle = mode === 'patch' && single.value && colourKey ? { id: single.value, key: colourKey, value: String(patch[colourKey]) } : null;
        const count = scope.value?.ids.length ?? 0;
        const result = mode === 'paste' && copied.value ? engine.value?.pasteStyle(copied.value) : mode === 'apply' && single.value ? engine.value?.applyStyleToSiblings(single.value) : engine.value?.setStyle(patch);
        refreshBox();
        if (!result || result.ids.length === 0) return;
        const named = (ids: string[]) => ids.map(nameOf).join(', ');
        if (mode === 'apply' && single.value) return say(t('drawing_style.applied', { name: nameOf(single.value), count }) + (result.autoText.length > 0 ? `. ${t('drawing_style.auto_text', { names: named(result.autoText) })}` : ''));
        say(result.autoText.length > 0 ? t('drawing_style.auto_text', { names: named(result.autoText) }) : t('drawing_style.changed', { name: named(result.ids) }));
    };
    const setBox = (id: string, values: Partial<BoxValues>): void => {
        invalid.value = null;
        refused.value = null;
        typedStyle = null;
        engine.value?.apply({ kind: 'setBox', id, box: values });
        const key = Object.keys(values)[0] as InvalidValue['key'];
        typed = { id, key, value: String(values[key]) };
        refreshBox();
    };
    const clearTyped = (): void => {
        typed = null;
    };
    const onSelection = (ids: string[]): void => {
        if (invalid.value && ids.join() !== invalid.value.id) invalid.value = null;
        if (refused.value && ids.join() !== refused.value.id) refused.value = null;
        refreshBox();
    };
    const captureRefusal = (failure: SaveFailure & { kind: 'refused' }): (() => void) => {
        const attempt = typed;
        const styled = typedStyle;

        return () => {
            if (styled && failure.elements.includes(styled.id)) {
                engine.value?.select([styled.id]);
                refused.value = { ...styled, message: failure.messages[0] ?? t('drawing.value_invalid') };
                return;
            }
            if (!attempt || !failure.elements.includes(attempt.id)) return;
            engine.value?.select([attempt.id]);
            invalid.value = { ...attempt, message: failure.messages[0] ?? t('drawing.value_invalid') };
        };
    };

    return { box, target, copied, applying, invalid, refused, scope, refreshBox, copyStyle, setStyle, setBox, clearTyped, onSelection, captureRefusal };
}

export type StyleEditing = ReturnType<typeof useStyleEditing>;
