import { readFile, writeFile, cp, mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const api = process.env.VAYTOVEN_API_URL || '';
if (api) {
  const url = new URL(api);
  const local = ['localhost', '127.0.0.1', '10.0.2.2'].includes(url.hostname);
  if (url.protocol !== 'https:' && !(local && process.env.VAYTOVEN_ALLOW_LOCAL_HTTP === '1')) throw new Error('Native API URL must use HTTPS. For a local emulator only, set VAYTOVEN_ALLOW_LOCAL_HTTP=1.');
  if (url.username || url.password || url.search || url.hash || url.pathname !== '/') throw new Error('Use the server origin only, without credentials, paths or query parameters.');
}
await writeFile(path.join(root, 'www/config.js'), `window.VAYTOVEN_CONFIG = ${JSON.stringify({ apiUrl: api.replace(/\/$/, '') })};\n`);
await cp(path.join(root, '../public/favicon.svg'), path.join(root, 'www/brand.svg'));
await cp(path.join(root, '../public/icon-512.png'), path.join(root, 'www/icon-512.png'));
await cp(path.join(root, 'node_modules/@capacitor/core/dist/capacitor.js'), path.join(root, 'www/capacitor.js'));
const preview = path.join(root, '../public/app');
await mkdir(preview, { recursive: true });
await cp(path.join(root, 'www'), preview, { recursive: true });
// Browser preview always talks to its own Laravel instance.
await writeFile(path.join(preview, 'config.js'), 'window.VAYTOVEN_CONFIG = { apiUrl: "" };\n');
process.stdout.write(`Built mobile assets and /app/ preview. Native backend: ${api || 'not configured; set VAYTOVEN_API_URL before device builds'}.\n`);
