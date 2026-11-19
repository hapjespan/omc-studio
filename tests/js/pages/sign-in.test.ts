import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    useForm: () => ({ email: '', password: '', errors: {}, processing: false, post: () => {}, reset: () => {} }),
}));

const SignIn = (await import('../../../resources/js/pages/Auth/SignIn.vue')).default;

const render = (props: Record<string, unknown>) => renderToString(createSSRApp({ render: () => h(SignIn, props) }));

describe('SignIn', () => {
    it('announces the reason a session ended', async () => {
        const html = await render({ flash: { status: 'Je sessie is na 12 uur beëindigd. Log opnieuw in' } });

        expect(html).toContain('role="status"');
        expect(html).toContain('Je sessie is na 12 uur beëindigd. Log opnieuw in');
    });

    it('announces nothing without a message', async () => {
        expect(await render({ flash: { status: null } })).not.toContain('role="status"');
    });
});
