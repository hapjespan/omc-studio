import { t } from '../i18n';

export const PNG_SCALE = 2;

const pad = (value: number): string => String(value).padStart(2, '0');

export const pngFileName = (date: Date): string => `canvas-${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}.png`;

let running = false;

export async function exportPng(): Promise<string> {
    if (running) return '';
    running = true;
    try {
        const artboard = document.querySelector<HTMLElement>('[data-artboard]');
        if (!artboard) return t('print.png_failed');
        const { toBlob } = await import('html-to-image');
        const blob = await toBlob(artboard, {
            pixelRatio: PNG_SCALE,
            width: artboard.offsetWidth,
            height: artboard.offsetHeight,
            style: { transform: 'none', position: 'static' },
            backgroundColor: '#ffffff',
        });
        if (!blob) return t('print.png_failed');
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = pngFileName(new Date());
        link.click();
        URL.revokeObjectURL(link.href);

        return t('print.png_done');
    } catch {
        return t('print.png_failed');
    } finally {
        running = false;
    }
}
