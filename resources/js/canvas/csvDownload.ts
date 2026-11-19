export async function downloadCsv(url: string, fallbackName: string): Promise<number | null> {
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) return null;
    const name = /filename="?([^";]+)"?/.exec(response.headers.get('Content-Disposition') ?? '')?.[1] ?? fallbackName;
    const link = document.createElement('a');
    link.href = URL.createObjectURL(await response.blob());
    link.download = name;
    link.click();
    URL.revokeObjectURL(link.href);

    return Number(response.headers.get('X-Omitted-Cost-Records') ?? 0);
}
