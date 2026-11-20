const MAX_CENTS = 99_999_999_999;
const AMOUNT = /^(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d{1,2}))?$/;
const PERCENTAGE = /^(\d+)(?:,(\d{1,2}))?$/;

export function parseAmount(text: string): number | null {
    const match = AMOUNT.exec(text.trim());
    if (match === null) {
        return null;
    }
    const whole = Number(text.trim().split(',')[0].replaceAll('.', ''));
    const cents = whole * 100 + Number((match[1] ?? '').padEnd(2, '0'));
    return cents <= MAX_CENTS ? cents : null;
}

export function parsePercentage(text: string): number | null {
    const match = PERCENTAGE.exec(text.trim());
    if (match === null) {
        return null;
    }
    const basisPoints = Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'));
    return basisPoints <= 10_000 ? basisPoints : null;
}
