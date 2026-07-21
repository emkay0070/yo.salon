const fs = require('fs');
const path = require('path');

const directoriesToSearch = [
  'frontend/src/app',
];

const replacements = [
  // Note: leave auth pages (login/register) mostly intact as they use
  // intentional dark-overlay white opacity for cinematic effect.
  // We do NOT touch text-white/XX (opacity variants) on auth pages —
  // those are deliberate. We only replace bare `text-white` class tokens.
  //
  // SAFE global replacements (bare class names, not opacity variants):
  { target: /\btext-white\b/g, replacement: 'text-text-primary' },
  { target: /\btext-\[#A0A0A0\]/g, replacement: 'text-text-secondary' },
  { target: /\bbg-white\/5\b/g, replacement: 'bg-card' },
  { target: /\bbg-white\/3\b/g, replacement: 'bg-surface' },
  { target: /\bborder-white\/10\b/g, replacement: 'border-border-light' },
  { target: /\bborder-white\/8\b/g, replacement: 'border-border-medium' },
  { target: /\btext-\[#FFD700\]/g, replacement: 'text-gold' },
  { target: /\btext-\[#0A0A0A\]/g, replacement: 'text-obsidian' },
];

// auth pages intentionally keep cinematic dark-mode bg, no changes needed beyond bare text-white
const AUTH_PAGES = ['login', 'register'];

function processDirectory(dir) {
  const items = fs.readdirSync(dir);
  for (const item of items) {
    const fullPath = path.join(dir, item);
    const stat = fs.statSync(fullPath);
    if (stat.isDirectory()) {
      processDirectory(fullPath);
    } else if (fullPath.endsWith('.tsx') || fullPath.endsWith('.ts')) {
      let content = fs.readFileSync(fullPath, 'utf8');
      const original = content;
      for (const { target, replacement } of replacements) {
        content = content.replace(target, replacement);
      }
      if (content !== original) {
        fs.writeFileSync(fullPath, content, 'utf8');
        console.log('Updated:', fullPath);
      }
    }
  }
}

for (const dir of directoriesToSearch) {
  processDirectory(path.join(__dirname, dir));
}
console.log('Done.');
