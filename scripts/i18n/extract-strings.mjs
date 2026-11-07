#!/usr/bin/env node
/**
 * ATG i18n — extract translatable UI text from an app's Blade templates.
 *
 *   node scripts/i18n/extract-strings.mjs <appRoot> [outFile]
 *
 * Collects visible text between tags plus the placeholder / title / aria-label / alt
 * attributes, skipping <script>, <style>, <code>, <pre>, Blade comments and directives.
 * Text that embeds a Blade echo ("Hello {{ $name }}!") becomes a pattern ("Hello {0}!")
 * so the runtime translator can match it with the real value substituted.
 * Output: JSON array of unique source strings (sorted), default <appRoot>/resources/lang/auto/source.json
 */
import fs from 'fs';
import path from 'path';

const [, , appRoot, outArg] = process.argv;
if (!appRoot) { console.error('usage: extract-strings.mjs <appRoot> [outFile]'); process.exit(1); }
const viewsDirs = ['resources/views', 'resources/view'].map((d) => path.join(appRoot, d)).filter((d) => fs.existsSync(d));
const outFile = outArg || path.join(appRoot, 'resources/lang/auto/source.json');

const ENT = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', bull: '•', middot: '·', mdash: '—', ndash: '–', rsquo: '’', lsquo: '‘', rdquo: '”', ldquo: '“', hellip: '…', pound: '£', euro: '€', copy: '©', reg: '®', trade: '™', times: '×', rarr: '→', larr: '←', check: '✓' };
const decode = (s) => s
  .replace(/&#x([0-9a-f]+);/gi, (_, h) => String.fromCodePoint(parseInt(h, 16)))
  .replace(/&#(\d+);/g, (_, d) => String.fromCodePoint(+d))
  .replace(/&([a-z]+);/gi, (m, n) => (ENT[n.toLowerCase()] ?? m));
const norm = (s) => decode(s).replace(/\s+/g, ' ').trim();

const files = [];
const walk = (d) => fs.readdirSync(d, { withFileTypes: true }).forEach((e) => {
  const p = path.join(d, e.name);
  if (e.isDirectory()) { if (!/^(vendor|node_modules|emails?|mail|msg)$/i.test(e.name)) walk(p); }
  else if (e.name.endsWith('.blade.php')) files.push(p);
});
viewsDirs.forEach(walk);

/** A string is worth translating if it has real words and isn't code/data. */
const worthy = (s) => {
  if (s.length < 2 || s.length > 600) return false;
  if (!/[A-Za-z]{2,}/.test(s)) return false;                       // needs letters
  if (/[{}$;]|=>|->|::|\(\)|&&|\|\||^\/|<\?|\?>/.test(s.replace(/\{\d+\}/g, ''))) return false; // code-ish
  if (/^[\w.-]+@[\w.-]+$/.test(s) || /^https?:\/\//.test(s)) return false;
  if (/^[A-Z0-9_]+$/.test(s) && s.length > 3 && s.includes('_')) return false; // CONSTANT_NAMES
  if (/^[a-z]+(?:[A-Z][a-z]+)+$/.test(s)) return false;           // camelCase identifiers
  if (/^(true|false|null|undefined|px|rem|em|auto|none)$/i.test(s)) return false;
  if (/=\s*"|"\s*>|^["'>]|\b(?:px|py|mx|my|gap|rounded|text|bg|border|flex|grid|w|h)-[\w./[\]]+/.test(s)) return false; // markup / CSS leftovers
  return true;
};

const found = new Set();
for (const f of files) {
  let src = fs.readFileSync(f, 'utf8');
  src = src
    .replace(/\{\{--[\s\S]*?--\}\}/g, ' ')
    .replace(/<script\b[\s\S]*?<\/script>/gi, ' ')
    .replace(/<style\b[\s\S]*?<\/style>/gi, ' ')
    .replace(/<(code|pre|svg|template\s+x-if)\b[\s\S]*?<\/\1>/gi, ' ')
    .replace(/<!--[\s\S]*?-->/g, ' ')
    .replace(/@php\b[\s\S]*?@endphp/g, ' ')
    .replace(/<\?php[\s\S]*?\?>/g, ' ');

  // attributes worth translating (skip ones built from echoes or Alpine expressions)
  for (const m of src.matchAll(/\s(placeholder|title|aria-label|alt|data-tooltip)\s*=\s*"([^"]*)"/gi)) {
    const v = m[2];
    if (/\{\{|\{!!|@/.test(v)) continue;
    const t = norm(v); if (worthy(t)) found.add(t);
  }

  // text between tags
  // tags, respecting quoted attribute values (Alpine attributes often contain '>' or '=>')
  const stripped = src.replace(/<[a-zA-Z\/!](?:[^>"']|"[^"]*"|'[^']*')*>/g, '\u0000');
  for (const raw of stripped.split('\u0000')) {
    // drop lines that are Blade directives
    const lines = raw.split('\n').filter((l) => !/^\s*@[a-zA-Z]+/.test(l));
    let text = lines.join(' ');
    if (/\{!!/.test(text)) continue;                                   // raw HTML echoes: skip
    let i = 0;
    text = text.replace(/\{\{[\s\S]*?\}\}/g, () => `{${i++}}`);       // echoes -> {0}, {1}…
    if (/@[a-zA-Z]+\s*\(/.test(text)) continue;                        // inline directives
    const t = norm(text);
    if (!t || /^(\{\d+\}\s*)+$/.test(t)) continue;                    // only echoes
    if (worthy(t)) found.add(t);
  }
}

const list = [...found].sort();
fs.mkdirSync(path.dirname(outFile), { recursive: true });
fs.writeFileSync(outFile, JSON.stringify(list, null, 1));
console.log(`${files.length} templates → ${list.length} unique strings → ${outFile}`);
