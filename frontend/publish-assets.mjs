import { mkdir, readdir, copyFile } from 'node:fs/promises';

// Keep dist for Node tests; publish only browser JavaScript inside the web root.
const source = new URL('./dist/', import.meta.url);
const target = new URL('../public/assets/js/', import.meta.url);
await mkdir(target, { recursive: true });
for (const name of await readdir(source)) {
  if (name.endsWith('.js')) await copyFile(new URL(name, source), new URL(name, target));
}
