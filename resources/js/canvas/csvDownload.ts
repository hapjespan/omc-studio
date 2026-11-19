import { t } from '../i18n';

let running = false;

export async function exportCsv(url: string, fallbackName: string): Promise<string> {
    if (running) return '';
    running = true;
    try {
        const response = await fetch(url, { credentials: 'same-origin' });
        if (!response.ok) return t('csvx.export_failed');
        const name = /filename="?([^";]+)"?/.exec(response.headers.get('Content-Disposition') ?? '')?.[1] ?? fallbackName;
        const link = document.createElement('a');
        link.href = URL.createObjectURL(await response.blob());
        link.download = name;
        link.click();
        URL.revokeObjectURL(link.href);
        const omitted = Number(response.headers.get('X-Omitted-Cost-Records') ?? 0);

        return omitted === 0 ? t('csvx.omitted_none') : t('csvx.omitted', { count: omitted });
    } catch {
        return t('csvx.export_failed');
    } finally {
        running = false;
    }
}
