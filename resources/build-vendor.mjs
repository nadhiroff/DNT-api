// Copie les dépendances front (polices, Alpine.js CSP) dans public/assets : aucun CDN en production.
import { cpSync, mkdirSync } from 'node:fs';

const copies = [
  ['node_modules/@fontsource-variable/ibm-plex-sans/files/ibm-plex-sans-latin-wght-normal.woff2', 'public/assets/fonts/ibm-plex-sans-latin.woff2'],
  ['node_modules/@fontsource-variable/ibm-plex-sans/files/ibm-plex-sans-latin-ext-wght-normal.woff2', 'public/assets/fonts/ibm-plex-sans-latin-ext.woff2'],
  ['node_modules/@fontsource-variable/jetbrains-mono/files/jetbrains-mono-latin-wght-normal.woff2', 'public/assets/fonts/jetbrains-mono-latin.woff2'],
  ['node_modules/@alpinejs/csp/dist/cdn.min.js', 'public/assets/js/alpine-csp.min.js'],
  ['resources/js/app.js', 'public/assets/js/app.js'],
  // Génération du QR code 2FA côté navigateur (MIT) : le secret ne quitte jamais le serveur et la page.
  ['node_modules/qrcode-generator/qrcode.js', 'public/assets/js/qrcode.js'],
];

mkdirSync('public/assets/fonts', { recursive: true });
mkdirSync('public/assets/js', { recursive: true });
for (const [from, to] of copies) {
  cpSync(from, to);
  console.log(`✔ ${to}`);
}
