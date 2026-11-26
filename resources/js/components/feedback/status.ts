import { nextTick, ref } from 'vue';

export const message = ref('');

export async function announce(text: string): Promise<void> {
    message.value = '';
    await nextTick();
    message.value = text;
}
