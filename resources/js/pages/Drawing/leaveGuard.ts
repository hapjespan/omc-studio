import { ref, type Ref } from 'vue';
import { leaveGuards } from '../../shell/fullLoad';
import type { SaveStatus } from './SaveQueue';

export function createLeaveGuard(status: Ref<SaveStatus>, retry: () => void) {
    const target = ref<string | null>(null);
    const retried = ref(false);
    let waiting: string | null = null;
    const guard = (destination: string): boolean => {
        if (status.value === 'saving') {
            waiting = destination;

            return true;
        }
        if (status.value !== 'failed') return false;
        target.value = destination;

        return true;
    };
    const beforeUnload = (event: BeforeUnloadEvent): void => {
        if (status.value !== 'failed' && status.value !== 'saving') return;
        event.preventDefault();
        event.returnValue = '';
    };
    const install = (): void => {
        leaveGuards.add(guard);
        window.addEventListener('beforeunload', beforeUnload);
    };
    const uninstall = (): void => {
        leaveGuards.delete(guard);
        window.removeEventListener('beforeunload', beforeUnload);
    };

    return {
        target,
        retried,
        install,
        uninstall,
        destination: (): string | null => target.value ?? waiting,
        retry: (): void => {
            retried.value = true;
            retry();
        },
        discard: (): void => {
            const destination = target.value;
            uninstall();
            if (destination) window.location.assign(destination);
        },
    };
}
