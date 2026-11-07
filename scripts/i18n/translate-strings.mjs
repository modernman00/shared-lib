#!/usr/bin/env node
/**
 * ATG i18n — machine-translate extracted UI strings into dictionary files.
 *
 *   node scripts/i18n/translate-strings.mjs <appRoot> fr es
 *
 * Reads  <appRoot>/resources/lang/auto/source.json   (from extract-strings.mjs)
 * Writes <appRoot>/resources/lang/auto/<lang>.json   { "English source": "Translation" }
 *
 * - Incremental: strings already translated in <lang>.json are kept, only new ones are sent.
 * - Human review: <appRoot>/resources/lang/auto/overrides.<lang>.json always wins (edit that file,
 *   not the generated one, to correct a translation).
 * - Safety: if a translation loses a {0} placeholder or a brand name, the English is kept instead.
 * Uses the same Google endpoint as the apps' content-translate API.
 */
import fs from 'fs';
import path from 'path';

const [, , appRoot, ...langs] = process.argv;
if (!appRoot || !langs.length) { console.error('usage: translate-strings.mjs <appRoot> <lang...>'); process.exit(1); }
const dir = path.join(appRoot, 'resources/lang/auto');
const source = JSON.parse(fs.readFileSync(path.join(dir, 'source.json'), 'utf8'));
const BRANDS = ['ExecMind', 'iAccountApp', 'iAccount', 'LoanEasy', 'TenantScore', 'ScoreTenant', 'PartyPlatform', 'FamilyPlatform', 'iDecide', 'HMRC', 'GOV.UK', 'FCA', 'CQC', 'PDF', 'CSV', 'VAT', 'ISA', 'SIPP', 'API', 'PWA', 'OCR', 'SA103', 'ITTOIA', 'FRS 102', 'Open Banking', 'Stripe', 'WhatsApp', 'LinkedIn', 'Google'];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

class BlockedError extends Error {}
async function gtx(text, tl) {
  const url = `https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=${tl}&dt=t`;
  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      const res = await fetch(url, { method: 'POST', signal: AbortSignal.timeout(15000), headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: 'q=' + encodeURIComponent(text) });
      const body = await res.text();
      // Google answers rate-limited clients with an HTML "Sorry… unusual traffic" page: stop at once
      if (res.status === 429 || /^\s*</.test(body)) throw new BlockedError('Translation service is rate-limiting this machine (HTTP ' + res.status + '). Progress saved; re-run later.');
      if (res.status >= 500) { await sleep(3000 * (attempt + 1)); continue; }
      const data = JSON.parse(body);
      return (data[0] || []).map((seg) => seg[0] || '').join('');
    } catch (e) {
      if (e instanceof BlockedError) throw e;
      await sleep(2000 * (attempt + 1));
    }
  }
  throw new Error('translation request failed');
}

/** Keep the English when the translation lost something that must survive. */
const valid = (src, out) => {
  if (!out || !out.trim()) return false;
  const ph = src.match(/\{\d+\}/g) || [];
  if (ph.some((p) => !out.includes(p))) return false;
  if (BRANDS.some((b) => src.includes(b) && !out.includes(b))) return false;
  return true;
};

for (const lang of langs) {
  const outFile = path.join(dir, `${lang}.json`);
  const dict = fs.existsSync(outFile) ? JSON.parse(fs.readFileSync(outFile, 'utf8')) : {};
  const todo = source.filter((s) => !(s in dict));
  console.log(`${lang}: ${Object.keys(dict).length} cached, ${todo.length} to translate`);
  // batches of lines (one string per line; strings never contain newlines after extraction)
  const batches = []; let cur = [], len = 0;
  for (const s of todo) { if (len + s.length > 1800 || cur.length >= 30) { batches.push(cur); cur = []; len = 0; } cur.push(s); len += s.length + 1; }
  if (cur.length) batches.push(cur);
  let done = 0, fallback = 0;
  for (const batch of batches) {
    let lines = null;
    try {
      try {
        const out = await gtx(batch.join('\n'), lang);
        lines = out.split('\n').map((l) => l.trim());
      } catch (e) { if (e instanceof BlockedError) throw e; lines = null; }
      if (!lines || lines.length !== batch.length) {         // batch drifted: translate one by one
        lines = [];
        for (const s of batch) { try { lines.push((await gtx(s, lang)).trim()); } catch (e) { if (e instanceof BlockedError) throw e; lines.push(''); } await sleep(1200); }
      }
    } catch (e) {
      if (e instanceof BlockedError) { fs.writeFileSync(outFile, JSON.stringify(dict, null, 1)); console.error('\n' + e.message); process.exit(2); }
      throw e;
    }
    batch.forEach((s, i) => {
      let t = lines[i];
      if (!valid(s, t)) { fallback++; t = s; }
      dict[s] = t;
    });
    done += batch.length;
    fs.writeFileSync(outFile, JSON.stringify(dict, null, 1));     // save progress after every batch
    process.stdout.write(`\r  ${lang}: ${done}/${todo.length}`);
    await sleep(1500);
  }
  // drop entries for strings no longer in the templates
  for (const k of Object.keys(dict)) if (!source.includes(k)) delete dict[k];
  fs.writeFileSync(outFile, JSON.stringify(dict, null, 1));
  console.log(`\n  ${lang}: ${Object.keys(dict).length} entries written (${fallback} kept in English for safety) → ${outFile}`);
}
