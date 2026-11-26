import { spawn, spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';

const DISPLAY = ':99';
const SOCKET = '/tmp/.X11-unix/X99';

export function ensureDisplay(): void {
    if (process.env.DISPLAY || process.platform !== 'linux' || spawnSync('which', ['Xvfb']).status !== 0) return;
    if (!existsSync(SOCKET)) spawn('Xvfb', [DISPLAY, '-screen', '0', '1920x1080x24'], { detached: true, stdio: 'ignore' }).unref();
    process.env.DISPLAY = DISPLAY;
}
