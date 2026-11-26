import { ref } from 'vue';

const WARNING_SECONDS = 10 * 60;

const CHECK_MS = 60_000;

export const reauth = ref(false);

let timer: ReturnType<typeof setInterval> | undefined;
let deadline: number | null = null;

export function inReauthWindow(): boolean {
    return deadline !== null && deadline - Date.now() / 1000 <= WARNING_SECONDS;
}

function check(): void {
    if (inReauthWindow()) reauth.value = true;
}

export function scheduleReauth(endsAt: number | null | undefined): void {
    clearInterval(timer);
    reauth.value = false;
    deadline = endsAt ?? null;
    if (deadline === null) return;
    check();
    timer = setInterval(check, CHECK_MS);
}
