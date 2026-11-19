const TIMEZONE = 'Europe/Amsterdam';

function group(whole: number): string {
    return String(whole).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function signed(value: number, text: string): string {
    return value < 0 ? `-${text}` : text;
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function local(instant: string): Record<string, string> {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date(instant));
    return Object.fromEntries(parts.map((part) => [part.type, part.value]));
}

export function formatAmount(cents: number): string {
    const abs = Math.abs(cents);
    return signed(cents, `€ ${group(Math.floor(abs / 100))},${pad(abs % 100)}`);
}

export function formatWholeEuros(cents: number): string {
    return signed(cents, `€ ${group(Math.floor((Math.abs(cents) + 50) / 100))}`);
}

export function formatShare(part: number, total: number): string {
    const basisPoints = basisPointsOf(part, total);
    if (basisPoints === null) return '-';
    const abs = Math.abs(basisPoints);
    const text = `${Math.floor(abs / 100)},${pad(abs % 100)}`;
    return signed(basisPoints, `${text.replace(/0+$/, '').replace(/,$/, '')}%`);
}

export function formatFte(hundredths: number): string {
    const abs = Math.abs(hundredths);
    return signed(hundredths, `${Math.floor(abs / 100)},${pad(abs % 100)}`);
}

export function formatDate(instant: string): string {
    const { day, month, year } = local(instant);
    return `${day}-${month}-${year}`;
}

export function formatTime(instant: string): string {
    const { hour, minute } = local(instant);
    return `${hour}:${minute}`;
}

export function formatBasisPoints(basisPoints: number): string {
    const tenths = Math.floor((Math.abs(basisPoints) + 5) / 10);
    return signed(basisPoints, `${Math.floor(tenths / 10)},${tenths % 10}%`);
}

export function basisPointsOf(part: number, whole: number): number | null {
    if (whole === 0) return null;
    const scaled = part * 10000;
    const remainder = scaled % whole;
    return (scaled - remainder) / whole + (2 * remainder >= whole ? 1 : 0);
}
