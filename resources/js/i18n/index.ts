import messages from '../../../lang/nl.json';

const catalogue: Record<string, string> = messages;

export function t(key: string, params: Record<string, string | number> = {}): string {
    const message = catalogue[key];
    if (message === undefined) throw new Error(`Missing message key: ${key}`);
    return message.replace(/:(\w+)/g, (match, name: string) => (name in params ? String(params[name]) : match));
}
