/* ==========================================================================
 * ATG World Engine — 05 Poster Engine
 * 18 art-directed templates, every one animatable as a "motion poster".
 * Content rules (learned from production feedback):
 *   • Every text block is fitted to its box — long event names shrink/wrap,
 *     they never run off the canvas.
 *   • Empty fields render NOTHING. Sample copy lives only in form placeholders,
 *     so it can never be exported by mistake.
 * ========================================================================== */
(function (root) {
  'use strict';
  const WE = root.WorldEngine;
  const { clamp, lerp, range } = WE.M;
  const E = WE.Ease, C = WE.Color, F = WE.F;

  const SIZES = {
    story: { label: 'Story 9:16', w: 1080, h: 1920 }, portrait: { label: 'Portrait 4:5', w: 1080, h: 1350 },
    square: { label: 'Square 1:1', w: 1080, h: 1080 }, landscape: { label: 'Landscape 16:9', w: 1920, h: 1080 },
    a4: { label: 'A4 print', w: 1240, h: 1754 },
  };
  const has = (v) => v != null && String(v).trim() !== '';
  const joinInfo = (P) => [P.date, P.time, P.venue].filter(has);

  /* ---------------------------------------------------------- env/helpers */
  const fitCache = new Map();
  const fit = (ctx, text, w, h, font, max, min, lh) => {
    const k = [text, w | 0, h | 0, font, max | 0, min | 0, lh].join('|');
    let v = fitCache.get(k);
    if (!v) { v = WE.fitText(ctx, text, w, h, font, max, min, lh); if (fitCache.size > 300) fitCache.clear(); fitCache.set(k, v); }
    return v;
  };
  const photoCache = new Map();
  const makeEnv = (ctx, P, W, H, t, o) => {
    const u = Math.min(W, H) / 100, tall = H / W;
    const tpl = TEMPLATES[P.template] || TEMPLATES.riso;
    const pals = tpl.palettes;
    let pal = Object.assign({}, pals[(P.palette | 0) % pals.length]);
    if (P.palette === 'brand' && P.brand) pal = Object.assign(pal, { a: P.brand.a || pal.a, b: P.brand.b || pal.b, c: P.brand.c || pal.c });
    if (P.colors) Object.assign(pal, P.colors);
    const e = { ctx, P, W, H, u, t, tall, pal, still: !!o.still, rnd: WE.rng((P.seed || 1) * 7919 + WE.hash(P.template || '')) };
    e.in = (start, dur = 0.8, ease = E.outCubic) => (e.still ? 1 : ease(range(t, start, start + dur)));
    e.loop = (period = 6) => (t % period) / period;
    /** Fitted text. Returns layout (with x,y) or null when empty. */
    e.text = (str, x, y, w, h, s = {}) => {
      if (!has(str)) return null;
      let txt = String(str); if (s.upper) txt = txt.toUpperCase();
      const lay = fit(ctx, txt, w, h, s.font || F.grotesk, s.size || u * 10, s.min || u * 2.2, s.lh || 1.05);
      const ty = s.valign === 'middle' ? y + (h - lay.h) / 2 : s.valign === 'bottom' ? y + h - lay.h : y;
      const ax = s.align === 'center' ? x + w / 2 : s.align === 'right' ? x + w : x;
      const p = s.p == null ? e.in(s.start || 0, s.dur || 0.9) : s.p;
      if (p <= 0) return lay;
      if (s.layers) s.layers.forEach(([dx, dy, col]) => WE.drawText(ctx, lay, { x: ax + dx, y: ty + dy, align: s.align, color: col, p, effect: s.effect || 'rise', t, stroke: s.layerStroke ? col : null, strokeW: s.layerStroke }));
      WE.drawText(ctx, lay, Object.assign({ x: ax, y: ty, align: s.align, color: s.color || pal.ink, effect: s.effect || (e.still ? 'none' : 'rise'), p, t, hl: s.hl || pal.a, hlStyle: s.hlStyle || 'color' }, s.look || {}));
      lay.x = ax; lay.y = ty;
      return lay;
    };
    /** Letter-spaced single line. */
    e.spaced = (str, x, y, size, font, color, spacing = 0.2, align = 'left', maxW = null) => {
      if (!has(str)) return 0;
      let s = String(str);
      ctx.save(); ctx.font = `${size}px ${font}`; ctx.fillStyle = color; ctx.textBaseline = 'middle';
      const sp = size * spacing; let w = [...s].reduce((a, ch) => a + ctx.measureText(ch).width + sp, -sp);
      if (maxW && w > maxW) { const k = maxW / w; size *= k; ctx.font = `${size}px ${font}`; w = [...s].reduce((a, ch) => a + ctx.measureText(ch).width + sp * k, -sp * k); }
      let cx = align === 'center' ? x - w / 2 : align === 'right' ? x - w : x;
      for (const ch of s) { ctx.fillText(ch, cx, y); cx += ctx.measureText(ch).width + sp * (maxW && w > maxW ? maxW / w : 1); }
      ctx.restore();
      return w;
    };
    e.pill = (str, cx, cy, s = {}) => {
      if (!has(str)) return null;
      ctx.save(); const size = s.size || u * 3.4; ctx.font = `${size}px ${s.font || F.ui}`;
      let tw = ctx.measureText(String(str)).width; const maxW = s.maxW || W * 0.86; let fs = size;
      if (tw + size * 2 > maxW) { fs = size * (maxW - size * 2) / tw; ctx.font = `${fs}px ${s.font || F.ui}`; tw = ctx.measureText(String(str)).width; }
      const pw = tw + fs * 2.1, ph = fs * 2.1, x = s.align === 'left' ? cx : cx - pw / 2, y = cy - ph / 2;
      if (s.shadow) { ctx.fillStyle = s.shadow; WE.roundRect(ctx, x + u * 0.7, y + u * 0.7, pw, ph, s.r == null ? ph / 2 : s.r); ctx.fill(); }
      ctx.fillStyle = s.bg || pal.a; WE.roundRect(ctx, x, y, pw, ph, s.r == null ? ph / 2 : s.r); ctx.fill();
      if (s.border) { ctx.strokeStyle = s.border; ctx.lineWidth = s.borderW || u * 0.4; ctx.stroke(); }
      ctx.fillStyle = s.fg || C.readable(s.bg || pal.a); ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(String(str), x + pw / 2, cy + fs * 0.06);
      ctx.restore();
      return { x, y, w: pw, h: ph };
    };
    e.clip = (shape, x, y, w, h) => {
      ctx.beginPath();
      if (shape === 'circle') ctx.ellipse(x + w / 2, y + h / 2, w / 2, h / 2, 0, 0, 7);
      else if (shape === 'arch') { ctx.moveTo(x, y + h); ctx.lineTo(x, y + w / 2); ctx.arc(x + w / 2, y + w / 2, w / 2, Math.PI, 0); ctx.lineTo(x + w, y + h); ctx.closePath(); }
      else if (shape === 'blob') { const r = WE.rng(P.seed || 3); for (let i = 0; i <= 64; i++) { const a = (i / 64) * Math.PI * 2, k = 1 + Math.sin(a * 3 + r() * 6) * 0.06 + Math.sin(a * 5 + 1) * 0.04; const px = x + w / 2 + Math.cos(a) * (w / 2) * k, py = y + h / 2 + Math.sin(a) * (h / 2) * k; i ? ctx.lineTo(px, py) : ctx.moveTo(px, py); } ctx.closePath(); }
      else if (shape === 'rounded') WE.roundRect(ctx, x, y, w, h, Math.min(w, h) * 0.08);
      else ctx.rect(x, y, w, h);
    };
    /** Photo (with optional duotone/mono FX) or, when none, the cartoon character as hero. */
    e.hero = (x, y, w, h, s = {}) => {
      const img = P.photoImg;
      ctx.save();
      if (img && img.width) {
        e.clip(s.shape || P.photoShape || 'rect', x, y, w, h); ctx.clip();
        const fx = s.fx || P.photoFx || 'none';
        const bgFade = clamp(+P.photoBgFade || 0, 0, 1);
        const base = bgFade > 0 ? backgroundFadedPhoto(img, bgFade) : img;
        const src = fx === 'none' ? base : processPhoto(base, fx, s.duo || [pal.b || '#1a1a1a', pal.bg || '#ffffff']);
        const sc = Math.max(w / src.width, h / src.height) * (P.photoZoom || 1);
        const dw = src.width * sc, dh = src.height * sc;
        ctx.drawImage(src, x + (w - dw) / 2 + (P.photoX || 0) * w, y + (h - dh) * (s.anchorTop ? 0.15 : 0.5) + (P.photoY || 0) * h, dw, dh);
        ctx.restore();
        return 'photo';
      }
      ctx.restore();
      if (P.showCharacter === false) return null;
      const ch = Object.assign({}, P.character || {});
      const p = e.in(s.start || 0.2, 0.7, E.outBack);
      if (p <= 0) return 'char';
      ctx.save(); ctx.translate(x + w / 2, y + h); ctx.scale(p, p); ctx.translate(-(x + w / 2), -(y + h));
      WE.Cast.drawCharacter(ctx, x + w / 2, y + h, Math.min(h, w * 2.1), Object.assign(ch, { mood: s.mood || P.mood || 'excited', pose: s.pose || P.pose || 'celebrate', t: e.still ? 1.3 : t, still: e.still, seed: 2 }), { mode: s.mode || 'cartoon', pal: Object.assign({}, pal, { ink: s.ink || '#1d1b26', l: '#ffffff', d: pal.d || '#2b2d42', b: pal.b || pal.a, c: pal.c || pal.a }), lw: s.lw || u * 0.55, boil: s.mode === 'cartoon' && !e.still ? Math.floor(t * 6) : null });
      ctx.restore();
      return 'char';
    };
    e.prop = (name, x, y, size, s = {}) => {
      const p = s.p == null ? e.in(s.start || 0.4, 0.6, E.outBack) : s.p;
      if (p <= 0) return;
      ctx.save(); ctx.translate(x, y); ctx.scale(p, p); ctx.translate(-x, -y);
      const fl = e.still ? 0 : Math.sin(t * 2 + x) * size * 0.04;
      WE.Cast.drawProp(ctx, name, x, y + fl, size, { mode: s.mode || 'cartoon', pal: Object.assign({}, pal, { ink: s.ink || '#1d1b26', b: pal.b || pal.a, c: pal.c || pal.a, d: pal.d || '#2b2d42', l: '#ffffff' }), lw: s.lw || size * 0.035, progress: s.progress == null ? 1 : s.progress }, s.rot || 0);
      ctx.restore();
    };
    e.props = (spots, s = {}) => (P.props || []).slice(0, spots.length).forEach((name, i) => e.prop(name, spots[i][0] * W, spots[i][1] * H, (spots[i][2] || 12) * u, Object.assign({ start: 0.5 + i * 0.15, rot: spots[i][3] || 0 }, s)));
    e.qr = (x, y, size, s = {}) => { if (P.qr === false || !has(P.url)) return false; const url = /^https?:/i.test(P.url) ? P.url : 'https://' + P.url; WE.QR.draw(ctx, url, x, y, size, s); return true; };
    e.bg = (col) => { ctx.fillStyle = col || pal.bg; ctx.fillRect(0, 0, W, H); };
    e.grain = (a = 1, kind = 'grain') => { ctx.save(); ctx.globalAlpha = a; ctx.fillStyle = WE.texture(ctx, kind); ctx.fillRect(0, 0, W, H); ctx.restore(); };
    /** Standard info block: date / time / venue lines, perks and CTA. */
    e.info = (x, y, w, s = {}) => {
      const lines = joinInfo(P);
      let cy = y;
      const size = s.size || u * 3.6, font = s.font || F.ui, col = s.color || pal.ink;
      if (s.inline && lines.length) { const str = lines.join(s.sep || '  •  '); e.spaced(s.upper ? str.toUpperCase() : str, s.align === 'center' ? x + w / 2 : x, cy + size / 2, size, font, col, s.spacing || 0.04, s.align || 'left', w); cy += size * 1.6; }
      else lines.forEach((l) => { e.spaced(s.upper ? l.toUpperCase() : l, s.align === 'center' ? x + w / 2 : x, cy + size / 2, size, font, col, s.spacing || 0.04, s.align || 'left', w); cy += size * 1.45; });
      if (s.perks !== false && P.details && P.details.length) { const str = P.details.filter(has).join('  ·  '); e.spaced(s.upper ? str.toUpperCase() : str, s.align === 'center' ? x + w / 2 : x, cy + size * 0.45, size * 0.78, font, s.perkColor || col, 0.04, s.align || 'left', w); cy += size * 1.3; }
      return cy;
    };
    return e;
  };

  /**
   * Photo background transparency: fades the photo's own background (walls, rooms,
   * furniture) while keeping the person solid, so the poster shows through around them.
   * Same algorithm as the legacy poster studio's "Photo Background Transparency".
   */
  const fadePhotoBackground = (px, w, h, strength) => {
    const N = w * h;

    // 1. Background colour palette from edge samples.
    const samples = [];
    const sampleAt = (x, y) => {
      let r = 0, g = 0, b = 0, n = 0;
      for (let dy = -2; dy <= 2; dy++) {
        for (let dx = -2; dx <= 2; dx++) {
          const xx = Math.min(w - 1, Math.max(0, x + dx));
          const yy = Math.min(h - 1, Math.max(0, y + dy));
          const i = (yy * w + xx) * 4;
          if (px[i + 3] < 16) continue;
          r += px[i]; g += px[i + 1]; b += px[i + 2]; n++;
        }
      }
      if (n) samples.push([r / n, g / n, b / n]);
    };
    const steps = 28;
    for (let s = 0; s <= steps; s++) {
      const t = s / steps;
      if (t < 0.3 || t > 0.7) sampleAt(Math.round(t * (w - 1)), 2); // top edge, avoiding the head
      if (t <= 0.7) { // sides, avoiding where arms/shoulders usually touch the frame
        sampleAt(2, Math.round(t * (h - 1)));
        sampleAt(w - 3, Math.round(t * (h - 1)));
      }
    }
    const palette = [];
    for (const c of samples) {
      if (!palette.some((p) => (p[0] - c[0]) ** 2 + (p[1] - c[1]) ** 2 + (p[2] - c[2]) ** 2 < 324)) palette.push(c);
    }

    // 2. Distance of every pixel to the nearest background colour.
    const dist = new Float32Array(N);
    for (let p = 0, i = 0; p < N; p++, i += 4) {
      const r = px[i], g = px[i + 1], b = px[i + 2];
      let best = Infinity;
      for (let k = 0; k < palette.length; k++) {
        const c = palette[k];
        const d = (r - c[0]) * (r - c[0]) * 0.3 + (g - c[1]) * (g - c[1]) * 0.59 + (b - c[2]) * (b - c[2]) * 0.11;
        if (d < best) best = d;
      }
      dist[p] = Math.sqrt(best);
    }

    // 3. Flood fill from the top and side edges through background-coloured pixels.
    // The threshold is fixed and conservative: the slider only sets how transparent the
    // detected background becomes (tying it to the slider let the fill leak into dark hair/skin).
    const thr = 24;
    // Texture guard: hair (curls, strands) is busy, walls are mostly smooth. Local texture
    // energy (smoothed luminance gradient) stops the fill even where colours are similar,
    // e.g. dark hair against a dark wall.
    const lum = new Float32Array(N);
    for (let p = 0, i = 0; p < N; p++, i += 4) lum[p] = px[i] * 0.299 + px[i + 1] * 0.587 + px[i + 2] * 0.114;
    const grad = new Float32Array(N);
    for (let y = 1; y < h - 1; y++) {
      for (let x = 1; x < w - 1; x++) {
        const p = y * w + x;
        grad[p] = Math.abs(lum[p + 1] - lum[p - 1]) + Math.abs(lum[p + w] - lum[p - w]);
      }
    }
    const texR = Math.max(2, Math.round(Math.min(w, h) / 200));
    const texture = new Float32Array(N);
    {
      // box-average the gradient (integral image) so isolated thin lines don't count, dense texture does
      const S = new Float64Array((w + 1) * (h + 1));
      for (let y = 0; y < h; y++) {
        let row = 0;
        for (let x = 0; x < w; x++) { row += grad[y * w + x]; S[(y + 1) * (w + 1) + x + 1] = S[y * (w + 1) + x + 1] + row; }
      }
      for (let y = 0; y < h; y++) {
        const y0 = Math.max(0, y - texR), y1 = Math.min(h, y + texR + 1);
        for (let x = 0; x < w; x++) {
          const x0 = Math.max(0, x - texR), x1 = Math.min(w, x + texR + 1);
          texture[y * w + x] = (S[y1 * (w + 1) + x1] - S[y0 * (w + 1) + x1] - S[y1 * (w + 1) + x0] + S[y0 * (w + 1) + x0]) / ((y1 - y0) * (x1 - x0));
        }
      }
    }
    const textureLimit = 14;
    const bg = new Uint8Array(N);
    const queue = new Int32Array(N);
    let qh = 0, qt = 0;
    const visit = (p) => {
      if (!bg[p] && ((dist[p] < thr && texture[p] < textureLimit) || px[p * 4 + 3] < 16)) { bg[p] = 1; queue[qt++] = p; }
    };
    for (let x = 0; x < w; x++) visit(x);
    for (let y = 0; y < h; y++) { visit(y * w); visit(y * w + w - 1); }
    while (qh < qt) {
      const p = queue[qh++];
      const x = p % w;
      if (x > 0) visit(p - 1);
      if (x < w - 1) visit(p + 1);
      if (p >= w) visit(p - w);
      if (p < N - w) visit(p + w);
    }

    // 3b. Islands: textured background details (leaves, frames, shelves) survive the colour
    // fill. Keep only the connected region that contains the person — the one covering most
    // of the torso area — and treat every other kept island as background.
    {
      // Count of kept pixels in a (2r+1)² window, via an integral image.
      const windowKept = (keep, r) => {
        const S = new Int32Array((w + 1) * (h + 1));
        for (let y = 0; y < h; y++) {
          let row = 0;
          for (let x = 0; x < w; x++) { row += keep[y * w + x]; S[(y + 1) * (w + 1) + x + 1] = S[y * (w + 1) + x + 1] + row; }
        }
        return (x, y) => {
          const x0 = Math.max(0, x - r), x1 = Math.min(w, x + r + 1), y0 = Math.max(0, y - r), y1 = Math.min(h, y + r + 1);
          return [S[y1 * (w + 1) + x1] - S[y0 * (w + 1) + x1] - S[y1 * (w + 1) + x0] + S[y0 * (w + 1) + x0], (x1 - x0) * (y1 - y0)];
        };
      };
      const kept = new Uint8Array(N);
      for (let p = 0; p < N; p++) kept[p] = bg[p] ? 0 : 1;
      // Erode briefly so thin bridges (a leaf tip touching hair) no longer join regions.
      const er = Math.max(2, Math.round(Math.min(w, h) / 120));
      const countK = windowKept(kept, er);
      const core = new Uint8Array(N);
      for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) { const [c, a] = countK(x, y); core[y * w + x] = c === a ? 1 : 0; }
      // The person's core = the eroded region covering most of the torso area.
      const label = new Int32Array(N).fill(-1);
      const torso = [];
      let comp = 0;
      for (let s = 0; s < N; s++) {
        if (!core[s] || label[s] !== -1) continue;
        let head = 0, tail = 0, torsoHits = 0;
        queue[tail++] = s; label[s] = comp;
        while (head < tail) {
          const p = queue[head++];
          const x = p % w, y = (p / w) | 0;
          if (x > w * 0.35 && x < w * 0.65 && y > h * 0.55 && y < h * 0.9) torsoHits++;
          if (x > 0 && core[p - 1] && label[p - 1] === -1) { label[p - 1] = comp; queue[tail++] = p - 1; }
          if (x < w - 1 && core[p + 1] && label[p + 1] === -1) { label[p + 1] = comp; queue[tail++] = p + 1; }
          if (p >= w && core[p - w] && label[p - w] === -1) { label[p - w] = comp; queue[tail++] = p - w; }
          if (p < N - w && core[p + w] && label[p + w] === -1) { label[p + w] = comp; queue[tail++] = p + w; }
        }
        torso[comp++] = torsoHits;
      }
      let main = -1, best = 0;
      for (let c = 0; c < comp; c++) if (torso[c] > best) { best = torso[c]; main = c; }
      if (main >= 0) {
        // Grow the person's core back to its original outline (dilate within the kept area).
        const mainCore = new Uint8Array(N);
        for (let p = 0; p < N; p++) mainCore[p] = label[p] === main ? 1 : 0;
        const near = windowKept(mainCore, er + 1);
        for (let y = 0; y < h; y++) {
          for (let x = 0; x < w; x++) {
            const p = y * w + x;
            if (!bg[p] && near(x, y)[0] === 0) bg[p] = 1;
          }
        }
        // Head area: refill gaps between the left and right edges of the outline on each row,
        // so a hair parting/crown that happens to match a dark wall is not punched out.
        for (let y = 0; y < Math.round(h * 0.5); y++) {
          let lo = -1, hi = -1;
          for (let x = 0; x < w; x++) if (!bg[y * w + x]) { if (lo < 0) lo = x; hi = x; }
          if (lo >= 0 && hi - lo < w * 0.6) for (let x = lo; x <= hi; x++) bg[y * w + x] = 0;
        }
      }
    }

    // 4. Feather the mask edge (separable box blur).
    const rad = Math.max(1, Math.round(Math.min(w, h) / 260));
    let mask = Float32Array.from(bg);
    const blur = (src, horizontal) => {
      const out = new Float32Array(N);
      const len = horizontal ? w : h, lines = horizontal ? h : w;
      for (let l = 0; l < lines; l++) {
        let acc = 0;
        const at = (k) => src[horizontal ? l * w + k : k * w + l];
        for (let k = -rad; k <= rad; k++) acc += at(Math.min(len - 1, Math.max(0, k)));
        for (let k = 0; k < len; k++) {
          out[horizontal ? l * w + k : k * w + l] = acc / (rad * 2 + 1);
          acc += at(Math.min(len - 1, k + rad + 1)) - at(Math.max(0, k - rad));
        }
      }
      return out;
    };
    mask = blur(blur(mask, true), false);

    // 5. Apply: fade the background, plus a gentle vignette on far-off areas at high strengths
    //    (catches textured backgrounds the colour palette cannot fully describe).
    const prior = strength > 0.45 ? ((strength - 0.45) / 0.55) * 0.85 : 0;
    for (let y = 0; y < h; y++) {
      const ny = y / h;
      const ey = (ny - 0.56) / 0.62;
      for (let x = 0; x < w; x++) {
        const p = y * w + x, i = p * 4;
        let fade = mask[p] * strength;
        if (prior > 0) {
          const ex = (x / w - 0.5) / 0.4;
          const e = ex * ex + ey * ey;
          const outside = e <= 1 ? 0 : e >= 1.6 ? 1 : (e - 1) / 0.6;
          fade = Math.max(fade, outside * prior * strength);
        }
        let a = px[i + 3] * (1 - fade);
        if (ny > 0.88) a *= Math.max(0, 1 - (ny - 0.88) / 0.12); // same soft bottom edge as the cutout
        px[i + 3] = Math.round(a);
      }
    }
  };
  const cutoutCache = new Map();
  let cutoutSeq = 0;
  const backgroundFadedPhoto = (img, strength) => {
    img._weId = img._weId || 'img' + (++cutoutSeq);
    const key = img._weId + ':' + strength.toFixed(2);
    if (cutoutCache.has(key)) return cutoutCache.get(key);
    const k = Math.min(1, 1100 / Math.max(img.width, img.height));
    const c = document.createElement('canvas'); c.width = Math.max(1, Math.round(img.width * k)); c.height = Math.max(1, Math.round(img.height * k));
    const g = c.getContext('2d'); g.drawImage(img, 0, 0, c.width, c.height);
    try { const d = g.getImageData(0, 0, c.width, c.height); fadePhotoBackground(d.data, c.width, c.height, strength); g.putImageData(d, 0, 0); } catch (e) { return img; }
    c._weId = key;
    if (cutoutCache.size > 12) cutoutCache.clear();
    cutoutCache.set(key, c);
    return c;
  };

  /** Duotone / mono / halftone photo processing (cached). */
  const processPhoto = (img, fx, duo) => {
    const id = img._weId || ((img.src || '').length + ':' + (img.src || '').slice(-40));
    const key = id + fx + duo.join();
    if (photoCache.has(key)) return photoCache.get(key);
    const sc = Math.min(1, 900 / Math.max(img.width, img.height));
    const c = document.createElement('canvas'); c.width = Math.round(img.width * sc); c.height = Math.round(img.height * sc);
    const g = c.getContext('2d'); g.drawImage(img, 0, 0, c.width, c.height);
    const d = g.getImageData(0, 0, c.width, c.height), a = C.hexToRgb(duo[0]), b = C.hexToRgb(duo[1]);
    for (let i = 0; i < d.data.length; i += 4) {
      let l = (d.data[i] * 0.3 + d.data[i + 1] * 0.59 + d.data[i + 2] * 0.11) / 255;
      l = clamp((l - 0.5) * 1.25 + 0.5);
      if (fx === 'mono') { d.data[i] = d.data[i + 1] = d.data[i + 2] = l * 255; }
      else { d.data[i] = lerp(a[0], b[0], l); d.data[i + 1] = lerp(a[1], b[1], l); d.data[i + 2] = lerp(a[2], b[2], l); }
    }
    g.putImageData(d, 0, 0);
    if (fx === 'halftone') {
      const h = document.createElement('canvas'); h.width = c.width; h.height = c.height; const hg = h.getContext('2d');
      hg.fillStyle = duo[1]; hg.fillRect(0, 0, h.width, h.height); hg.fillStyle = duo[0];
      const step = Math.max(6, c.width / 90);
      for (let y = 0; y < c.height; y += step) for (let x = (y / step) % 2 ? step / 2 : 0; x < c.width; x += step) { const px = g.getImageData(x | 0, y | 0, 1, 1).data; const l = 1 - (px[0] * 0.3 + px[1] * 0.59 + px[2] * 0.11) / 255; hg.beginPath(); hg.arc(x, y, (step / 2) * Math.sqrt(l) * 1.1, 0, 7); hg.fill(); }
      photoCache.set(key, h); return h;
    }
    photoCache.set(key, c);
    return c;
  };

  /* ------------------------------------------------------------ templates */
  const TEMPLATES = {
    riso: {
      label: 'Riso Pop', tags: ['party', 'family', 'any'], blurb: 'Two-ink risograph overprint, halftone, misregistration',
      palettes: [{ bg: '#f4efe6', a: '#ff48b0', b: '#0078bf', c: '#ffe800', ink: '#1a1a1a' }, { bg: '#f7f1e3', a: '#ff6c2f', b: '#00a95c', c: '#ffd23f', ink: '#222222' }, { bg: '#efe9df', a: '#e2006a', b: '#3255a4', c: '#f15060', ink: '#111111' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg();
        ctx.save(); ctx.globalCompositeOperation = 'multiply';
        const cr = Math.min(W, H) * 0.36, cx = W * 0.64, cy = H * 0.3;
        ctx.fillStyle = pal.c; ctx.beginPath(); ctx.arc(cx + u * 2, cy + u * 2, cr * e.in(0, 1, E.outBack), 0, 7); ctx.fill();
        ctx.save(); ctx.translate(cx, cy); ctx.rotate(t * 0.05); ctx.fillStyle = WE.texture(ctx, 'halftone', pal.a); ctx.beginPath(); ctx.arc(0, 0, cr * 0.9, 0, 7); ctx.fill(); ctx.restore();
        ctx.restore();
        const hx = W * 0.64 - cr * 0.78, hy = H * 0.3 - cr * 0.78;
        ctx.save(); ctx.globalCompositeOperation = 'multiply'; e.hero(hx, hy, cr * 1.56, cr * 1.56, { shape: 'circle', fx: 'duotone', duo: [pal.b, '#ffffff'] }); ctx.restore();
        e.spaced(P.kicker, W * 0.06, H * 0.05, u * 3.2, F.mono, pal.ink, 0.25, 'left', W * 0.5);
        const off = u * (0.7 + (e.still ? 0 : Math.sin(t * 1.4) * 0.35));
        ctx.save(); ctx.globalCompositeOperation = 'multiply';
        e.text(P.headline, W * 0.06, H * 0.56, W * 0.88, H * 0.26, { font: F.condensed, size: u * 26, upper: true, color: pal.b, lh: 0.92, valign: 'bottom', hl: pal.b });
        e.text(P.headline, W * 0.06 + off, H * 0.56 + off * 0.8, W * 0.88, H * 0.26, { font: F.condensed, size: u * 26, upper: true, color: pal.a, lh: 0.92, valign: 'bottom', hl: pal.a, start: 0.15 });
        ctx.restore();
        const sl = e.text(P.sub, W * 0.06, H * 0.83, W * 0.6, H * 0.06, { font: F.grotesk, size: u * 4.4, color: pal.ink });
        e.info(W * 0.06, sl ? sl.y + sl.h + u * 1.5 : H * 0.85, W * 0.62, { font: F.mono, size: u * 2.9, upper: true });
        e.qr(W - u * 20, H - u * 20, u * 15, { fg: pal.ink, bg: pal.bg });
        if (has(P.badge)) { const bp = e.in(0.6, 0.6, E.outBack); ctx.save(); ctx.translate(W * 0.2, H * 0.36); ctx.rotate(-0.25 + (e.still ? 0 : Math.sin(t) * 0.05)); ctx.scale(bp, bp); ctx.fillStyle = pal.a; ctx.globalCompositeOperation = 'multiply'; ctx.beginPath(); for (let i = 0; i < 28; i++) { const a = (i / 28) * Math.PI * 2, r = i % 2 ? u * 9 : u * 11; ctx.lineTo(Math.cos(a) * r, Math.sin(a) * r); } ctx.fill(); ctx.restore(); ctx.save(); ctx.translate(W * 0.2, H * 0.36); ctx.rotate(-0.25); ctx.scale(bp, bp); e.text(P.badge, -u * 8, -u * 5, u * 16, u * 10, { font: F.condensed, size: u * 5, upper: true, color: '#ffffff', align: 'center', valign: 'middle', p: 1 }); ctx.restore(); }
        e.grain(1, 'paper');
      },
    },
    swiss: {
      label: 'Swiss Grid', tags: ['finance', 'business', 'any'], blurb: 'International Typographic Style — grid, giant numeral',
      palettes: [{ bg: '#f2f0eb', a: '#e3241b', b: '#111111', ink: '#111111' }, { bg: '#111111', a: '#ff4d00', b: '#f2f0eb', ink: '#f2f0eb' }, { bg: '#e9f0ff', a: '#0047ff', b: '#0b0b0b', ink: '#0b0b0b' }],
      render(e) {
        const { ctx, W, H, u, P, pal } = e;
        e.bg();
        ctx.save(); ctx.strokeStyle = C.rgba(pal.ink, 0.13); ctx.lineWidth = 1;
        for (let i = 1; i < 6; i++) { const x = (W * i) / 6, p = e.in(i * 0.05, 0.8); ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H * p); ctx.stroke(); }
        ctx.restore();
        const num = (String(P.date || '').match(/\d{1,2}/) || [has(P.badge) ? String(P.badge).slice(0, 3) : ''])[0];
        if (num) { const p = e.in(0.2, 1); ctx.save(); ctx.globalAlpha = p; ctx.fillStyle = pal.a; ctx.font = `${H * 0.5}px ${F.condensed}`; ctx.textAlign = 'right'; ctx.textBaseline = 'alphabetic'; ctx.fillText(num, W * 1.02, H * 0.98 + (1 - p) * H * 0.1); ctx.restore(); }
        ctx.fillStyle = pal.a; ctx.fillRect(W / 6, H * 0.05, W * (4 / 6) * e.in(0, 0.6), u * 1.6);
        e.spaced(P.kicker, W / 6, H * 0.09, u * 2.8, F.ui, pal.ink, 0.12, 'left', W * 0.6);
        e.text(P.headline, W / 6, H * 0.13, W * (4 / 6), H * 0.34, { font: F.grotesk, size: u * 16, color: pal.ink, lh: 0.95, hl: pal.a });
        e.text(P.sub, W / 6, H * 0.5, W * 0.5, H * 0.1, { font: F.ui, size: u * 3.6, color: pal.ink, start: 0.3 });
        const cols = [['Date', P.date], ['Time', P.time], ['Where', P.venue]].filter((c) => has(c[1]));
        cols.forEach(([k, v], i) => { const x = W / 6 + i * (W / 6) * 1.35; e.spaced(k.toUpperCase(), x, H * 0.63, u * 2.2, F.mono, pal.a, 0.1); e.text(v, x, H * 0.65, (W / 6) * 1.25, H * 0.08, { font: F.ui, size: u * 3.3, color: pal.ink, start: 0.4 + i * 0.1 }); });
        if (P.details && P.details.length) e.text(P.details.filter(has).join('\n'), W / 6, H * 0.75, W * 0.32, H * 0.14, { font: F.ui, size: u * 2.8, color: pal.ink, lh: 1.35, start: 0.6 });
        if (P.photoImg) e.hero(W * 0.62, H * 0.5, W * 0.3, W * 0.3, { shape: 'rect', fx: 'mono' });
        e.qr(W / 6, H - u * 19, u * 14, { fg: pal.bg === '#111111' ? '#111111' : pal.ink, bg: pal.bg === '#111111' ? '#f2f0eb' : pal.bg });
        e.spaced(P.url, W / 6 + u * 17, H - u * 12, u * 2.6, F.mono, pal.ink, 0.05, 'left', W * 0.4);
      },
    },
    groovy: {
      label: 'Groovy 70s', tags: ['party', 'family'], blurb: 'Sunset rainbow arcs, stacked retro shadows, wavy type',
      palettes: [{ bg: '#fff1d6', a: '#e8590c', b: '#f59f00', c: '#c2255c', d: '#5f3dc4', ink: '#3b1f0e' }, { bg: '#f7e8d0', a: '#2b8a3e', b: '#e67700', c: '#c92a2a', d: '#1864ab', ink: '#2b1a0e' }, { bg: '#fde2e4', a: '#ff7b54', b: '#ffb26b', c: '#ffd56f', d: '#939b62', ink: '#3d2b1f' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg();
        const cx = W / 2, cy = H * 1.02, R = Math.max(W, H) * 0.62, cols = [pal.d, pal.c, pal.a, pal.b];
        ctx.save(); ctx.translate(cx, cy); ctx.rotate(e.still ? 0 : t * 0.04);
        for (let i = 0; i < 24; i++) { const a0 = (i / 24) * Math.PI * 2; ctx.fillStyle = C.rgba(pal.b, i % 2 ? 0.16 : 0.0); ctx.beginPath(); ctx.moveTo(0, 0); ctx.arc(0, 0, R * 2, a0, a0 + Math.PI / 24); ctx.fill(); }
        ctx.restore();
        cols.forEach((c, i) => { const p = e.in(i * 0.12, 0.9, E.outBack), r = R * (0.95 - i * 0.16) * p; ctx.fillStyle = c; ctx.beginPath(); ctx.arc(cx, cy, Math.max(0, r), Math.PI, 0); ctx.fill(); });
        ctx.fillStyle = pal.bg; ctx.beginPath(); ctx.arc(cx, cy, R * 0.3 * e.in(0.4, 0.8, E.outBack), Math.PI, 0); ctx.fill();
        e.hero(W * 0.3, H * 0.62, W * 0.4, H * 0.36, { mood: 'excited', pose: 'celebrate' });
        e.text(P.kicker, W * 0.1, H * 0.06, W * 0.8, H * 0.07, { font: F.script, size: u * 7, color: pal.c, align: 'center', effect: 'pop' });
        const hl = e.text(P.headline, W * 0.06, H * 0.14, W * 0.88, H * 0.3, { font: F.groovy, size: u * 18, color: pal.bg, align: 'center', lh: 0.98, effect: e.still ? 'none' : 'wave', layers: [[u * 1.6, u * 1.6, pal.d], [u * 1.1, u * 1.1, pal.c], [u * 0.55, u * 0.55, pal.a]], look: { stroke: pal.ink, strokeW: u * 0.5, hl: pal.b, hlStyle: 'color' } });
        e.text(P.sub, W * 0.12, hl ? hl.y + hl.h + u * 2 : H * 0.42, W * 0.76, H * 0.08, { font: F.round, size: u * 4.6, color: pal.ink, align: 'center', start: 0.4 });
        const info = joinInfo(P).join('  ✦  ');
        e.pill(info, W / 2, H * 0.55, { bg: pal.ink, fg: pal.bg, font: F.round, size: u * 3.4 });
        if (P.details && P.details.length) e.text(P.details.filter(has).join('  ·  '), W * 0.1, H * 0.585, W * 0.8, H * 0.04, { font: F.round, size: u * 3, color: pal.ink, align: 'center', start: 0.6 });
        if (e.qr(W - u * 18, H - u * 18, u * 13, { fg: pal.ink })) void 0;
        e.grain(0.8, 'paper');
      },
    },
    chrome: {
      label: 'Y2K Chrome', tags: ['party', 'social'], blurb: 'Holographic gradient, liquid chrome type, sparkles',
      palettes: [{ bg: '#c8b6ff', a: '#ff8fd8', b: '#7ef9ff', c: '#fff38a', ink: '#1a1033' }, { bg: '#0a0a0a', a: '#ff00e5', b: '#00f0ff', c: '#c6ff00', ink: '#ffffff' }, { bg: '#ffd6f5', a: '#9d4edd', b: '#80ffdb', c: '#ffbe0b', ink: '#240046' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        const sh = e.still ? 0 : t * 0.3;
        if (ctx.createConicGradient) { const g = ctx.createConicGradient(sh, W * 0.5, H * 0.45); [pal.a, pal.b, pal.c, pal.bg, pal.a].forEach((c, i) => g.addColorStop(i / 4, c)); ctx.fillStyle = g; } else ctx.fillStyle = pal.bg;
        ctx.fillRect(0, 0, W, H);
        ctx.fillStyle = C.rgba(pal.bg, 0.35); ctx.fillRect(0, 0, W, H);
        e.hero(W * 0.22, H * 0.4, W * 0.56, H * 0.42, { shape: 'circle', mood: 'wink', pose: 'present' });
        const chrome = ['#ffffff', '#c9cfe0', '#ffffff', '#6b7189', '#eef1ff', '#9aa0b5'];
        ctx.save(); ctx.translate(W / 2, 0); ctx.transform(1, 0, -0.12, 1, 0, 0); ctx.translate(-W / 2, 0);
        e.text(P.headline, W * 0.06, H * 0.08, W * 0.88, H * 0.3, { font: F.wide, size: u * 16, align: 'center', lh: 0.95, look: { gradient: chrome, stroke: pal.ink, strokeW: u * 0.9, shadow: C.rgba(pal.a, 0.9), shadowBlur: u * 3, hl: null }, upper: true });
        ctx.restore();
        e.spaced(P.kicker, W / 2, H * 0.05, u * 3, F.wide, pal.ink, 0.3, 'center', W * 0.8);
        e.text(P.sub, W * 0.1, H * 0.84, W * 0.8, H * 0.06, { font: F.wide, size: u * 4.2, color: pal.ink, align: 'center', start: 0.4 });
        const info = joinInfo(P).join('  ★  ');
        e.pill(info, W / 2, H * 0.92, { bg: 'rgba(255,255,255,0.55)', fg: pal.ink, border: pal.ink, font: F.wide, size: u * 2.8 });
        for (let i = 0; i < 9; i++) { const r = WE.rng(i + 3), x = r() * W, y = r() * H, s = u * (2 + r() * 4) * (e.still ? 1 : 0.6 + Math.abs(Math.sin(t * 2 + i)) * 0.6); WE.sparkle(ctx, x, y, s, '#ffffff', 0); }
        if (has(P.badge)) e.pill(P.badge, W * 0.8, H * 0.46, { bg: pal.c, fg: pal.ink, font: F.wide, size: u * 3.2, border: pal.ink });
        e.qr(u * 4, H - u * 17, u * 12, { fg: pal.ink });
      },
    },
    memphis: {
      label: 'Memphis Party', tags: ['party', 'family', 'social'], blurb: 'Squiggles, confetti geometry, bold offset box',
      palettes: [{ bg: '#fff6e9', a: '#ff5c8a', b: '#00b8a9', c: '#ffd166', d: '#6c5ce7', ink: '#1b1b1b' }, { bg: '#e8f7ff', a: '#ff7f50', b: '#3a86ff', c: '#ffbe0b', d: '#8338ec', ink: '#14213d' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg();
        const cols = [pal.a, pal.b, pal.c, pal.d], r = WE.rng((P.seed || 1) * 31);
        for (let i = 0; i < 22; i++) {
          const x = r() * W, y = r() * H, s = u * (3 + r() * 6), k = Math.floor(r() * 5), col = cols[i % 4], rot = r() * 6 + (e.still ? 0 : Math.sin(t + i) * 0.2), p = e.in(i * 0.03, 0.5, E.outBack);
          ctx.save(); ctx.translate(x, y); ctx.rotate(rot); ctx.scale(p, p); ctx.strokeStyle = col; ctx.fillStyle = col; ctx.lineWidth = u * 1.1; ctx.lineCap = 'round';
          if (k === 0) { ctx.beginPath(); for (let j = 0; j <= 20; j++) ctx.lineTo(-s * 1.5 + (j / 20) * s * 3, Math.sin(j * 0.9) * s * 0.4); ctx.stroke(); }
          else if (k === 1) { ctx.beginPath(); ctx.moveTo(0, -s); ctx.lineTo(s, s); ctx.lineTo(-s, s); ctx.closePath(); ctx.stroke(); }
          else if (k === 2) { ctx.beginPath(); ctx.arc(0, 0, s * 0.6, 0, 7); ctx.fill(); }
          else if (k === 3) { for (let a = 0; a < 3; a++) for (let b = 0; b < 3; b++) { ctx.beginPath(); ctx.arc(a * s * 0.5, b * s * 0.5, u * 0.5, 0, 7); ctx.fill(); } }
          else { ctx.beginPath(); for (let j = 0; j < 6; j++) ctx.lineTo(j * s * 0.5, j % 2 ? s * 0.4 : 0); ctx.stroke(); }
          ctx.restore();
        }
        e.hero(W * 0.25, H * 0.5, W * 0.5, H * 0.38, { mood: 'excited', pose: 'celebrate' });
        e.props([[0.16, 0.56, 13, -0.2], [0.84, 0.6, 13, 0.2], [0.82, 0.86, 10, 0]]);
        const bx = W * 0.07, by = H * 0.08, bw = W * 0.86, bh = H * 0.32, p = e.in(0.1, 0.6, E.outBack);
        ctx.save(); ctx.translate(W / 2, by + bh / 2); ctx.rotate(-0.03); ctx.scale(p, p); ctx.translate(-W / 2, -(by + bh / 2));
        ctx.fillStyle = pal.d; ctx.fillRect(bx + u * 2, by + u * 2, bw, bh); ctx.fillStyle = '#ffffff'; ctx.fillRect(bx, by, bw, bh); ctx.strokeStyle = pal.ink; ctx.lineWidth = u * 0.8; ctx.strokeRect(bx, by, bw, bh);
        e.spaced(P.kicker, W / 2, by + u * 4.5, u * 3, F.round, pal.a, 0.15, 'center', bw * 0.9);
        e.text(P.headline, bx + u * 4, by + u * 8, bw - u * 8, bh - u * 11, { font: F.comic, size: u * 14, color: pal.ink, align: 'center', valign: 'middle', hl: pal.a, upper: true, p: 1 });
        ctx.restore();
        e.text(P.sub, W * 0.1, by + bh + u * 4, W * 0.8, H * 0.06, { font: F.round, size: u * 4.4, color: pal.ink, align: 'center', start: 0.4 });
        const y = H * 0.9; e.info(W * 0.08, y - u * 2, W * 0.64, { font: F.round, size: u * 3.3, upper: true });
        e.qr(W - u * 17, H - u * 17, u * 13, { fg: pal.ink });
      },
    },
    editorial: {
      label: 'Magazine Cover', tags: ['any', 'business', 'family'], blurb: 'Masthead, cover lines, barcode — newsstand energy',
      palettes: [{ bg: '#f3eee6', a: '#d7263d', b: '#1b998b', ink: '#111111' }, { bg: '#151515', a: '#f5d300', b: '#ff4f79', ink: '#ffffff' }, { bg: '#dfe9f3', a: '#0b3d91', b: '#ff6b35', ink: '#0b1320' }],
      render(e) {
        const { ctx, W, H, u, P, pal } = e;
        e.bg();
        const z = e.still ? 1 : 1 + e.loop(12) * 0.04;
        ctx.save(); ctx.translate(W / 2, H * 0.6); ctx.scale(z, z); ctx.translate(-W / 2, -H * 0.6);
        if (P.photoImg) e.hero(0, H * 0.14, W, H * 0.86, { shape: 'rect', anchorTop: true });
        else { ctx.fillStyle = pal.a; ctx.beginPath(); ctx.arc(W * 0.62, H * 0.6, W * 0.4, 0, 7); ctx.fill(); e.hero(W * 0.3, H * 0.32, W * 0.62, H * 0.66, { mood: 'proud', pose: 'present' }); }
        ctx.restore();
        const masthead = (P.brand && P.brand.name) || P.kicker || '';
        e.text(masthead, W * 0.04, H * 0.02, W * 0.92, H * 0.15, { font: F.display, size: u * 22, color: P.photoImg ? '#ffffff' : pal.ink, align: 'center', valign: 'middle', look: P.photoImg ? { shadow: 'rgba(0,0,0,0.35)', shadowBlur: u * 2 } : {} });
        e.spaced(has(P.date) ? 'ISSUE · ' + String(P.date).toUpperCase() : '', W * 0.96, H * 0.17, u * 2.4, F.ui, pal.ink, 0.12, 'right', W * 0.5);
        const lx = W * 0.05, lw = W * 0.42;
        const hl = e.text(P.headline, lx, H * 0.24, lw, H * 0.3, { font: F.serif, size: u * 10, color: pal.ink, lh: 0.98, hl: pal.a, start: 0.2, look: P.photoImg ? { shadow: 'rgba(255,255,255,0.6)', shadowBlur: u * 2 } : {} });
        let y = hl ? hl.y + hl.h + u * 3 : H * 0.5;
        const lines = [P.sub, ...(P.details || []), P.time, P.venue].filter(has).slice(0, 5);
        lines.forEach((l, i) => { ctx.fillStyle = pal.a; ctx.fillRect(lx, y + u * 0.6, u * 4, u * 0.7); const L = e.text(l, lx, y + u * 1.8, lw, H * 0.07, { font: F.ui, size: u * 3.4, color: pal.ink, start: 0.4 + i * 0.1 }); y += (L ? L.h : u * 4) + u * 3; });
        if (!e.qr(W - u * 18, H - u * 18, u * 14)) { ctx.fillStyle = '#fff'; ctx.fillRect(W - u * 22, H - u * 12, u * 18, u * 9); ctx.fillStyle = '#000'; const r = WE.rng(5); for (let x = 0; x < u * 16; x += u * 0.5) if (r() > 0.4) ctx.fillRect(W - u * 21 + x, H - u * 11, u * (0.15 + r() * 0.3), u * 6.5); }
        if (has(P.badge)) { ctx.save(); ctx.translate(W * 0.84, H * 0.3); ctx.rotate(0.15); ctx.fillStyle = pal.b; ctx.beginPath(); ctx.arc(0, 0, u * 10, 0, 7); ctx.fill(); ctx.restore(); e.text(P.badge, W * 0.84 - u * 8, H * 0.3 - u * 6, u * 16, u * 12, { font: F.condensed, size: u * 6, color: '#fff', align: 'center', valign: 'middle', upper: true }); }
      },
    },
    aurora: {
      label: 'Aurora Glass', tags: ['business', 'finance', 'tech', 'any'], blurb: 'Drifting mesh gradients and a frosted glass card',
      palettes: [{ bg: '#0b1026', a: '#7c3aed', b: '#06b6d4', c: '#f472b6', ink: '#ffffff' }, { bg: '#f1f5f9', a: '#a78bfa', b: '#38bdf8', c: '#fb7185', ink: '#0f172a' }, { bg: '#04130d', a: '#10b981', b: '#a3e635', c: '#22d3ee', ink: '#ecfdf5' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg();
        [[pal.a, 0.2, 0.15], [pal.b, 0.85, 0.35], [pal.c, 0.3, 0.85], [pal.a, 0.9, 0.95]].forEach(([c, fx, fy], i) => { const x = W * fx + (e.still ? 0 : Math.sin(t * 0.5 + i * 2) * W * 0.08), y = H * fy + (e.still ? 0 : Math.cos(t * 0.4 + i) * H * 0.05), r = Math.max(W, H) * 0.55; const g = ctx.createRadialGradient(x, y, 0, x, y, r); g.addColorStop(0, C.rgba(c, 0.75)); g.addColorStop(1, C.rgba(c, 0)); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H); });
        e.grain(0.9);
        const dark = C.lum(pal.bg) < 0.3;
        const cx = W * 0.07, cy = H * 0.3, cw = W * 0.86, ch = H * 0.62, p = e.in(0.1, 0.9);
        ctx.save(); ctx.globalAlpha = p; ctx.translate(0, (1 - p) * u * 6);
        ctx.fillStyle = dark ? 'rgba(255,255,255,0.1)' : 'rgba(255,255,255,0.55)'; WE.roundRect(ctx, cx, cy, cw, ch, u * 5); ctx.fill();
        ctx.strokeStyle = dark ? 'rgba(255,255,255,0.35)' : 'rgba(255,255,255,0.9)'; ctx.lineWidth = u * 0.3; ctx.stroke();
        ctx.restore();
        e.hero(W * 0.3, H * 0.01, W * 0.4, H * 0.31, { shape: 'circle', mood: 'happy', pose: 'wave', mode: 'clean' });
        if (has(P.kicker)) e.pill(P.kicker, cx + u * 6, cy + u * 7, { align: 'left', bg: C.rgba(pal.a, 0.9), fg: '#fff', size: u * 2.8 });
        const hl = e.text(P.headline, cx + u * 6, cy + u * 13, cw - u * 12, ch * 0.4, { font: F.grotesk, size: u * 11, color: pal.ink, lh: 0.98, hl: pal.c, start: 0.3 });
        const sy = hl ? hl.y + hl.h + u * 2.5 : cy + ch * 0.5;
        const sl = e.text(P.sub, cx + u * 6, sy, cw - u * 12, ch * 0.16, { font: F.ui, size: u * 3.8, color: C.rgba(pal.ink, 0.8), start: 0.4 });
        let iy = (sl ? sl.y + sl.h : sy) + u * 3;
        ctx.fillStyle = C.rgba(pal.ink, 0.2); ctx.fillRect(cx + u * 6, iy, cw - u * 12, 1.5); iy += u * 3;
        const rows = [['calendar', P.date], ['clock', P.time], ['pin', P.venue]].filter((r) => has(r[1]));
        rows.forEach(([ic, v], i) => { e.prop(ic, cx + u * 9, iy + u * 2.5 + i * u * 6.5, u * 4.6, { mode: 'clean', start: 0.5 + i * 0.1 }); e.text(v, cx + u * 14, iy + i * u * 6.5 + u * 0.5, cw * 0.55, u * 4.5, { font: F.ui, size: u * 3.4, color: pal.ink, valign: 'middle', start: 0.5 + i * 0.1 }); });
        e.qr(cx + cw - u * 20, cy + ch - u * 20, u * 15, { fg: '#0f172a' });
        if (has(P.cta)) e.pill(P.cta, cx + cw - u * 18, cy + u * 7, { bg: pal.ink, fg: dark ? '#0b1026' : '#ffffff', size: u * 2.8, maxW: cw * 0.4 });
      },
    },
    papercut: {
      label: 'Paper Cut', tags: ['family', 'care', 'any'], blurb: 'Layered paper hills with real shadows, cosy & warm',
      palettes: [{ bg: '#ffe8d6', a: '#ffb4a2', b: '#e5989b', c: '#b5838d', d: '#6d6875', ink: '#2d2a32' }, { bg: '#dff3ff', a: '#9bd0f5', b: '#5aa9e6', c: '#3c78d8', d: '#1e3a5f', ink: '#10233b' }, { bg: '#e9f5db', a: '#cfe1b9', b: '#97a97c', c: '#718355', d: '#3a5a40', ink: '#1f2d1f' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        const g = ctx.createLinearGradient(0, 0, 0, H); g.addColorStop(0, C.shade(pal.bg, 0.4)); g.addColorStop(1, pal.bg); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
        ctx.save(); ctx.shadowColor = 'rgba(0,0,0,0.15)'; ctx.shadowBlur = u * 3; ctx.fillStyle = '#fff6e0'; ctx.beginPath(); ctx.arc(W * 0.75, H * 0.36, u * 14, 0, 7); ctx.fill(); ctx.restore();
        const layers = [pal.a, pal.b, pal.c, pal.d];
        layers.forEach((col, i) => {
          const base = H * (0.62 + i * 0.1), amp = H * 0.04, ph = i * 1.7 + (e.still ? 0 : Math.sin(t * 0.6 + i) * 0.2), p = e.in(i * 0.12, 0.9);
          ctx.save(); ctx.translate(0, (1 - p) * H * 0.3); ctx.shadowColor = 'rgba(40,20,20,0.35)'; ctx.shadowBlur = u * 2.5; ctx.shadowOffsetY = -u * 0.6; ctx.fillStyle = col;
          ctx.beginPath(); ctx.moveTo(0, H); for (let x = 0; x <= W; x += W / 40) ctx.lineTo(x, base + Math.sin(x / W * Math.PI * (2 + i * 0.5) + ph) * amp); ctx.lineTo(W, H); ctx.fill(); ctx.restore();
          if (i === 1) e.hero(W * 0.08, H * 0.42, W * 0.36, H * 0.26, { mode: 'paper', mood: 'happy', pose: 'wave' });
        });
        e.props([[0.86, 0.56, 9], [0.62, 0.62, 8]], { mode: 'paper' });
        e.spaced(P.kicker, W * 0.08, H * 0.07, u * 3, F.ui, pal.ink, 0.25, 'left', W * 0.8);
        e.text(P.headline, W * 0.08, H * 0.1, W * 0.84, H * 0.24, { font: F.serif, size: u * 13, color: pal.ink, lh: 0.98, hl: pal.d });
        e.text(P.sub, W * 0.08, H * 0.35, W * 0.55, H * 0.1, { font: F.ui, size: u * 3.8, color: pal.ink, start: 0.3 });
        e.info(W * 0.08, H * 0.82, W * 0.6, { color: '#ffffff', size: u * 3.5 });
        e.qr(W - u * 19, H - u * 19, u * 14);
        e.grain(0.7, 'paper');
      },
    },
    neon: {
      label: 'Neon Sign', tags: ['party', 'nightlife'], blurb: 'Brick wall, flickering neon tubes, after-dark glow',
      palettes: [{ bg: '#120b1e', a: '#ff2bd6', b: '#00e5ff', c: '#ffe94a', ink: '#ffffff' }, { bg: '#0b1418', a: '#39ff14', b: '#ff3131', c: '#ffffff', ink: '#ffffff' }, { bg: '#160a0a', a: '#ff6b00', b: '#ff2e63', c: '#f9f871', ink: '#ffffff' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg();
        ctx.save(); ctx.strokeStyle = 'rgba(255,255,255,0.05)'; ctx.lineWidth = u * 0.35; const bh = u * 5, bw = u * 12;
        for (let y = 0, r = 0; y < H; y += bh, r++) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke(); for (let x = (r % 2) * bw / 2; x < W; x += bw) { ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x, y + bh); ctx.stroke(); } }
        const vg = ctx.createRadialGradient(W / 2, H * 0.4, 0, W / 2, H * 0.4, Math.max(W, H) * 0.7); vg.addColorStop(0, C.rgba(pal.a, 0.18)); vg.addColorStop(1, 'rgba(0,0,0,0.75)'); ctx.fillStyle = vg; ctx.fillRect(0, 0, W, H); ctx.restore();
        const flick = (seed) => (e.still ? 1 : Math.sin(t * 23 + seed) > 0.96 || (t % 5 > 4.7 && seed % 2) ? 0.25 : 1);
        ctx.save(); ctx.globalAlpha = flick(3); ctx.strokeStyle = pal.b; ctx.lineWidth = u * 0.8; ctx.shadowColor = pal.b; ctx.shadowBlur = u * 3; WE.roundRect(ctx, u * 5, u * 5, W - u * 10, H - u * 10, u * 6); ctx.stroke(); ctx.restore();
        const glow = (c) => ({ shadow: c, shadowBlur: u * 2.8, stroke: null });
        e.text(P.kicker, W * 0.1, H * 0.08, W * 0.62, H * 0.1, { font: F.script, size: u * 9, color: '#ffffff', align: 'left', effect: 'type', look: Object.assign(glow(pal.a), { color: C.shade(pal.a, 0.6) }) });
        ctx.save(); ctx.globalAlpha = flick(7);
        e.text(P.headline, W * 0.1, H * 0.2, W * 0.8, H * 0.3, { font: F.neon, size: u * 14, color: C.shade(pal.c, 0.5), align: 'center', valign: 'middle', lh: 1.05, upper: true, effect: 'type', dur: 1.4, look: Object.assign(glow(pal.c), { hl: pal.a, hlStyle: 'glow' }) });
        ctx.restore();
        e.hero(W * 0.3, H * 0.5, W * 0.4, H * 0.28, { mode: 'neon', ink: '#ffffff', lw: u * 0.45, mood: 'excited', pose: 'celebrate' });
        e.props([[0.18, 0.62, 12], [0.82, 0.62, 12]], { mode: 'neon', ink: '#ffffff' });
        e.text(P.sub, W * 0.1, H * 0.8, W * 0.8, H * 0.05, { font: F.sign, size: u * 3.8, color: '#ffffff', align: 'center', look: glow(pal.b) });
        e.info(W * 0.1, H * 0.86, W * 0.8, { font: F.sign, size: u * 3, color: C.shade(pal.b, 0.6), align: 'center', inline: true, upper: true, perkColor: '#ffffff' });
        e.qr(W - u * 20, u * 8, u * 12, { fg: '#120b1e' });
      },
    },
    comic: {
      label: 'Comic Pop', tags: ['party', 'social', 'any'], blurb: 'Halftone panels, POW bursts, speech bubbles',
      palettes: [{ bg: '#ffe14d', a: '#ff2e4c', b: '#1fb6ff', c: '#ffffff', ink: '#111111' }, { bg: '#7df9ff', a: '#ff3cac', b: '#ffd23f', c: '#ffffff', ink: '#111111' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg();
        const g = u * 1.6;
        const panel = (x, y, w, h, col) => { ctx.fillStyle = col; ctx.fillRect(x, y, w, h); ctx.save(); ctx.globalAlpha = 0.25; ctx.fillStyle = WE.texture(ctx, 'halftone', C.shade(col, -0.35)); ctx.fillRect(x, y, w, h); ctx.restore(); ctx.strokeStyle = pal.ink; ctx.lineWidth = u; ctx.strokeRect(x, y, w, h); };
        panel(g, g, W - g * 2, H * 0.3, pal.a);
        panel(g, H * 0.3 + g * 2, W - g * 2, H * 0.48, pal.b);
        panel(g, H * 0.78 + g * 3, W - g * 2, H * 0.22 - g * 4, '#ffffff');
        ctx.save(); ctx.beginPath(); ctx.rect(g, H * 0.3 + g * 2, W - g * 2, H * 0.48); ctx.clip(); ctx.translate(W / 2, H * 0.55); ctx.rotate(e.still ? 0 : t * 0.08); for (let i = 0; i < 20; i++) { ctx.fillStyle = 'rgba(255,255,255,0.18)'; ctx.beginPath(); ctx.moveTo(0, 0); const a = (i / 20) * Math.PI * 2; ctx.arc(0, 0, W, a, a + 0.12); ctx.fill(); } ctx.restore();
        if (has(P.kicker)) { const bp = e.in(0, 0.5, E.outBack); ctx.save(); ctx.translate(W * 0.22, H * 0.065); ctx.rotate(-0.12); ctx.scale(bp, bp); ctx.fillStyle = pal.c; ctx.strokeStyle = pal.ink; ctx.lineWidth = u * 0.6; ctx.beginPath(); for (let i = 0; i < 24; i++) { const a = (i / 24) * Math.PI * 2, r = i % 2 ? u * 5.5 : u * 8; ctx.lineTo(Math.cos(a) * r * 1.9, Math.sin(a) * r); } ctx.closePath(); ctx.fill(); ctx.stroke(); ctx.restore(); ctx.save(); ctx.translate(W * 0.22, H * 0.065); ctx.rotate(-0.12); e.text(P.kicker, -u * 11, -u * 3.5, u * 22, u * 7, { font: F.comic, size: u * 5, color: pal.ink, align: 'center', valign: 'middle', upper: true, p: bp }); ctx.restore(); }
        e.text(P.headline, W * 0.06, H * 0.13, W * 0.88, H * 0.16, { font: F.comic, size: u * 15, color: '#ffffff', align: 'center', valign: 'bottom', upper: true, effect: 'pop', look: { stroke: pal.ink, strokeW: u * 1.6, shadow: pal.ink, shadowX: u * 0.8, shadowY: u * 0.8, hl: pal.bg, hlStyle: 'color' } });
        e.hero(W * 0.08, H * 0.38, W * 0.5, H * 0.39, { mood: 'excited', pose: 'point' });
        if (has(P.sub)) { const bx = W * 0.52, by = H * 0.38, bw = W * 0.42, bh = H * 0.2, p = e.in(0.6, 0.4, E.outBack); ctx.save(); ctx.translate(bx + bw / 2, by + bh / 2); ctx.scale(p, p); ctx.translate(-(bx + bw / 2), -(by + bh / 2)); ctx.fillStyle = '#fff'; ctx.strokeStyle = pal.ink; ctx.lineWidth = u * 0.7; ctx.beginPath(); ctx.ellipse(bx + bw / 2, by + bh / 2, bw / 2, bh / 2, 0, 0, 7); ctx.fill(); ctx.stroke(); ctx.beginPath(); ctx.moveTo(bx + bw * 0.2, by + bh * 0.85); ctx.lineTo(bx - u * 4, by + bh * 1.2); ctx.lineTo(bx + bw * 0.35, by + bh * 0.95); ctx.fill(); ctx.stroke(); e.text(P.sub, bx + bw * 0.14, by + bh * 0.16, bw * 0.72, bh * 0.68, { font: F.comic, size: u * 4.5, color: pal.ink, align: 'center', valign: 'middle', p: 1 }); ctx.restore(); }
        e.props([[0.78, 0.68, 14, 0.2]]);
        e.info(W * 0.06, H * 0.8 + g * 2, W * 0.66, { font: F.comic, size: u * 3.6, upper: true });
        e.qr(W - u * 18, H - u * 18, u * 13);
      },
    },
    bauhaus: {
      label: 'Bauhaus', tags: ['business', 'finance', 'any'], blurb: 'Primary geometry that rotates into place',
      palettes: [{ bg: '#efe6d5', a: '#d62828', b: '#003049', c: '#f77f00', d: '#fcbf49', ink: '#1d1d1d' }, { bg: '#f4f1ea', a: '#1d4ed8', b: '#111827', c: '#e11d48', d: '#facc15', ink: '#111827' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg();
        const cols = 3, rows = 3, cw = W / cols, ch = (H * 0.62) / rows, r = WE.rng((P.seed || 1) * 17), cs = [pal.a, pal.b, pal.c, pal.d];
        for (let i = 0; i < cols * rows; i++) {
          const x = (i % cols) * cw, y = Math.floor(i / cols) * ch, k = Math.floor(r() * 5), col = cs[Math.floor(r() * 4)], rot0 = Math.floor(r() * 4);
          const step = e.still ? 0 : Math.floor((t + i * 0.37) / 3), turn = e.still ? 0 : E.inOutCubic(clamp(((t + i * 0.37) % 3) - 2.4) / 0.6);
          const rot = (rot0 + step + turn) * (Math.PI / 2), p = e.in(i * 0.06, 0.6, E.outBack), s = Math.min(cw, ch);
          ctx.save(); ctx.translate(x + cw / 2, y + ch / 2); ctx.rotate(rot); ctx.scale(p, p); ctx.fillStyle = col;
          if (k === 0) { ctx.beginPath(); ctx.arc(0, 0, s * 0.42, 0, 7); ctx.fill(); }
          else if (k === 1) { ctx.beginPath(); ctx.moveTo(-s / 2, -s / 2); ctx.arc(-s / 2, -s / 2, s, 0, Math.PI / 2); ctx.closePath(); ctx.fill(); }
          else if (k === 2) { ctx.beginPath(); ctx.arc(0, s * 0.1, s * 0.45, Math.PI, 0); ctx.fill(); }
          else if (k === 3) { ctx.fillRect(-s * 0.4, -s * 0.4, s * 0.8, s * 0.8); ctx.fillStyle = pal.bg; ctx.beginPath(); ctx.arc(0, 0, s * 0.2, 0, 7); ctx.fill(); }
          else { ctx.beginPath(); ctx.moveTo(-s * 0.45, s * 0.4); ctx.lineTo(s * 0.45, s * 0.4); ctx.lineTo(0, -s * 0.42); ctx.closePath(); ctx.fill(); }
          ctx.restore();
        }
        if (P.photoImg) e.hero(cw, ch, cw, ch, { shape: 'circle', fx: 'duotone', duo: [pal.b, pal.bg] });
        ctx.fillStyle = pal.ink; ctx.fillRect(0, H * 0.62, W, u * 1.2);
        e.spaced(P.kicker, W * 0.06, H * 0.66, u * 2.8, F.ui, pal.a, 0.2, 'left', W * 0.8);
        const hl = e.text(P.headline ? String(P.headline).toLowerCase() : '', W * 0.06, H * 0.68, W * 0.88, H * 0.17, { font: F.grotesk, size: u * 13, color: pal.ink, lh: 0.92, hl: pal.a });
        e.text(P.sub, W * 0.06, hl ? hl.y + hl.h + u * 1.5 : H * 0.86, W * 0.6, H * 0.05, { font: F.ui, size: u * 3.3, color: pal.ink, start: 0.3 });
        e.info(W * 0.06, H * 0.92, W * 0.62, { inline: true, size: u * 2.8, font: F.mono, upper: true, perks: false });
        e.qr(W - u * 17, H - u * 17, u * 13, { fg: pal.ink, bg: pal.bg });
      },
    },
    brutal: {
      label: 'Neo-Brutalist', tags: ['party', 'tech', 'social'], blurb: 'Hard shadows, tape marquees, loud stickers',
      palettes: [{ bg: '#c6ff00', a: '#ff3ea5', b: '#ffffff', ink: '#000000' }, { bg: '#ff5a1f', a: '#1d4ed8', b: '#fff7e6', ink: '#000000' }, { bg: '#a5b4fc', a: '#facc15', b: '#ffffff', ink: '#000000' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg();
        const box = (x, y, w, h, fill) => { ctx.fillStyle = pal.ink; ctx.fillRect(x + u * 1.6, y + u * 1.6, w, h); ctx.fillStyle = fill; ctx.fillRect(x, y, w, h); ctx.strokeStyle = pal.ink; ctx.lineWidth = u * 0.7; ctx.strokeRect(x, y, w, h); };
        const tape = (y, rot, text, dir) => { ctx.save(); ctx.translate(W / 2, y); ctx.rotate(rot); ctx.fillStyle = pal.ink; ctx.fillRect(-W, -u * 3.4, W * 2, u * 6.8); ctx.font = `${u * 3.6}px ${F.mono}`; ctx.fillStyle = pal.a; ctx.textBaseline = 'middle'; const unit = (text + '  ✶  ').toUpperCase(); const uw = ctx.measureText(unit).width || 100; const off = e.still ? 0 : ((t * u * 12 * dir) % uw); for (let x = -W - uw + off; x < W; x += uw) ctx.fillText(unit, x, 0); ctx.restore(); };
        box(W * 0.06, H * 0.06, W * 0.88, H * 0.36, pal.b);
        e.spaced(P.kicker, W * 0.1, H * 0.09, u * 3, F.mono, pal.ink, 0.1, 'left', W * 0.8);
        e.text(P.headline, W * 0.1, H * 0.12, W * 0.8, H * 0.28, { font: F.grotesk, size: u * 14, color: pal.ink, upper: true, lh: 0.9, valign: 'middle', hl: pal.a, hlStyle: 'box', look: { hlText: pal.ink } });
        tape(H * 0.47, -0.05, has(P.kicker) ? P.kicker : (P.headline || ''), 1);
        box(W * 0.06, H * 0.54, W * 0.5, H * 0.32, '#ffffff');
        ctx.save(); ctx.beginPath(); ctx.rect(W * 0.06, H * 0.54, W * 0.5, H * 0.32); ctx.clip(); e.hero(W * 0.08, H * 0.56, W * 0.46, H * 0.3, { mood: 'wink', pose: 'thumbsUp' }); ctx.restore();
        box(W * 0.6, H * 0.54, W * 0.34, H * 0.32, pal.a);
        const lines = [P.date, P.time, P.venue].filter(has);
        e.text(lines.join('\n').toUpperCase(), W * 0.63, H * 0.56, W * 0.28, H * 0.15, { font: F.mono, size: u * 3.6, color: C.readable(pal.a, '#000', '#fff'), lh: 1.3 });
        tape(H * 0.9, 0.04, P.sub || P.cta || P.headline || '', -1);
        if (has(P.badge)) { ctx.save(); ctx.translate(W * 0.86, H * 0.08); ctx.rotate(0.3 + (e.still ? 0 : Math.sin(t * 2) * 0.05)); ctx.fillStyle = '#ffeb3b'; ctx.strokeStyle = pal.ink; ctx.lineWidth = u * 0.5; ctx.beginPath(); ctx.arc(0, 0, u * 8, 0, 7); ctx.fill(); ctx.stroke(); ctx.restore(); e.text(P.badge, W * 0.86 - u * 6.5, H * 0.08 - u * 4, u * 13, u * 8, { font: F.mono, size: u * 3.4, color: pal.ink, align: 'center', valign: 'middle', upper: true }); }
        e.qr(W * 0.77 - u * 6.5, H * 0.86 - u * 15, u * 13, { fg: '#000' });
      },
    },
    blueprint: {
      label: 'Blueprint', tags: ['finance', 'business', 'property', 'tech'], blurb: 'Technical drawing, dimension lines, title block',
      palettes: [{ bg: '#0e3b70', a: '#ffd166', b: '#7fd1ff', ink: '#ffffff' }, { bg: '#123524', a: '#f4d35e', b: '#9be564', ink: '#ecfff0' }, { bg: '#1f1147', a: '#ff9ff3', b: '#a29bfe', ink: '#ffffff' }],
      render(e) {
        const { ctx, W, H, u, P, pal } = e;
        e.bg();
        ctx.save(); ctx.strokeStyle = C.rgba(pal.ink, 0.08); ctx.lineWidth = 1;
        for (let x = 0; x < W; x += u * 2.5) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke(); }
        for (let y = 0; y < H; y += u * 2.5) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke(); }
        ctx.strokeStyle = C.rgba(pal.ink, 0.18);
        for (let x = 0; x < W; x += u * 12.5) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke(); }
        for (let y = 0; y < H; y += u * 12.5) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke(); }
        ctx.restore();
        ctx.strokeStyle = pal.ink; ctx.lineWidth = u * 0.35; ctx.strokeRect(u * 3, u * 3, W - u * 6, H - u * 6);
        const prop = (P.props && P.props[0]) || 'house';
        const pp = e.in(0.3, 2, E.inOutSine);
        e.prop(prop, W * 0.62, H * 0.42, Math.min(W, H) * 0.42, { mode: 'line', ink: pal.ink, p: 1, progress: pp, lw: u * 0.35 });
        const dim = (x1, y1, x2, y2, label) => { ctx.save(); ctx.strokeStyle = pal.b; ctx.fillStyle = pal.b; ctx.lineWidth = u * 0.22; ctx.setLineDash([u, u * 0.6]); ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke(); ctx.setLineDash([]); [[x1, y1], [x2, y2]].forEach(([x, y]) => { ctx.beginPath(); ctx.arc(x, y, u * 0.6, 0, 7); ctx.fill(); }); ctx.font = `${u * 2.2}px ${F.mono}`; ctx.textAlign = 'center'; ctx.fillText(label, (x1 + x2) / 2, (y1 + y2) / 2 - u * 1.2); ctx.restore(); };
        if (pp > 0.6) { dim(W * 0.41, H * 0.66, W * 0.83, H * 0.66, (P.time || 'SCALE 1:1').toUpperCase()); dim(W * 0.88, H * 0.2, W * 0.88, H * 0.63, ''); }
        e.spaced(P.kicker ? 'FIG. 01 — ' + String(P.kicker).toUpperCase() : 'FIG. 01', W * 0.08, H * 0.07, u * 2.4, F.mono, pal.b, 0.12, 'left', W * 0.8);
        e.text(P.headline, W * 0.08, H * 0.1, W * 0.5, H * 0.32, { font: F.grotesk, size: u * 11, color: pal.ink, lh: 0.98, hl: pal.a });
        e.text(P.sub, W * 0.08, H * 0.45, W * 0.32, H * 0.15, { font: F.mono, size: u * 2.8, color: C.rgba(pal.ink, 0.85), lh: 1.3, start: 0.4 });
        const tb = { x: W * 0.08, y: H * 0.72, w: W * 0.84, h: H * 0.2 };
        ctx.strokeStyle = pal.ink; ctx.lineWidth = u * 0.3; ctx.strokeRect(tb.x, tb.y, tb.w, tb.h);
        const cells = [['PROJECT', (P.brand && P.brand.name) || P.kicker], ['DATE', P.date], ['LOCATION', P.venue], ['NOTES', (P.details || []).filter(has).join(' · ')]];
        cells.forEach(([k, v], i) => { const cx = tb.x + (i % 2) * (tb.w * 0.36), cy = tb.y + Math.floor(i / 2) * (tb.h / 2), cw = i % 2 ? tb.w * 0.36 : tb.w * 0.36; ctx.strokeRect(cx, cy, cw, tb.h / 2); e.spaced(k, cx + u * 1.5, cy + u * 2.2, u * 1.8, F.mono, pal.b, 0.1); e.text(v || '—', cx + u * 1.5, cy + u * 3.8, cw - u * 3, tb.h / 2 - u * 5, { font: F.mono, size: u * 3, color: pal.ink, start: 0.5 + i * 0.1 }); });
        e.qr(tb.x + tb.w - tb.h + u * 1.5, tb.y + u * 1.5, tb.h - u * 3, { fg: pal.bg, bg: pal.ink });
      },
    },
    ticket: {
      label: 'Golden Ticket', tags: ['party', 'family', 'event'], blurb: 'Die-cut ticket with stub, barcode and a shine sweep',
      palettes: [{ bg: '#1b1b2f', a: '#ffd166', b: '#ef476f', c: '#06d6a0', ink: '#1b1b2f' }, { bg: '#0f172a', a: '#f9a8d4', b: '#a78bfa', c: '#67e8f9', ink: '#1e1b4b' }, { bg: '#2b1b17', a: '#f4d58d', b: '#bf0603', c: '#708d81', ink: '#2b1b17' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg(); ctx.save(); ctx.globalAlpha = 0.25; ctx.fillStyle = WE.texture(ctx, 'dots', pal.a); ctx.fillRect(0, 0, W, H); ctx.restore();
        const tw = W * 0.8, th = H * 0.8, tx = (W - tw) / 2, ty = (H - th) / 2, stubY = ty + th * 0.72, nr = u * 4, p = e.in(0, 0.9, E.outBack);
        ctx.save(); ctx.translate(W / 2, H / 2); ctx.rotate(-0.04 * p); ctx.scale(p, p); ctx.translate(-W / 2, -H / 2);
        const shape = () => { ctx.beginPath(); ctx.moveTo(tx + u * 3, ty); ctx.lineTo(tx + tw - u * 3, ty); ctx.quadraticCurveTo(tx + tw, ty, tx + tw, ty + u * 3); ctx.lineTo(tx + tw, stubY - nr); ctx.arc(tx + tw, stubY, nr, -Math.PI / 2, Math.PI / 2, true); ctx.lineTo(tx + tw, ty + th - u * 3); ctx.quadraticCurveTo(tx + tw, ty + th, tx + tw - u * 3, ty + th); ctx.lineTo(tx + u * 3, ty + th); ctx.quadraticCurveTo(tx, ty + th, tx, ty + th - u * 3); ctx.lineTo(tx, stubY + nr); ctx.arc(tx, stubY, nr, Math.PI / 2, -Math.PI / 2, true); ctx.lineTo(tx, ty + u * 3); ctx.quadraticCurveTo(tx, ty, tx + u * 3, ty); ctx.closePath(); };
        ctx.save(); ctx.shadowColor = 'rgba(0,0,0,0.5)'; ctx.shadowBlur = u * 4; ctx.shadowOffsetY = u * 2; const g = ctx.createLinearGradient(tx, ty, tx + tw, ty + th); g.addColorStop(0, pal.a); g.addColorStop(1, C.mix(pal.a, pal.b, 0.45)); ctx.fillStyle = g; shape(); ctx.fill(); ctx.restore();
        ctx.save(); shape(); ctx.clip();
        if (!e.still) { const sx = tx - tw + ((t * 0.35) % 1.6) * tw * 2.2; const sg = ctx.createLinearGradient(sx, ty, sx + tw * 0.3, ty + th * 0.2); sg.addColorStop(0, 'rgba(255,255,255,0)'); sg.addColorStop(0.5, 'rgba(255,255,255,0.45)'); sg.addColorStop(1, 'rgba(255,255,255,0)'); ctx.fillStyle = sg; ctx.fillRect(tx, ty, tw, th); }
        ctx.strokeStyle = C.rgba(pal.ink, 0.5); ctx.setLineDash([u * 1.2, u * 1]); ctx.lineWidth = u * 0.35; ctx.beginPath(); ctx.moveTo(tx + nr, stubY); ctx.lineTo(tx + tw - nr, stubY); ctx.stroke(); ctx.setLineDash([]);
        ctx.strokeStyle = C.rgba(pal.ink, 0.35); ctx.lineWidth = u * 0.25; WE.roundRect(ctx, tx + u * 2.5, ty + u * 2.5, tw - u * 5, stubY - ty - u * 5, u * 2); ctx.stroke();
        ctx.restore();
        e.spaced('ADMIT ONE', W / 2, ty + u * 7, u * 2.8, F.mono, pal.ink, 0.5, 'center');
        e.text(P.kicker, tx + u * 6, ty + u * 10, tw - u * 12, u * 7, { font: F.script, size: u * 6, color: pal.ink, align: 'center' });
        const hl = e.text(P.headline, tx + u * 6, ty + u * 18, tw - u * 12, th * 0.3, { font: F.condensed, size: u * 16, color: pal.ink, align: 'center', upper: true, lh: 0.92, hl: pal.b });
        e.text(P.sub, tx + u * 8, hl ? hl.y + hl.h + u * 2 : ty + th * 0.45, tw - u * 16, u * 8, { font: F.ui, size: u * 3.4, color: pal.ink, align: 'center', start: 0.4 });
        e.hero(W / 2 - tw * 0.18, stubY - th * 0.3, tw * 0.36, th * 0.27, { mood: 'happy', pose: 'present' });
        e.info(tx + u * 6, stubY + u * 4, tw * 0.55, { font: F.mono, size: u * 2.8, upper: true, color: pal.ink });
        if (!e.qr(tx + tw - u * 22, stubY + u * 3, u * 17, { fg: pal.ink })) { const r = WE.rng(9); ctx.fillStyle = pal.ink; for (let x = 0; x < u * 18; x += u * 0.5) if (r() > 0.35) ctx.fillRect(tx + tw - u * 24 + x, stubY + u * 5, u * (0.12 + r() * 0.3), u * 12); }
        ctx.restore();
      },
    },
    chalk: {
      label: 'Chalkboard', tags: ['family', 'care', 'education'], blurb: 'Hand-chalked lettering, doodles and a wooden frame',
      palettes: [{ bg: '#24392f', a: '#ffd6e0', b: '#bde0fe', c: '#fff3b0', ink: '#f1f1ea' }, { bg: '#22262a', a: '#ffadad', b: '#9bf6ff', c: '#fdffb6', ink: '#f5f5f0' }],
      render(e) {
        const { ctx, W, H, u, P, pal } = e;
        e.bg();
        const vg = ctx.createRadialGradient(W / 2, H / 2, 0, W / 2, H / 2, Math.max(W, H) * 0.7); vg.addColorStop(0, 'rgba(255,255,255,0.06)'); vg.addColorStop(1, 'rgba(0,0,0,0.35)'); ctx.fillStyle = vg; ctx.fillRect(0, 0, W, H);
        ctx.save(); ctx.globalAlpha = 0.5; ctx.fillStyle = WE.texture(ctx, 'grain'); ctx.fillRect(0, 0, W, H); ctx.restore();
        const fw = u * 3.4; ctx.save(); ctx.strokeStyle = '#8b5a2b'; ctx.lineWidth = fw * 2; ctx.strokeRect(0, 0, W, H); ctx.strokeStyle = 'rgba(0,0,0,0.25)'; ctx.lineWidth = u * 0.3; ctx.strokeRect(fw, fw, W - fw * 2, H - fw * 2); ctx.restore();
        const chalk = (lay) => lay;
        const look = { shadow: 'rgba(255,255,255,0.25)', shadowBlur: u * 0.5 };
        e.text(P.kicker, W * 0.1, H * 0.06, W * 0.8, H * 0.06, { font: F.hand, size: u * 6, color: pal.c, align: 'center', effect: e.still ? 'none' : 'hand', look });
        const hl = chalk(e.text(P.headline, W * 0.08, H * 0.12, W * 0.84, H * 0.26, { font: F.hand, size: u * 15, color: pal.ink, align: 'center', lh: 0.95, effect: e.still ? 'none' : 'hand', dur: 1.8, hl: pal.a, hlStyle: 'underline', look }));
        if (hl) { ctx.save(); ctx.strokeStyle = pal.b; ctx.lineWidth = u * 0.6; ctx.lineCap = 'round'; ctx.beginPath(); const y = hl.y + hl.h + u; ctx.moveTo(W * 0.3, y); ctx.quadraticCurveTo(W / 2, y + u * 2, W * 0.7, y - u * 0.5); ctx.stroke(); ctx.restore(); }
        e.text(P.sub, W * 0.12, H * 0.42, W * 0.76, H * 0.08, { font: F.hand, size: u * 6, color: pal.b, align: 'center', start: 1.2, effect: e.still ? 'none' : 'hand' });
        e.hero(W * 0.3, H * 0.5, W * 0.4, H * 0.3, { mode: 'chalk', ink: '#f1f1ea', pal: pal, lw: u * 0.45, mood: 'happy', pose: 'wave' });
        e.props([[0.17, 0.62, 13], [0.83, 0.62, 13]], { mode: 'chalk', ink: '#f1f1ea' });
        e.info(W * 0.1, H * 0.83, W * 0.8, { font: F.hand, size: u * 5, align: 'center', color: pal.ink, perkColor: pal.c });
        e.qr(W - u * 18, H - u * 18, u * 12, { fg: '#24392f', bg: '#f1f1ea' });
        ctx.save(); ctx.globalCompositeOperation = 'destination-out'; ctx.globalAlpha = 0.35; ctx.fillStyle = WE.texture(ctx, 'grain'); ctx.fillRect(fw, fw, W - fw * 2, H - fw * 2); ctx.restore();
        ctx.save(); ctx.globalCompositeOperation = 'destination-over'; e.bg(); ctx.restore();
      },
    },
    kinetic: {
      label: 'Kinetic Type', tags: ['party', 'tech', 'social'], blurb: 'Your headline as a scrolling wall of type',
      palettes: [{ bg: '#0a0a0a', a: '#ff4d00', ink: '#f5f5f5' }, { bg: '#f5f5f0', a: '#2d00f7', ink: '#0a0a0a' }, { bg: '#1a0033', a: '#00ffa3', ink: '#ffffff' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg();
        const word = String(P.headline || P.kicker || '').replace(/\*/g, '').toUpperCase();
        if (word) {
          const rows = 9, rh = (H * 0.72) / rows;
          ctx.save(); ctx.font = `${rh * 1.02}px ${F.condensed}`; ctx.textBaseline = 'middle';
          const unit = word + '  ', uw = ctx.measureText(unit).width || 100;
          for (let r = 0; r < rows; r++) {
            const y = rh * (r + 0.5) + u * 2, dir = r % 2 ? 1 : -1, speed = e.still ? 0 : t * u * (8 + (r % 3) * 4) * dir, off = ((speed % uw) + uw) % uw;
            const solid = r === Math.floor(rows / 2), p = e.in(r * 0.05, 0.6);
            ctx.globalAlpha = p;
            for (let x = -uw + off - (r * uw) / 3; x < W + uw; x += uw) { if (solid) { ctx.fillStyle = pal.a; ctx.fillText(unit, x, y); } else { ctx.strokeStyle = C.rgba(pal.ink, 0.55); ctx.lineWidth = u * 0.25; ctx.strokeText(unit, x, y); } }
          }
          ctx.restore();
        }
        ctx.fillStyle = pal.ink; ctx.fillRect(0, H * 0.76, W, H * 0.24);
        e.text(P.sub || P.kicker, W * 0.06, H * 0.78, W * 0.62, H * 0.09, { font: F.grotesk, size: u * 6, color: pal.bg });
        e.info(W * 0.06, H * 0.88, W * 0.62, { font: F.mono, size: u * 2.8, color: pal.bg, upper: true, inline: true });
        e.qr(W - u * 19, H * 0.78 + u * 2, u * 15, { fg: pal.ink === '#0a0a0a' ? '#0a0a0a' : '#000', bg: pal.bg === '#0a0a0a' ? '#ffffff' : pal.bg });
      },
    },
    sticker: {
      label: 'Sticker Bomb', tags: ['party', 'family', 'social'], blurb: 'Die-cut stickers everywhere, playful & loud',
      palettes: [{ bg: '#ffd6e8', a: '#ff4fa3', b: '#7b61ff', c: '#00c2a8', d: '#ffbe0b', ink: '#1b1b1b' }, { bg: '#d8f3dc', a: '#2d6a4f', b: '#ff6b6b', c: '#4ea8de', d: '#ffd60a', ink: '#081c15' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg(); ctx.save(); ctx.globalAlpha = 0.5; ctx.fillStyle = WE.texture(ctx, 'dots', C.shade(pal.bg, -0.2)); ctx.fillRect(0, 0, W, H); ctx.restore();
        const sticker = (draw, x, y, rot, i) => { const p = e.in(0.1 + i * 0.08, 0.5, E.outBack), wob = e.still ? 0 : Math.sin(t * 2 + i) * 0.04; ctx.save(); ctx.translate(x, y); ctx.rotate(rot + wob); ctx.scale(p, p); ctx.shadowColor = 'rgba(0,0,0,0.25)'; ctx.shadowBlur = u * 1.5; ctx.shadowOffsetY = u * 0.8; draw(); ctx.restore(); };
        const names = (P.props && P.props.length ? P.props : ['star', 'heart', 'balloon', 'gift', 'sparkle', 'music']);
        const spots = [[0.12, 0.18, -0.3], [0.88, 0.14, 0.25], [0.1, 0.72, 0.2], [0.9, 0.66, -0.2], [0.2, 0.92, 0.1], [0.84, 0.9, -0.1]];
        spots.forEach((s, i) => sticker(() => { ctx.fillStyle = '#ffffff'; ctx.beginPath(); ctx.arc(0, 0, u * 9, 0, 7); ctx.fill(); ctx.shadowColor = 'transparent'; WE.Cast.drawProp(ctx, names[i % names.length], 0, 0, u * 13, { mode: 'cartoon', pal: Object.assign({ ink: pal.ink, l: '#fff', d: pal.ink }, pal), lw: u * 0.45 }); }, W * s[0], H * s[1], s[2], i));
        sticker(() => { e.hero(-W * 0.2, -H * 0.15, W * 0.4, H * 0.26, { mood: 'wink', pose: 'celebrate' }); }, W / 2, H * 0.68, 0, 3);
        sticker(() => { const w = W * 0.82, h = H * 0.26; ctx.fillStyle = '#ffffff'; WE.roundRect(ctx, -w / 2 - u * 1.6, -h / 2 - u * 1.6, w + u * 3.2, h + u * 3.2, u * 6); ctx.fill(); ctx.shadowColor = 'transparent'; ctx.fillStyle = pal.a; WE.roundRect(ctx, -w / 2, -h / 2, w, h, u * 5); ctx.fill(); e.text(P.headline, -w / 2 + u * 4, -h / 2 + u * 3, w - u * 8, h - u * 6, { font: F.comic, size: u * 13, color: '#ffffff', align: 'center', valign: 'middle', upper: true, p: 1, look: { stroke: pal.ink, strokeW: u * 1.1, hl: pal.d } }); }, W / 2, H * 0.24, -0.05, 0);
        if (has(P.kicker)) sticker(() => e.pill(P.kicker, 0, 0, { bg: pal.d, fg: pal.ink, font: F.comic, size: u * 3.6, border: '#ffffff', borderW: u * 1.2 }), W * 0.5, H * 0.07, 0.04, 1);
        const info = joinInfo(P).join(' • ');
        if (info) sticker(() => e.pill(info, 0, 0, { bg: pal.b, fg: '#ffffff', font: F.round, size: u * 3.4, border: '#ffffff', borderW: u * 1.2, maxW: W * 0.8 }), W / 2, H * 0.43, -0.02, 2);
        if (has(P.sub)) e.text(P.sub, W * 0.12, H * 0.47, W * 0.76, H * 0.05, { font: F.round, size: u * 3.8, color: pal.ink, align: 'center', start: 0.4 });
        e.qr(W / 2 - u * 7, H - u * 17, u * 14, { fg: pal.ink });
      },
    },
    score: {
      label: 'Score Glow', tags: ['finance', 'property', 'tech'], blurb: 'Animated gradient gauge with a counting score',
      palettes: [{ bg: '#0b1220', a: '#22c55e', b: '#06b6d4', c: '#a3e635', ink: '#ffffff' }, { bg: '#f6f7fb', a: '#6366f1', b: '#ec4899', c: '#f59e0b', ink: '#0b1220' }, { bg: '#140c26', a: '#f472b6', b: '#a78bfa', c: '#fde047', ink: '#ffffff' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg();
        [[pal.a, 0.2, 0.2], [pal.b, 0.85, 0.75]].forEach(([c, fx, fy]) => { const g = ctx.createRadialGradient(W * fx, H * fy, 0, W * fx, H * fy, Math.max(W, H) * 0.5); g.addColorStop(0, C.rgba(c, 0.3)); g.addColorStop(1, C.rgba(c, 0)); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H); });
        const max = parseFloat(P.scoreMax) || 1000, val = clamp((parseFloat(P.score) || 0) / max), p = e.in(0.3, 1.8, E.outCubic);
        const cx = W / 2, cy = H * 0.42, R = Math.min(W, H) * 0.3, a0 = Math.PI * 0.75, span = Math.PI * 1.5;
        ctx.save(); ctx.lineCap = 'round'; ctx.lineWidth = R * 0.16; ctx.strokeStyle = C.rgba(pal.ink, 0.1); ctx.beginPath(); ctx.arc(cx, cy, R, a0, a0 + span); ctx.stroke();
        const segs = ['#f97316', '#facc15', '#84cc16', '#22c55e', '#15803d'];
        segs.forEach((c, i) => { const s0 = a0 + (span / 5) * i + 0.03, s1 = a0 + (span / 5) * (i + 1) - 0.03, lim = a0 + span * val * p; if (lim <= s0) return; ctx.strokeStyle = c; ctx.shadowColor = c; ctx.shadowBlur = R * 0.12; ctx.beginPath(); ctx.arc(cx, cy, R, s0, Math.min(s1, lim)); ctx.stroke(); });
        const ma = a0 + span * val * p; ctx.shadowBlur = 0; ctx.fillStyle = '#ffffff'; ctx.strokeStyle = pal.bg; ctx.lineWidth = R * 0.04; ctx.beginPath(); ctx.arc(cx + Math.cos(ma) * R, cy + Math.sin(ma) * R, R * 0.1, 0, 7); ctx.fill(); ctx.stroke();
        ctx.restore();
        if (has(P.score)) { ctx.save(); ctx.fillStyle = pal.ink; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.font = `${R * 0.6}px ${F.grotesk}`; ctx.fillText(Math.round((parseFloat(P.score) || 0) * p).toLocaleString('en-GB'), cx, cy - R * 0.02); ctx.font = `${R * 0.12}px ${F.ui}`; ctx.fillStyle = C.rgba(pal.ink, 0.7); ctx.fillText('out of ' + max.toLocaleString('en-GB'), cx, cy + R * 0.36); ctx.restore(); }
        e.spaced(P.kicker, W / 2, H * 0.06, u * 2.8, F.ui, pal.a, 0.25, 'center', W * 0.84);
        e.text(P.headline, W * 0.08, H * 0.67, W * 0.84, H * 0.11, { font: F.grotesk, size: u * 8, color: pal.ink, align: 'center', hl: pal.a, start: 0.6 });
        const chips = (P.details || []).filter(has).slice(0, 3);
        chips.forEach((c, i) => e.pill(c, W * (0.5 + (i - (chips.length - 1) / 2) * 0.3), H * 0.86, { bg: C.rgba(pal.a, 0.18), fg: pal.ink, border: C.rgba(pal.a, 0.6), size: u * 2.6, maxW: W * 0.28 }));
        e.text(P.sub, W * 0.1, H * 0.79, W * 0.8, u * 4, { font: F.ui, size: u * 3, color: C.rgba(pal.ink, 0.75), align: 'center', start: 0.8 });
        e.qr(W - u * 17, H - u * 17, u * 13, { fg: '#0b1220' });
      },
    },
    scrapbook: {
      label: 'Family Scrapbook', tags: ['family', 'care'], blurb: 'Polaroids, washi tape and handwritten notes',
      palettes: [{ bg: '#f5ebe0', a: '#e76f51', b: '#2a9d8f', c: '#e9c46a', ink: '#3d2c1e' }, { bg: '#fdf0f5', a: '#d6336c', b: '#5c7cfa', c: '#ffd43b', ink: '#2b2d42' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg(); e.grain(1, 'paper');
        const pol = (x, y, w, rot, i, inner) => { const p = e.in(0.1 + i * 0.15, 0.6, E.outBack); ctx.save(); ctx.translate(x, y); ctx.rotate(rot + (e.still ? 0 : Math.sin(t + i) * 0.01)); ctx.scale(p, p); ctx.shadowColor = 'rgba(60,40,20,0.3)'; ctx.shadowBlur = u * 2; ctx.shadowOffsetY = u; ctx.fillStyle = '#fffdf8'; ctx.fillRect(-w / 2, -w / 2, w, w * 1.18); ctx.shadowColor = 'transparent'; ctx.fillStyle = C.shade(pal.b, 0.6); ctx.fillRect(-w / 2 + w * 0.06, -w / 2 + w * 0.06, w * 0.88, w * 0.88); ctx.save(); ctx.beginPath(); ctx.rect(-w / 2 + w * 0.06, -w / 2 + w * 0.06, w * 0.88, w * 0.88); ctx.clip(); inner(-w / 2 + w * 0.06, -w / 2 + w * 0.06, w * 0.88); ctx.restore(); ctx.fillStyle = 'rgba(255,230,160,0.7)'; ctx.save(); ctx.rotate(-0.1); ctx.fillRect(-w * 0.2, -w / 2 - u * 1.5, w * 0.4, u * 3.4); ctx.restore(); ctx.restore(); };
        const pw = Math.min(W * 0.5, H * 0.32);
        pol(W * 0.33, H * 0.43, pw, -0.08, 0, (x, y, s) => e.hero(x, y, s, s, { mood: 'happy', pose: 'wave' }));
        pol(W * 0.73, H * 0.37, pw * 0.75, 0.1, 1, (x, y, s) => { ctx.fillStyle = C.shade(pal.c, 0.4); ctx.fillRect(x, y, s, s); WE.Cast.drawProp(ctx, (P.props && P.props[0]) || 'tree', x + s / 2, y + s / 2, s * 0.8, { mode: 'cartoon', pal: Object.assign({ ink: pal.ink, l: '#fff', d: pal.ink }, pal), lw: u * 0.4 }); });
        pol(W * 0.75, H * 0.62, pw * 0.6, -0.05, 2, (x, y, s) => { ctx.fillStyle = C.shade(pal.a, 0.5); ctx.fillRect(x, y, s, s); WE.Cast.drawProp(ctx, (P.props && P.props[1]) || 'heart', x + s / 2, y + s / 2, s * 0.75, { mode: 'cartoon', pal: Object.assign({ ink: pal.ink, l: '#fff', d: pal.ink }, pal), lw: u * 0.4 }); });
        e.text(P.kicker, W * 0.06, H * 0.04, W * 0.88, H * 0.06, { font: F.hand, size: u * 6, color: pal.a, align: 'center' });
        e.text(P.headline, W * 0.06, H * 0.08, W * 0.88, H * 0.16, { font: F.hand, size: u * 14, color: pal.ink, align: 'center', lh: 0.95, hl: pal.a, hlStyle: 'marker', effect: e.still ? 'none' : 'hand', dur: 1.6 });
        const nx = W * 0.08, ny = H * 0.68, nw = W * 0.56, nh = H * 0.27;
        ctx.save(); ctx.translate(nx + nw / 2, ny + nh / 2); ctx.rotate(-0.02); ctx.translate(-(nx + nw / 2), -(ny + nh / 2)); ctx.shadowColor = 'rgba(60,40,20,0.2)'; ctx.shadowBlur = u; ctx.fillStyle = '#fffdf2'; ctx.fillRect(nx, ny, nw, nh); ctx.shadowColor = 'transparent'; ctx.strokeStyle = C.rgba(pal.b, 0.25); for (let y = ny + u * 5; y < ny + nh; y += u * 4.4) { ctx.beginPath(); ctx.moveTo(nx, y); ctx.lineTo(nx + nw, y); ctx.stroke(); }
        const sl = e.text(P.sub, nx + u * 3, ny + u * 1.8, nw - u * 6, nh * 0.35, { font: F.hand, size: u * 5, color: pal.ink });
        e.info(nx + u * 3, sl ? sl.y + sl.h + u : ny + u * 3, nw - u * 6, { font: F.hand, size: u * 4.4, color: pal.b });
        ctx.restore();
        e.qr(W * 0.72, H * 0.8, u * 15, { fg: pal.ink });
      },
    },
    cinema: {
      label: 'Movie Poster', tags: ['any', 'party', 'business'], blurb: 'Rim-lit hero, god-rays, billing block',
      palettes: [{ bg: '#05070d', a: '#ff9f1c', b: '#2ec4b6', ink: '#f5f5f5' }, { bg: '#0d0208', a: '#e63946', b: '#a8dadc', ink: '#f1faee' }, { bg: '#020c1b', a: '#64ffda', b: '#bd93f9', ink: '#e6f1ff' }],
      render(e) {
        const { ctx, W, H, u, t, P, pal } = e;
        e.bg();
        ctx.save(); ctx.globalCompositeOperation = 'lighter';
        for (let i = 0; i < 5; i++) { const a = -Math.PI / 2 + (i - 2) * 0.16 + (e.still ? 0 : Math.sin(t * 0.3 + i) * 0.03); ctx.fillStyle = C.rgba(pal.a, 0.06); ctx.beginPath(); ctx.moveTo(W / 2, -H * 0.05); ctx.lineTo(W / 2 + Math.cos(a - 0.05) * H * 1.4, Math.sin(-a - 0.05) * H * 1.4); ctx.lineTo(W / 2 + Math.cos(a + 0.05) * H * 1.4, Math.sin(-a + 0.05) * H * 1.4); ctx.fill(); }
        ctx.restore();
        const rim = ctx.createRadialGradient(W / 2, H * 0.45, 0, W / 2, H * 0.45, W * 0.6); rim.addColorStop(0, C.rgba(pal.b, 0.35)); rim.addColorStop(1, C.rgba(pal.b, 0)); ctx.fillStyle = rim; ctx.fillRect(0, 0, W, H);
        e.hero(W * 0.18, H * 0.16, W * 0.64, H * 0.58, { anchorTop: true, mood: 'proud', pose: 'present', shape: 'rect' });
        const fog = ctx.createLinearGradient(0, H * 0.5, 0, H); fog.addColorStop(0, C.rgba(pal.bg, 0)); fog.addColorStop(0.45, C.rgba(pal.bg, 0.92)); fog.addColorStop(1, pal.bg); ctx.fillStyle = fog; ctx.fillRect(0, H * 0.5, W, H * 0.5);
        e.spaced(P.kicker ? String(P.kicker).toUpperCase() : '', W / 2, H * 0.05, u * 2.6, F.ui, C.rgba(pal.ink, 0.85), 0.45, 'center', W * 0.86);
        e.text(P.sub, W * 0.1, H * 0.6, W * 0.8, H * 0.06, { font: F.serif, size: u * 3.6, color: pal.a, align: 'center', start: 0.4 });
        e.text(P.headline, W * 0.05, H * 0.66, W * 0.9, H * 0.15, { font: F.condensed, size: u * 17, color: pal.ink, align: 'center', valign: 'middle', upper: true, lh: 0.92, look: { gradient: ['#ffffff', C.shade(pal.a, 0.3)], shadow: C.rgba(pal.a, 0.6), shadowBlur: u * 3, hl: pal.a } });
        const billing = [...(P.details || []), P.venue].filter(has).join('  ').toUpperCase();
        if (billing) { ctx.save(); ctx.translate(W / 2, H * 0.86); ctx.scale(0.7, 1); e.spaced(billing, 0, 0, u * 2.4, F.condensed, C.rgba(pal.ink, 0.7), 0.06, 'center', W * 1.2); ctx.restore(); }
        e.spaced([P.date, P.time].filter(has).join('  ·  ').toUpperCase(), W / 2, H * 0.91, u * 3.6, F.condensed, pal.ink, 0.25, 'center', W * 0.8);
        e.qr(W - u * 15, H - u * 15, u * 11, { fg: '#05070d' });
      },
    },
  };

  /** Render a poster frame. o: { still } */
  const render = (ctx, P, W, H, t = 0, o = {}) => {
    const e = makeEnv(ctx, P, W, H, t, o);
    ctx.save();
    try { (TEMPLATES[P.template] || TEMPLATES.riso).render(e); } catch (err) { if (root.console) console.warn('[WorldEngine poster]', err); }
    ctx.restore();
    return e;
  };
  /** Fields that still hold the blueprint's sample text — warn before export. */
  const sampleWarnings = (P, sample) => (sample ? ['headline', 'kicker', 'sub', 'date', 'time', 'venue', 'url'].filter((k) => has(P[k]) && P[k] === sample[k]) : []);

  WE.Poster = { TEMPLATES, SIZES, render, sampleWarnings, has };
})(typeof window !== 'undefined' ? window : globalThis);
