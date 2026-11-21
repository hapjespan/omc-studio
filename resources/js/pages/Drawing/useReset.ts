import { computed, ref, type ComputedRef, type Ref, type ShallowRef } from 'vue';
import type { DrawingEngine, LayoutPayload } from '../../edit-engine';
import { resetIds, type ResetScope } from '../../edit-engine/commands/reset';
import { t } from '../../i18n';
import { sendReset } from './resetRequest';
import { MoveConflict, sendLayout, type SaveQueue } from './SaveQueue';
import type { DrawingProps } from './drawingProps';

export interface ResetDeps {
    props: DrawingProps;
    engine: ShallowRef<DrawingEngine | null>;
    host: Ref<HTMLElement | undefined>;
    single: ComputedRef<string | null>;
    names: ComputedRef<Map<string, string>>;
    queue: () => SaveQueue | null;
    say: (text: string) => void;
    reload: () => Promise<void>;
}

export function useReset({ props, engine, host, single, names, queue, say, reload }: ResetDeps) {
    const scope = ref<ResetScope | null>(null);
    const id = ref<string | null>(null);
    const opener = ref<HTMLElement | null>(null);
    const resetting = ref(false);
    const isArea = (key: string | null): boolean => key !== null && props.layout.areas.some((area) => area.key === key);
    const name = computed(() => (id.value ? (names.value.get(id.value) ?? '') : ''));
    const count = computed(() => (scope.value ? (isArea(id.value) ? props.elements.filter((element) => element.area === id.value).length : resetIds(props.elements, scope.value, id.value).length) : 0));
    const versions = () => ({ structure: props.versions.structure, layout: props.versions.layout });

    const ask = (next: ResetScope): void => {
        opener.value = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        scope.value = next;
        id.value = next === 'canvas' ? null : single.value;
    };
    const perform = (): void => {
        const chosen = scope.value;
        const key = id.value;
        const returnTo = opener.value;
        scope.value = null;
        if (!chosen) return;
        const area = isArea(key);
        resetting.value = true;
        queue()?.enqueueMove(async (current) => {
            try {
                const layout = engine.value?.getLayout() as LayoutPayload;
                const response = area
                    ? await sendLayout(props.canvas.id, { ...layout, areas: Object.fromEntries(Object.entries(layout.areas).filter(([entry]) => entry !== key)), structure_version: current.structure, layout_version: current.layout })
                    : await sendReset(props.canvas.id, chosen, key);
                if (response.outcome === 'conflict') throw new MoveConflict(response.conflict);
                if (response.outcome === 'refused') {
                    say(response.messages.join(' ') || t('drawing.save_failed'));
                    await reload();

                    return versions();
                }
                if (response.outcome !== 'saved') throw new Error('unreachable');
                await reload();
                say(t('drawing.reset_done'));
                (returnTo?.isConnected ? returnTo : host.value)?.focus();

                return versions();
            } finally {
                resetting.value = false;
            }
        });
    };

    return { scope, name, count, resetting, ask, perform };
}
