import { xsrfToken } from '../xsrf';
export async function postFile(url: string, fields: Record<string, string | Blob>): Promise<Response> {
    const token = xsrfToken();
    const body = new FormData();
    Object.entries(fields).forEach(([name, value]) => body.append(name, value));

    return fetch(url, { method: 'POST', body, credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': token, Accept: 'application/json' } });
}

export async function refusalOf(response: Response): Promise<string> {
    const data = await response.json();

    return (Object.values(data.errors ?? {}).flat() as string[]).join(' ') || data.message || '';
}
