import { ref, type Ref } from 'vue';
import { leaveGuards, proceed, type LeaveVisit } from '../../shell/fullLoad';
import type { SaveStatus } from './SaveQueue';

export function createLeaveGuard(status: Ref<SaveStatus>, retry: () => void) {
    const target = ref<LeaveVisit | null>(null);
    const retried = ref(false);
    let waiting: LeaveVisit | null = null;
    const guard = (destination: LeaveVisit): boolean => {
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
        destination: (): LeaveVisit | null => {
            const next = target.value ?? waiting;
            waiting = null;

            return next;
        },
        retry: (): void => {
            retried.value = true;
            retry();
        },
        discard: (): void => {
            const destination = target.value;
            uninstall();
            if (destination) proceed(destination);
        },
    };
}
