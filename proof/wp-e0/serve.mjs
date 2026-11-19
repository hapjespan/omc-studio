import { createServer } from 'node:http';
import { readFileSync, existsSync, statSync } from 'node:fs';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('./dist-page/', import.meta.url));
const appPolicy = readFileSync(fileURLToPath(new URL('../../app/Http/Middleware/SecurityHeaders.php', import.meta.url)), 'utf8').match(/POLICY = "([^"]+)"/)[1];
const policy = appPolicy.replace("script-src 'self'", "script-src 'self' 'wasm-unsafe-eval'");
const types = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript', '.wasm': 'application/wasm', '.ttf': 'font/ttf', '.css': 'text/css', '.json': 'application/json' };

function serve(port, host = '127.0.0.1') {
    const server = createServer((request, response) => {
        const path = normalize(decodeURIComponent(new URL(request.url, 'http://x').pathname)).replace(/^(\.\.[/\\])+/, '');
        let file = join(root, path === '/' ? 'page.html' : path);
        if (!file.startsWith(root) || !existsSync(file) || statSync(file).isDirectory()) {
            response.writeHead(404).end();
            return;
        }
        response.writeHead(200, {
            'Content-Type': types[extname(file)] ?? 'application/octet-stream',
            'Content-Security-Policy': policy,
            'X-Content-Type-Options': 'nosniff',
            'Referrer-Policy': 'no-referrer',
        });
        response.end(readFileSync(file));
    });
    return new Promise((resolve) => server.listen(port, host, () => resolve(server)));
}

await serve(Number(4173));
