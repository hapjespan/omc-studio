export class SearchEcho {
    private pending = false;
    private emitted = '';

    typed(): void {
        this.pending = true;
    }

    sent(value: string): void {
        this.emitted = value.trim();
    }

    adopts(echo: string): boolean {
        if (!this.pending) return true;
        if (echo === this.emitted) this.pending = false;
        return false;
    }
}
