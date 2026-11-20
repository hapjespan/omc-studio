export const HARNESS_PORT = Number(process.env.E2E_HARNESS_PORT ?? 4187);

export const HARNESS_URL = `http://127.0.0.1:${HARNESS_PORT}`;
