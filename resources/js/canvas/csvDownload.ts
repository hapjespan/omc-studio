import { t } from '../i18n';

let running = false;

interface OmittedReport {
    header: string;
    none: string;
    some: string;
}

const COST_RECORDS: OmittedReport = { header: 'X-Omitted-Cost-Records', none: 'csvx.omitted_none', some: 'csvx.omitted' };
const SNAPSHOTS: OmittedReport = { header: 'X-Omitted-Snapshots', none: 'fullx.omitted_none', some: 'fullx.omitted' };

export const exportCsv = (url: string, fallbackName: string): Promise<string> => download(url, fallbackName, COST_RECORDS);

export const exportFull = (url: string, fallbackName: string): Promise<string> => download(url, fallbackName, SNAPSHOTS);

async function download(url: string, fallbackName: string, report: OmittedReport): Promise<string> {
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
        const omitted = Number(response.headers.get(report.header) ?? 0);

        return omitted === 0 ? t(report.none) : t(report.some, { count: omitted });
    } catch {
        return t('csvx.export_failed');
    } finally {
        running = false;
    }
}
