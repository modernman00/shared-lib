/* ==========================================================================
 * ATG World Engine — 01 Core
 * Maths, easing, colour, fonts, hand-drawn "ink" renderer, kinetic text, QR.
 * Zero dependencies. Shared by the Explainer Studio and the Poster Studio.
 * ========================================================================== */
(function (root) {
  'use strict';
  const WE = (root.WorldEngine = root.WorldEngine || {});
  WE.version = '1.0.0';

  /* ---------------------------------------------------------------- maths */
  const hashStr = (s) => {
    let h = 2166136261;
    for (let i = 0; i < s.length; i++) { h ^= s.charCodeAt(i); h = Math.imul(h, 16777619); }
    return h >>> 0;
  };
  WE.hash = hashStr;
  WE.rng = (seed) => {
    let a = (typeof seed === 'string' ? hashStr(seed) : (seed >>> 0)) || 1;
    return () => {
      a = (a + 0x6d2b79f5) | 0;
      let t = Math.imul(a ^ (a >>> 15), 1 | a);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  };
  const clamp = (v, a = 0, b = 1) => (v < a ? a : v > b ? b : v);
  const lerp = (a, b, t) => a + (b - a) * t;
  const range = (t, a, b) => (b <= a ? (t >= b ? 1 : 0) : clamp((t - a) / (b - a)));
  WE.M = { clamp, lerp, range, TAU: Math.PI * 2 };

  const c1 = 1.70158, c3 = c1 + 1;
  WE.Ease = {
    linear: (t) => t,
    inQuad: (t) => t * t,
    outQuad: (t) => 1 - (1 - t) * (1 - t),
    inOutQuad: (t) => (t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2),
    outCubic: (t) => 1 - Math.pow(1 - t, 3),
    inCubic: (t) => t * t * t,
    inOutCubic: (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2),
    outBack: (t) => 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2),
    outElastic: (t) => (t === 0 || t === 1 ? t : Math.pow(2, -10 * t) * Math.sin((t * 10 - 0.75) * ((2 * Math.PI) / 3)) + 1),
    outBounce: (t) => {
      const n = 7.5625, d = 2.75;
      if (t < 1 / d) return n * t * t;
      if (t < 2 / d) return n * (t -= 1.5 / d) * t + 0.75;
      if (t < 2.5 / d) return n * (t -= 2.25 / d) * t + 0.9375;
      return n * (t -= 2.625 / d) * t + 0.984375;
    },
    inOutSine: (t) => -(Math.cos(Math.PI * t) - 1) / 2,
    outExpo: (t) => (t === 1 ? 1 : 1 - Math.pow(2, -10 * t)),
  };

  /* --------------------------------------------------------------- colour */
  const hexToRgb = (hex) => {
    let h = String(hex || '#000').replace('#', '');
    if (h.length === 3) h = h.split('').map((c) => c + c).join('');
    const n = parseInt(h.slice(0, 6), 16) || 0;
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  };
  const toHex = (r, g, b) => '#' + [r, g, b].map((v) => Math.round(clamp(v, 0, 255)).toString(16).padStart(2, '0')).join('');
  WE.Color = {
    hexToRgb,
    toHex,
    rgba: (hex, a) => { const [r, g, b] = hexToRgb(hex); return `rgba(${r},${g},${b},${a})`; },
    mix: (a, b, t) => { const x = hexToRgb(a), y = hexToRgb(b); return toHex(lerp(x[0], y[0], t), lerp(x[1], y[1], t), lerp(x[2], y[2], t)); },
    shade: (hex, amt) => (amt >= 0 ? WE.Color.mix(hex, '#ffffff', amt) : WE.Color.mix(hex, '#000000', -amt)),
    lum: (hex) => { const [r, g, b] = hexToRgb(hex).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; },
    readable: (bg, dark = '#111111', light = '#ffffff') => (WE.Color.lum(bg) > 0.42 ? dark : light),
    isHex: (s) => /^#?[0-9a-f]{3}([0-9a-f]{3})?$/i.test(String(s || '').trim()),
  };

  /* ---------------------------------------------------------------- fonts */
  WE.FONT_FILES = {
    caveat: ['WE Caveat', '700'], fredoka: ['WE Fredoka', '600'], luckiest: ['WE Luckiest', '400'], anton: ['WE Anton', '400'],
    bricolage: ['WE Bricolage', '800'], fraunces: ['WE Fraunces', '800'], spacemono: ['WE Space Mono', '700'], monoton: ['WE Monoton', '400'],
    shrikhand: ['WE Shrikhand', '400'], bungee: ['WE Bungee', '400'], marker: ['WE Marker', '400'], dmserif: ['WE DM Serif', '400'],
    syne: ['WE Syne', '800'], pacifico: ['WE Pacifico', '400'], inter: ['WE Inter', '500'], interbold: ['WE Inter', '800'],
  };
  WE.F = {
    hand: '"WE Caveat", "Chalkboard SE", "Marker Felt", "Comic Sans MS", cursive',
    marker: '"WE Marker", "Marker Felt", "Chalkboard SE", cursive',
    round: '"WE Fredoka", "Arial Rounded MT Bold", "Nunito", system-ui, sans-serif',
    comic: '"WE Luckiest", "WE Fredoka", "Impact", sans-serif',
    condensed: '"WE Anton", "Impact", "Haettenschweiler", "Arial Narrow Bold", sans-serif',
    grotesk: '"WE Bricolage", "Avenir Next", "Helvetica Neue", system-ui, sans-serif',
    serif: '"WE Fraunces", "Georgia", "Times New Roman", serif',
    display: '"WE DM Serif", "Didot", "Bodoni 72", "Georgia", serif',
    mono: '"WE Space Mono", "SF Mono", "Menlo", monospace',
    neon: '"WE Monoton", "WE Bungee", "Impact", sans-serif',
    groovy: '"WE Shrikhand", "Cooper Black", "Georgia", serif',
    sign: '"WE Bungee", "Impact", sans-serif',
    wide: '"WE Syne", "Avenir Next", system-ui, sans-serif',
    script: '"WE Pacifico", "Snell Roundhand", "Brush Script MT", cursive',
    ui: '"WE Inter", "Inter", system-ui, -apple-system, "Segoe UI", sans-serif',
  };
  let fontsPromise = null;
  /** Load bundled fonts from the app (self) first, falling back to the system stack silently. */
  WE.loadFonts = (baseUrl, fallback = {}) => {
    if (fontsPromise) return fontsPromise;
    if (typeof FontFace === 'undefined' || !document.fonts) return (fontsPromise = Promise.resolve(false));
    const tryLoad = (family, weight, url) => new FontFace(family, `url("${url}") format("woff2")`, { weight, display: 'swap' }).load().then((f) => { document.fonts.add(f); return true; });
    const jobs = Object.entries(WE.FONT_FILES).map(([file, [family, weight]]) => {
      const url = String(baseUrl || '').replace('{file}', 'we-font-' + file + '.woff2');
      return (url ? tryLoad(family, weight, url) : Promise.reject())
        .catch(() => (fallback[file] ? tryLoad(family, weight, fallback[file]) : false))
        .catch(() => false);
    });
    fontsPromise = Promise.all(jobs).then((r) => r.some(Boolean));
    return fontsPromise;
  };

  /* --------------------------------------------------------------- canvas */
  WE.roundRect = (ctx, x, y, w, h, r) => {
    r = Math.max(0, Math.min(r, w / 2, h / 2));
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  };

  /* ------------------------------------------------------- shape geometry */
  // A "shape" is { pts:[x,y,x,y,...], closed, fill, stroke, lw, nostroke }
  const circlePts = (cx, cy, rx, ry = rx, n = 40, a0 = 0, a1 = Math.PI * 2) => {
    const out = [];
    const full = Math.abs(a1 - a0) >= Math.PI * 2 - 1e-6;
    const steps = full ? n : Math.max(4, Math.round((n * Math.abs(a1 - a0)) / (Math.PI * 2)));
    for (let i = 0; i <= steps; i++) {
      if (full && i === steps) break;
      const a = a0 + ((a1 - a0) * i) / steps;
      out.push(cx + Math.cos(a) * rx, cy + Math.sin(a) * ry);
    }
    return out;
  };
  const rrectPts = (x, y, w, h, r) => {
    r = Math.max(0, Math.min(r, w / 2, h / 2));
    if (r < 0.5) return [x, y, x + w, y, x + w, y + h, x, y + h];
    const o = [];
    const arc = (cx, cy, a0) => { for (let i = 0; i <= 5; i++) { const a = a0 + (Math.PI / 2) * (i / 5); o.push(cx + Math.cos(a) * r, cy + Math.sin(a) * r); } };
    arc(x + w - r, y + r, -Math.PI / 2); arc(x + w - r, y + h - r, 0); arc(x + r, y + h - r, Math.PI / 2); arc(x + r, y + r, Math.PI);
    return o;
  };
  /** Minimal SVG path parser → array of polylines [{pts, closed}] (M L H V Q C Z, abs + rel). */
  const parsePath = (d) => {
    const toks = String(d).match(/[a-zA-Z]|-?\d*\.?\d+(?:e-?\d+)?/g) || [];
    const polys = [];
    let cur = null, x = 0, y = 0, sx = 0, sy = 0, i = 0, cmd = 'M';
    const num = () => parseFloat(toks[i++]);
    const push = (nx, ny) => { cur.pts.push(nx, ny); x = nx; y = ny; };
    while (i < toks.length) {
      if (/[a-zA-Z]/.test(toks[i])) cmd = toks[i++];
      const rel = cmd === cmd.toLowerCase();
      const C = cmd.toUpperCase();
      if (C === 'Z') { if (cur) { cur.closed = true; x = sx; y = sy; } cur = null; continue; }
      if (C === 'M') {
        const nx = num() + (rel ? x : 0), ny = num() + (rel ? y : 0);
        cur = { pts: [], closed: false }; polys.push(cur); sx = nx; sy = ny; push(nx, ny);
        cmd = rel ? 'l' : 'L';
        continue;
      }
      if (!cur) { cur = { pts: [x, y], closed: false }; polys.push(cur); }
      if (C === 'L') push(num() + (rel ? x : 0), num() + (rel ? y : 0));
      else if (C === 'H') push(num() + (rel ? x : 0), y);
      else if (C === 'V') push(x, num() + (rel ? y : 0));
      else if (C === 'Q') {
        const qx = num() + (rel ? x : 0), qy = num() + (rel ? y : 0), ex = num() + (rel ? x : 0), ey = num() + (rel ? y : 0);
        const x0 = x, y0 = y;
        for (let s = 1; s <= 10; s++) { const t = s / 10, u = 1 - t; push(u * u * x0 + 2 * u * t * qx + t * t * ex, u * u * y0 + 2 * u * t * qy + t * t * ey); }
      } else if (C === 'C') {
        const ax = num() + (rel ? x : 0), ay = num() + (rel ? y : 0), bx = num() + (rel ? x : 0), by = num() + (rel ? y : 0), ex = num() + (rel ? x : 0), ey = num() + (rel ? y : 0);
        const x0 = x, y0 = y;
        for (let s = 1; s <= 14; s++) { const t = s / 14, u = 1 - t; push(u * u * u * x0 + 3 * u * u * t * ax + 3 * u * t * t * bx + t * t * t * ex, u * u * u * y0 + 3 * u * u * t * ay + 3 * u * t * t * by + t * t * t * ey); }
      } else { i++; }
    }
    return polys;
  };
  /** Thick polyline → closed capsule polygon (for limbs). */
  const thickPts = (p, w) => {
    const L = [], R = [], n = p.length / 2, hw = w / 2;
    for (let i = 0; i < n; i++) {
      const ax = p[Math.max(0, i - 1) * 2], ay = p[Math.max(0, i - 1) * 2 + 1];
      const bx = p[Math.min(n - 1, i + 1) * 2], by = p[Math.min(n - 1, i + 1) * 2 + 1];
      let dx = bx - ax, dy = by - ay; const l = Math.hypot(dx, dy) || 1; dx /= l; dy /= l;
      L.push(p[i * 2] - dy * hw, p[i * 2 + 1] + dx * hw); R.push(p[i * 2] + dy * hw, p[i * 2 + 1] - dx * hw);
    }
    const out = L.slice();
    const ex = p[(n - 1) * 2], ey = p[(n - 1) * 2 + 1];
    const ea = Math.atan2(R[R.length - 1] - ey, R[R.length - 2] - ex);
    for (let s = 1; s < 8; s++) { const a = ea + Math.PI + (Math.PI * s) / 8; out.push(ex + Math.cos(a) * hw, ey + Math.sin(a) * hw); }
    for (let i = n - 1; i >= 0; i--) out.push(R[i * 2], R[i * 2 + 1]);
    const sxp = p[0], syp = p[1];
    const sa = Math.atan2(L[1] - syp, L[0] - sxp);
    for (let s = 1; s < 8; s++) { const a = sa + Math.PI + (Math.PI * s) / 8; out.push(sxp + Math.cos(a) * hw, syp + Math.sin(a) * hw); }
    return out;
  };
  WE.Geo = { circlePts, rrectPts, parsePath, thickPts };

  /** Build shapes from a compact definition list. */
  WE.buildShapes = (defs) => {
    const out = [];
    for (const d of defs) {
      const k = d[0];
      if (k === 'c') out.push({ pts: circlePts(d[1], d[2], d[3]), closed: true, fill: d[4], stroke: d[5] });
      else if (k === 'e') out.push({ pts: circlePts(d[1], d[2], d[3], d[4]), closed: true, fill: d[5], stroke: d[6] });
      else if (k === 'rr') out.push({ pts: rrectPts(d[1], d[2], d[3], d[4], d[5]), closed: true, fill: d[6], stroke: d[7] });
      else if (k === 'p') parsePath(d[1]).forEach((pl) => out.push({ pts: pl.pts, closed: pl.closed || !!d[2], fill: d[2], stroke: d[3] }));
      else if (k === 'l') out.push({ pts: [d[1], d[2], d[3], d[4]], closed: false, fill: null, stroke: d[5] || 'ink', lw: d[6] });
      else if (k === 'pl') parsePath(d[1]).forEach((pl) => out.push({ pts: pl.pts, closed: false, fill: null, stroke: d[2] || 'ink', lw: d[3] }));
      else if (k === 'arc') out.push({ pts: circlePts(d[1], d[2], d[3], d[3], 40, d[4], d[5]), closed: false, fill: null, stroke: d[6] || 'ink', lw: d[7] });
      else if (k === 'thick') out.push({ pts: thickPts(parsePath(d[1])[0].pts, d[2]), closed: true, fill: d[3], stroke: d[4] });
      else if (k === 't') out.push({ text: d[1], x: d[2], y: d[3], size: d[4], fill: d[5], font: d[6] });
    }
    return out;
  };

  /* ------------------------------------------------------- ink renderer */
  const lenOf = (pts, closed) => {
    let L = 0;
    for (let i = 2; i < pts.length; i += 2) L += Math.hypot(pts[i] - pts[i - 2], pts[i + 1] - pts[i - 1]);
    if (closed && pts.length > 2) L += Math.hypot(pts[0] - pts[pts.length - 2], pts[1] - pts[pts.length - 1]);
    return L;
  };
  const jitterPts = (pts, closed, amp, rnd, seg) => {
    // subdivide long segments, then jitter every vertex — the marker "wobble"
    const src = closed ? pts.concat(pts[0], pts[1]) : pts;
    const out = [];
    for (let i = 0; i < src.length - 2; i += 2) {
      const ax = src[i], ay = src[i + 1], bx = src[i + 2], by = src[i + 3];
      const n = Math.max(1, Math.ceil(Math.hypot(bx - ax, by - ay) / seg));
      for (let s = 0; s < n; s++) { const t = s / n; out.push(lerp(ax, bx, t) + (rnd() - 0.5) * amp, lerp(ay, by, t) + (rnd() - 0.5) * amp); }
    }
    const lx = src[src.length - 2], ly = src[src.length - 1];
    out.push(lx + (rnd() - 0.5) * amp, ly + (rnd() - 0.5) * amp);
    if (closed) { out.push(out[0] + (rnd() - 0.2) * amp * 2, out[1] + (rnd() - 0.2) * amp * 2); } // overshoot like a real pen
    return out;
  };
  /** Stroke the first `f` fraction of a polyline. Returns the pen tip. */
  const strokePartial = (ctx, pts, f) => {
    if (pts.length < 4) return null;
    const total = lenOf(pts, false);
    let want = total * clamp(f), acc = 0;
    ctx.beginPath(); ctx.moveTo(pts[0], pts[1]);
    let tip = [pts[0], pts[1]];
    for (let i = 2; i < pts.length; i += 2) {
      const sl = Math.hypot(pts[i] - pts[i - 2], pts[i + 1] - pts[i - 1]);
      if (acc + sl >= want) {
        const t = sl ? (want - acc) / sl : 0;
        tip = [lerp(pts[i - 2], pts[i], t), lerp(pts[i - 1], pts[i + 1], t)];
        ctx.lineTo(tip[0], tip[1]);
        break;
      }
      acc += sl; ctx.lineTo(pts[i], pts[i + 1]); tip = [pts[i], pts[i + 1]];
    }
    ctx.stroke();
    return tip;
  };
  const hachureCache = new WeakMap();
  const hachureLines = (shape, gap, angle) => {
    const key = gap.toFixed(2) + ':' + angle;
    let m = hachureCache.get(shape);
    if (m && m[key]) return m[key];
    const pts = shape.pts, ca = Math.cos(angle), sa = Math.sin(angle);
    const rp = []; let minY = Infinity, maxY = -Infinity;
    for (let i = 0; i < pts.length; i += 2) { const x = pts[i] * ca + pts[i + 1] * sa, y = -pts[i] * sa + pts[i + 1] * ca; rp.push(x, y); minY = Math.min(minY, y); maxY = Math.max(maxY, y); }
    const lines = [];
    let flip = false;
    for (let y = minY + gap / 2; y < maxY; y += gap) {
      const xs = [];
      for (let i = 0; i < rp.length; i += 2) {
        const j = (i + 2) % rp.length, y1 = rp[i + 1], y2 = rp[j + 1];
        if ((y1 <= y && y2 > y) || (y2 <= y && y1 > y)) xs.push(rp[i] + ((y - y1) / (y2 - y1)) * (rp[j] - rp[i]));
      }
      xs.sort((a, b) => a - b);
      for (let k = 0; k + 1 < xs.length; k += 2) {
        let a = xs[k], b = xs[k + 1]; if (flip) [a, b] = [b, a];
        lines.push([a * ca - y * sa, a * sa + y * ca, b * ca - y * sa, b * sa + y * ca]);
      }
      flip = !flip;
    }
    if (!m) { m = {}; hachureCache.set(shape, m); }
    m[key] = lines;
    return lines;
  };
  const tracePath = (ctx, pts, closed) => {
    ctx.beginPath(); ctx.moveTo(pts[0], pts[1]);
    for (let i = 2; i < pts.length; i += 2) ctx.lineTo(pts[i], pts[i + 1]);
    if (closed) ctx.closePath();
  };

  /**
   * Render shapes in a given artistic mode.
   * o: { mode: clean|cartoon|rough|paper|neon|chalk, pal, progress, seed, scale (local→px), lw (px), boil (int), alpha }
   * Returns the pen tip in canvas pixel space (for the whiteboard hand), or null.
   */
  WE.renderShapes = (ctx, shapes, o) => {
    const mode = o.mode || 'clean', pal = o.pal || {}, s = o.scale || 1, prog = o.progress == null ? 1 : o.progress;
    const col = (k) => (!k ? null : k[0] === '#' || k.startsWith('rgb') ? k : pal[k] || k);
    const lw = (o.lw || 3) / s;
    const rough = mode === 'rough' || mode === 'chalk';
    const ink = mode === 'chalk' ? (pal.chalk || '#f5f2e8') : (pal.ink || '#1a1a1a');
    // lengths for draw-on ordering (outline + fill time)
    const items = [];
    let total = 0;
    for (const sh of shapes) {
      if (sh.text) { const L = 60; items.push({ sh, a: total, L, fl: 0 }); total += L; continue; }
      const L = lenOf(sh.pts, sh.closed);
      const fl = rough && sh.fill && sh.fill !== 'none' ? L * 0.55 : 0;
      items.push({ sh, a: total, L, fl }); total += L + fl;
    }
    const want = prog * total;
    let tip = null;
    ctx.save();
    if (o.alpha != null) ctx.globalAlpha *= o.alpha;
    ctx.lineJoin = 'round'; ctx.lineCap = 'round';
    items.forEach((it, idx) => {
      const sh = it.sh;
      if (want <= it.a) return;
      const fOutline = clamp((want - it.a) / (it.L || 1));
      const fFill = it.fl ? clamp((want - it.a - it.L) / it.fl) : 1;
      if (sh.text) {
        ctx.save(); ctx.globalAlpha *= fOutline;
        ctx.fillStyle = col(sh.fill) || ink; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.font = `${sh.size}px ${sh.font || WE.F.round}`; ctx.fillText(sh.text, sh.x, sh.y); ctx.restore();
        return;
      }
      const fillCol = sh.fill && sh.fill !== 'none' ? col(sh.fill) : null;
      const strokeCol = sh.stroke === false ? null : col(sh.stroke) || ink;
      const lwm = (sh.lw || 1) * lw;
      if (rough) {
        const rnd = WE.rng((o.seed || 1) * 131 + idx * 977 + (o.boil || 0) * 7919);
        if (fillCol && fFill > 0 && sh.closed) {
          const lines = hachureLines(sh, Math.max(2.2, 7 / s), -0.72);
          const n = Math.ceil(lines.length * fFill);
          ctx.save(); tracePath(ctx, sh.pts, true); ctx.clip();
          ctx.strokeStyle = mode === 'chalk' ? WE.Color.rgba(fillCol, 0.75) : WE.Color.rgba(fillCol, 0.9);
          ctx.lineWidth = Math.max(lw * 1.6, 2.6 / s);
          ctx.beginPath();
          for (let i = 0; i < n; i++) {
            const l = lines[i], j = 1.2 / s;
            ctx.moveTo(l[0] + (rnd() - 0.5) * j, l[1] + (rnd() - 0.5) * j); ctx.lineTo(l[2] + (rnd() - 0.5) * j, l[3] + (rnd() - 0.5) * j);
          }
          ctx.stroke(); ctx.restore();
          if (fFill < 1 && n > 0) tip = [lines[n - 1][2], lines[n - 1][3]];
        }
        if (strokeCol && sh.stroke !== false) {
          const amp = (mode === 'chalk' ? 2.2 : 1.6) / s;
          const p1 = jitterPts(sh.pts, sh.closed, amp, rnd, 14 / s);
          ctx.strokeStyle = strokeCol; ctx.lineWidth = lwm;
          if (mode === 'chalk') ctx.setLineDash([9 / s, 1.5 / s, 5 / s, 1 / s]);
          const tp = strokePartial(ctx, p1, fOutline);
          if (fOutline >= 1) { ctx.globalAlpha *= 0.55; ctx.lineWidth = lwm * 0.6; strokePartial(ctx, jitterPts(sh.pts, sh.closed, amp * 1.4, rnd, 18 / s), 1); ctx.globalAlpha /= 0.55; }
          ctx.setLineDash([]);
          if (fOutline < 1) tip = tp;
        }
        return;
      }
      if (mode === 'line') { // technical line-art (blueprint)
        if (fillCol && sh.closed) { tracePath(ctx, sh.pts, true); ctx.fillStyle = WE.Color.rgba(pal.ink || '#ffffff', 0.06); ctx.fill(); }
        ctx.strokeStyle = pal.ink || '#ffffff'; ctx.lineWidth = lwm * 0.7;
        const tp = strokePartial(ctx, sh.closed ? sh.pts.concat(sh.pts[0], sh.pts[1]) : sh.pts, fOutline);
        if (fOutline < 1) tip = tp;
        return;
      }
      // non-rough modes
      let pts = sh.pts;
      if (mode === 'cartoon' && o.boil != null) { const rnd = WE.rng(idx * 31 + o.boil * 17 + 3); pts = pts.map((v) => v + (rnd() - 0.5) * (0.9 / s)); }
      if (fillCol && sh.closed) {
        tracePath(ctx, pts, true);
        if (mode === 'paper') { ctx.save(); ctx.shadowColor = 'rgba(40,25,10,0.28)'; ctx.shadowOffsetY = 4 * (o.shadowScale || 1); ctx.shadowBlur = 6; ctx.fillStyle = fillCol; ctx.fill(); ctx.restore(); }
        else if (mode === 'neon') { ctx.fillStyle = WE.Color.rgba(fillCol, 0.14); ctx.fill(); }
        else { ctx.fillStyle = fillCol; ctx.fill(); }
      }
      if (mode === 'cartoon' && !sh.nostroke && sh.stroke !== false) {
        ctx.strokeStyle = sh.stroke && sh.stroke !== 'ink' ? col(sh.stroke) : ink; ctx.lineWidth = lwm * (sh.closed ? 1 : 1.1);
        if (fOutline >= 1) { tracePath(ctx, pts, sh.closed); ctx.stroke(); } else tip = strokePartial(ctx, sh.closed ? pts.concat(pts[0], pts[1]) : pts, fOutline);
      } else if (mode === 'neon' && sh.stroke !== false) {
        const c = fillCol || strokeCol || '#ff3cac';
        ctx.save(); ctx.shadowColor = c; ctx.shadowBlur = 18; ctx.strokeStyle = c; ctx.lineWidth = lwm * 1.3;
        const pp = sh.closed ? pts.concat(pts[0], pts[1]) : pts;
        const tp = strokePartial(ctx, pp, fOutline);
        ctx.shadowBlur = 0; ctx.strokeStyle = 'rgba(255,255,255,0.85)'; ctx.lineWidth = lwm * 0.45; strokePartial(ctx, pp, fOutline);
        ctx.restore(); if (fOutline < 1) tip = tp;
      } else if ((mode === 'clean' || mode === 'paper') && !fillCol && strokeCol) {
        ctx.strokeStyle = strokeCol; ctx.lineWidth = lwm; tip = strokePartial(ctx, pts, fOutline);
      }
    });
    ctx.restore();
    if (tip) { const m = ctx.getTransform(); return [m.a * tip[0] + m.c * tip[1] + m.e, m.b * tip[0] + m.d * tip[1] + m.f]; }
    return null;
  };

  /* ------------------------------------------------------------------ text */
  /** Lay out text with *emphasis* markup into wrapped lines of words. */
  WE.layoutText = (ctx, text, maxW, font, size, lineH = 1.12) => {
    ctx.font = `${size}px ${font}`;
    const raw = String(text || '').replace(/\[[^\]]*\]/g, '').trim();
    const tokens = [];
    let hl = false;
    let prevSpace = true;
    raw.split(/(\*)/).forEach((part) => {
      if (part === '*') { hl = !hl; return; }
      if (!part) return;
      let first = !prevSpace;
      prevSpace = /\s$/.test(part);
      part.split(/(\s+|\n)/).forEach((w) => {
        if (w === '\n') { tokens.push({ br: true }); first = false; return; }
        if (!w) return;
        if (/^\s+$/.test(w)) { first = false; return; }
        tokens.push({ t: w, hl, glue: first && tokens.length > 0 && !tokens[tokens.length - 1].br });
        first = false;
      });
    });
    const space = ctx.measureText(' ').width;
    const lines = []; let line = { words: [], w: 0 };
    for (const tk of tokens) {
      if (tk.br) { lines.push(line); line = { words: [], w: 0 }; continue; }
      const w = ctx.measureText(tk.t).width;
      const gap = tk.glue ? 0 : space;
      if (line.words.length && !tk.glue && line.w + gap + w > maxW) { lines.push(line); line = { words: [], w: 0 }; }
      line.words.push({ t: tk.t, hl: tk.hl, w, x: line.words.length ? line.w + gap : 0 });
      line.w = line.words.length > 1 ? line.w + gap + w : w;
    }
    if (line.words.length) lines.push(line);
    const widest = lines.reduce((m, l) => Math.max(m, l.w), 0);
    return { lines, size, font, lh: size * lineH, h: lines.length * size * lineH, w: widest };
  };
  /** Shrink text until it fits a box. */
  WE.fitText = (ctx, text, maxW, maxH, font, maxSize, minSize = 12, lineH = 1.08) => {
    let size = maxSize, lay;
    for (; size >= minSize; size -= Math.max(1, size * 0.06)) {
      lay = WE.layoutText(ctx, text, maxW, font, size, lineH);
      const longest = lay.lines.reduce((m, l) => Math.max(m, ...l.words.map((w) => w.w)), 0);
      if (lay.h <= maxH && longest <= maxW) return lay;
    }
    return WE.layoutText(ctx, text, maxW, font, minSize, lineH);
  };
  /**
   * Draw laid-out text with an entrance effect.
   * o: { x, y (top), align, color, hl (highlight colour), hlStyle: marker|underline|box|glow|color, effect: none|type|pop|rise|hand|slam|wave|drop, p (0..1), t, stroke, strokeW, shadow }
   * Returns hand tip for the 'hand' effect.
   */
  WE.drawText = (ctx, lay, o) => {
    const p = o.p == null ? 1 : o.p, eff = o.effect || 'none', E = WE.Ease;
    const nWords = lay.lines.reduce((n, l) => n + l.words.length, 0) || 1;
    const nChars = lay.lines.reduce((n, l) => n + l.words.reduce((m, w) => m + w.t.length + 1, 0), 0) || 1;
    let wi = 0, ci = 0, tip = null;
    ctx.save();
    ctx.font = `${lay.size}px ${lay.font}`; ctx.textBaseline = 'alphabetic';
    if (eff === 'slam') {
      const k = E.outBack(clamp(p * 1.6)); const sc = lerp(2.4, 1, k);
      const cx = o.x + (o.align === 'center' ? 0 : lay.w / 2), cy = o.y + lay.h / 2;
      ctx.translate(cx, cy); ctx.scale(sc, sc); ctx.translate(-cx, -cy); ctx.globalAlpha *= clamp(p * 4);
    }
    lay.lines.forEach((line, li) => {
      const lx = o.align === 'center' ? o.x - line.w / 2 : o.align === 'right' ? o.x - line.w : o.x;
      const by = o.y + lay.lh * li + lay.size * 0.9;
      if (eff === 'hand') {
        const lineStart = li / lay.lines.length, lineEnd = (li + 1) / lay.lines.length;
        const lp = range(p, lineStart, lineEnd);
        if (lp <= 0) return;
        ctx.save(); ctx.beginPath(); ctx.rect(lx - 10, by - lay.size * 1.2, (line.w + 20) * lp, lay.size * 1.6); ctx.clip();
        if (lp < 1) tip = [lx + line.w * lp, by - lay.size * 0.2];
        line.words.forEach((w) => drawWord(ctx, w, lx + w.x, by, o, lay, 1, 0, 0));
        ctx.restore();
        return;
      }
      line.words.forEach((w) => {
        let a = 1, dy = 0, sc = 1, show = 1;
        const k = wi / nWords;
        if (eff === 'pop') { const q = range(p, k * 0.75, k * 0.75 + 0.25); sc = E.outBack(q); a = clamp(q * 3); }
        else if (eff === 'rise') { const q = E.outCubic(range(p, k * 0.7, k * 0.7 + 0.3)); dy = (1 - q) * lay.size * 0.8; a = q; }
        else if (eff === 'drop') { const q = E.outBounce(range(p, k * 0.6, k * 0.6 + 0.4)); dy = -(1 - q) * lay.size * 3; a = clamp(q * 4); }
        else if (eff === 'type') { const need = (ci + w.t.length) / nChars; show = clamp((p - ci / nChars) / (need - ci / nChars + 1e-6)); }
        else if (eff === 'wave') { dy = Math.sin((o.t || 0) * 5 + wi * 0.7) * lay.size * 0.06; a = clamp(p * 3 - k); }
        ci += w.t.length + 1; wi++;
        if (a <= 0.001 || show <= 0 || sc <= 0.001) return;
        ctx.save(); ctx.globalAlpha *= a;
        const wx = lx + w.x, wy = by + dy;
        if (sc !== 1) { ctx.translate(wx + w.w / 2, wy - lay.size * 0.35); ctx.scale(sc, sc); ctx.translate(-(wx + w.w / 2), -(wy - lay.size * 0.35)); }
        drawWord(ctx, w, wx, wy, o, lay, show, li, wi);
        ctx.restore();
      });
    });
    ctx.restore();
    if (tip) { const m = ctx.getTransform(); return [m.a * tip[0] + m.c * tip[1] + m.e, m.b * tip[0] + m.d * tip[1] + m.f]; }
    return null;
  };
  const drawWord = (ctx, w, x, y, o, lay, show, li, wi) => {
    const txt = show < 1 ? w.t.slice(0, Math.ceil(w.t.length * show)) : w.t;
    if (w.hl && o.hl) {
      const st = o.hlStyle || 'color';
      if (st === 'marker') { ctx.save(); ctx.fillStyle = WE.Color.rgba(o.hl, 0.55); const r = WE.rng(wi * 13 + li); ctx.beginPath(); ctx.moveTo(x - 6, y - lay.size * 0.42 + r() * 4); ctx.lineTo(x + w.w + 6, y - lay.size * 0.5 + r() * 4); ctx.lineTo(x + w.w + 4, y + lay.size * 0.08 + r() * 3); ctx.lineTo(x - 4, y + lay.size * 0.12); ctx.closePath(); ctx.fill(); ctx.restore(); }
      else if (st === 'box') { ctx.save(); ctx.fillStyle = o.hl; WE.roundRect(ctx, x - lay.size * 0.12, y - lay.size * 0.86, w.w + lay.size * 0.24, lay.size * 1.06, lay.size * 0.14); ctx.fill(); ctx.restore(); }
      else if (st === 'underline') { ctx.save(); ctx.strokeStyle = o.hl; ctx.lineWidth = Math.max(3, lay.size * 0.08); ctx.lineCap = 'round'; ctx.beginPath(); ctx.moveTo(x, y + lay.size * 0.12); ctx.quadraticCurveTo(x + w.w / 2, y + lay.size * 0.22, x + w.w, y + lay.size * 0.08); ctx.stroke(); ctx.restore(); }
    }
    const fill = w.hl && o.hl && (o.hlStyle === 'color' || o.hlStyle === 'glow' || o.hlStyle === 'underline') ? o.hl : w.hl && o.hlText ? o.hlText : o.color;
    if (o.shadow) { ctx.shadowColor = o.shadow; ctx.shadowBlur = o.shadowBlur || 0; ctx.shadowOffsetX = o.shadowX || 0; ctx.shadowOffsetY = o.shadowY || 0; }
    if (w.hl && o.hlStyle === 'glow') { ctx.shadowColor = o.hl; ctx.shadowBlur = lay.size * 0.35; }
    if (o.stroke) { ctx.lineJoin = 'round'; ctx.strokeStyle = o.stroke; ctx.lineWidth = o.strokeW || lay.size * 0.12; ctx.strokeText(txt, x, y); }
    if (o.gradient) { const g = ctx.createLinearGradient(x, y - lay.size, x, y); o.gradient.forEach((c, i) => g.addColorStop(i / (o.gradient.length - 1), c)); ctx.fillStyle = g; } else ctx.fillStyle = fill;
    if (!o.outlineOnly) ctx.fillText(txt, x, y);
    ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0;
  };

  /* ------------------------------------------------------------ textures */
  const texCache = {};
  /** Paper / grain / halftone textures, pre-rendered once and used as patterns. */
  WE.texture = (ctx, kind, color = '#000') => {
    const key = kind + color;
    if (texCache[key]) return ctx.createPattern(texCache[key], 'repeat');
    const c = document.createElement('canvas');
    const g = c.getContext('2d');
    const r = WE.rng(kind);
    if (kind === 'grain' || kind === 'paper') {
      c.width = c.height = 256;
      const img = g.createImageData(256, 256);
      for (let i = 0; i < img.data.length; i += 4) {
        const v = kind === 'paper' ? 0 : 255;
        img.data[i] = img.data[i + 1] = img.data[i + 2] = v;
        img.data[i + 3] = r() * (kind === 'paper' ? 16 : 22);
      }
      g.putImageData(img, 0, 0);
      if (kind === 'paper') { g.strokeStyle = 'rgba(120,100,70,0.05)'; for (let i = 0; i < 40; i++) { g.beginPath(); const x = r() * 256, y = r() * 256; g.moveTo(x, y); g.lineTo(x + r() * 40 - 20, y + r() * 6); g.stroke(); } }
    } else if (kind === 'halftone') {
      c.width = c.height = 14; g.fillStyle = color; g.beginPath(); g.arc(3.5, 3.5, 2.2, 0, 7); g.arc(10.5, 10.5, 2.2, 0, 7); g.fill();
    } else if (kind === 'lines') {
      c.width = c.height = 10; g.strokeStyle = color; g.lineWidth = 1.6; g.beginPath(); g.moveTo(0, 10); g.lineTo(10, 0); g.stroke();
    } else if (kind === 'grid') {
      c.width = c.height = 40; g.strokeStyle = color; g.lineWidth = 1; g.strokeRect(0.5, 0.5, 40, 40);
    } else if (kind === 'dots') {
      c.width = c.height = 24; g.fillStyle = color; g.beginPath(); g.arc(12, 12, 1.6, 0, 7); g.fill();
    }
    texCache[key] = c;
    return ctx.createPattern(c, 'repeat');
  };

  /* --------------------------------------------------------------- QR code */
  // Real, scannable QR (byte mode, ECC level M, versions 1–10). Derived from the ISO/IEC 18004 algorithm.
  WE.QR = (function () {
    const ECC_M = [null, [10, 1], [16, 1], [26, 1], [18, 2], [24, 2], [16, 4], [18, 4], [22, 4], [22, 5], [26, 5]];
    const rawModules = (v) => { let r = (16 * v + 128) * v + 64; if (v >= 2) { const n = Math.floor(v / 7) + 2; r -= (25 * n - 10) * n - 55; if (v >= 7) r -= 36; } return r; };
    const dataCw = (v) => Math.floor(rawModules(v) / 8) - ECC_M[v][0] * ECC_M[v][1];
    const mul = (x, y) => { let z = 0; for (let i = 7; i >= 0; i--) { z = (z << 1) ^ ((z >>> 7) * 0x11d); z ^= ((y >>> i) & 1) * x; } return z & 255; };
    const rsDivisor = (deg) => { const r = new Array(deg).fill(0); r[deg - 1] = 1; let root = 1; for (let i = 0; i < deg; i++) { for (let j = 0; j < r.length; j++) { r[j] = mul(r[j], root); if (j + 1 < r.length) r[j] ^= r[j + 1]; } root = mul(root, 2); } return r; };
    const rsRem = (data, div) => { const r = div.map(() => 0); for (const b of data) { const f = b ^ r.shift(); r.push(0); div.forEach((c, i) => (r[i] ^= mul(c, f))); } return r; };
    const alignPos = (v) => { if (v === 1) return []; const n = Math.floor(v / 7) + 2; const step = v === 32 ? 26 : Math.ceil((v * 4 + 4) / (n * 2 - 2)) * 2; const r = [6]; for (let p = v * 4 + 10; r.length < n; p -= step) r.splice(1, 0, p); return r; };
    const encode = (text) => {
      const bytes = Array.from(new TextEncoder().encode(String(text)));
      let v = 1;
      for (; v <= 10; v++) { const cap = dataCw(v) * 8; const need = 4 + (v < 10 ? 8 : 16) + bytes.length * 8; if (need <= cap) break; }
      if (v > 10) return null;
      const bits = [];
      const put = (val, len) => { for (let i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1); };
      put(4, 4); put(bytes.length, v < 10 ? 8 : 16); bytes.forEach((b) => put(b, 8));
      const cap = dataCw(v) * 8;
      put(0, Math.min(4, cap - bits.length)); put(0, (8 - (bits.length % 8)) % 8);
      for (let pad = 0xec; bits.length < cap; pad ^= 0xec ^ 0x11) put(pad, 8);
      const data = []; for (let i = 0; i < bits.length; i += 8) data.push(parseInt(bits.slice(i, i + 8).join(''), 2));
      // blocks + interleave
      const [ecLen, nBlocks] = ECC_M[v], raw = Math.floor(rawModules(v) / 8);
      const nShort = nBlocks - (raw % nBlocks), shortLen = Math.floor(raw / nBlocks);
      const div = rsDivisor(ecLen), blocks = [];
      for (let i = 0, k = 0; i < nBlocks; i++) {
        const dat = data.slice(k, k + shortLen - ecLen + (i < nShort ? 0 : 1)); k += dat.length;
        const ecc = rsRem(dat, div); if (i < nShort) dat.push(0); blocks.push(dat.concat(ecc));
      }
      const final = [];
      for (let i = 0; i < blocks[0].length; i++) blocks.forEach((b, j) => { if (i !== shortLen - ecLen || j >= nShort) final.push(b[i]); });
      // matrix
      const size = v * 4 + 17, M = [], F = [];
      for (let i = 0; i < size; i++) { M.push(new Array(size).fill(false)); F.push(new Array(size).fill(false)); }
      const set = (x, y, d) => { M[y][x] = d; F[y][x] = true; };
      for (let i = 0; i < size; i++) { set(6, i, i % 2 === 0); set(i, 6, i % 2 === 0); }
      const finder = (cx, cy) => { for (let dy = -4; dy <= 4; dy++) for (let dx = -4; dx <= 4; dx++) { const d = Math.max(Math.abs(dx), Math.abs(dy)), x = cx + dx, y = cy + dy; if (x >= 0 && x < size && y >= 0 && y < size) set(x, y, d !== 2 && d !== 4); } };
      finder(3, 3); finder(size - 4, 3); finder(3, size - 4);
      const ap = alignPos(v);
      ap.forEach((a, i) => ap.forEach((b, j) => { if ((i === 0 && j === 0) || (i === 0 && j === ap.length - 1) || (i === ap.length - 1 && j === 0)) return; for (let dy = -2; dy <= 2; dy++) for (let dx = -2; dx <= 2; dx++) set(a + dx, b + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1); }));
      const drawFormat = (mask) => {
        const d = (0 << 3) | mask; let r = d; for (let i = 0; i < 10; i++) r = (r << 1) ^ ((r >>> 9) * 0x537); const b = ((d << 10) | r) ^ 0x5412;
        const g = (i) => ((b >>> i) & 1) !== 0;
        for (let i = 0; i <= 5; i++) set(8, i, g(i)); set(8, 7, g(6)); set(8, 8, g(7)); set(7, 8, g(8)); for (let i = 9; i < 15; i++) set(14 - i, 8, g(i));
        for (let i = 0; i < 8; i++) set(size - 1 - i, 8, g(i)); for (let i = 8; i < 15; i++) set(8, size - 15 + i, g(i)); set(8, size - 8, true);
      };
      drawFormat(0);
      if (v >= 7) { let r = v; for (let i = 0; i < 12; i++) r = (r << 1) ^ ((r >>> 11) * 0x1f25); const b = (v << 12) | r; for (let i = 0; i < 18; i++) { const bt = ((b >>> i) & 1) !== 0, a = size - 11 + (i % 3), c = Math.floor(i / 3); set(a, c, bt); set(c, a, bt); } }
      let bi = 0;
      for (let right = size - 1; right >= 1; right -= 2) {
        if (right === 6) right = 5;
        for (let vert = 0; vert < size; vert++) for (let j = 0; j < 2; j++) {
          const x = right - j, up = ((right + 1) & 2) === 0, y = up ? size - 1 - vert : vert;
          if (!F[y][x] && bi < final.length * 8) { M[y][x] = ((final[bi >>> 3] >>> (7 - (bi & 7))) & 1) !== 0; bi++; }
        }
      }
      const maskFn = [(x, y) => (x + y) % 2 === 0, (x, y) => y % 2 === 0, (x) => x % 3 === 0, (x, y) => (x + y) % 3 === 0, (x, y) => (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0, (x, y) => ((x * y) % 2) + ((x * y) % 3) === 0, (x, y) => (((x * y) % 2) + ((x * y) % 3)) % 2 === 0, (x, y) => (((x + y) % 2) + ((x * y) % 3)) % 2 === 0];
      const applyMask = (m) => { for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) if (!F[y][x] && maskFn[m](x, y)) M[y][x] = !M[y][x]; };
      const penalty = () => { let p = 0; for (let y = 0; y < size; y++) { let run = 1; for (let x = 1; x < size; x++) { if (M[y][x] === M[y][x - 1]) { run++; if (run === 5) p += 3; else if (run > 5) p++; } else run = 1; } } for (let x = 0; x < size; x++) { let run = 1; for (let y = 1; y < size; y++) { if (M[y][x] === M[y - 1][x]) { run++; if (run === 5) p += 3; else if (run > 5) p++; } else run = 1; } } for (let y = 0; y < size - 1; y++) for (let x = 0; x < size - 1; x++) { const c = M[y][x]; if (c === M[y][x + 1] && c === M[y + 1][x] && c === M[y + 1][x + 1]) p += 3; } let dark = 0; M.forEach((r) => r.forEach((c) => (dark += c ? 1 : 0))); p += Math.floor(Math.abs(dark * 20 - size * size * 10) / (size * size)) * 10; return p; };
      let best = 0, bestP = Infinity;
      for (let m = 0; m < 8; m++) { applyMask(m); drawFormat(m); const pe = penalty(); if (pe < bestP) { bestP = pe; best = m; } applyMask(m); }
      applyMask(best); drawFormat(best);
      return { size, modules: M };
    };
    const cache = {};
    return {
      encode,
      /** Draw a scannable QR with quiet zone. */
      draw(ctx, text, x, y, size, opts = {}) {
        const q = cache[text] || (cache[text] = encode(text));
        if (!q) return;
        const bg = opts.bg || '#ffffff', fg = opts.fg || '#111111', quiet = 2, n = q.size + quiet * 2, m = size / n;
        ctx.save(); ctx.fillStyle = bg; WE.roundRect(ctx, x, y, size, size, opts.radius == null ? size * 0.06 : opts.radius); ctx.fill();
        ctx.fillStyle = fg;
        for (let r = 0; r < q.size; r++) for (let c = 0; c < q.size; c++) if (q.modules[r][c]) ctx.fillRect(x + (c + quiet) * m, y + (r + quiet) * m, Math.ceil(m), Math.ceil(m));
        ctx.restore();
      },
    };
  })();

  /* ------------------------------------------------------------- particles */
  /** Deterministic confetti / sparkle burst as a pure function of time (scrub-safe). */
  WE.confetti = (ctx, cx, cy, t, opts = {}) => {
    const n = opts.n || 70, colors = opts.colors || ['#ff3d7f', '#ffd23f', '#3ddc97', '#4cc9f0', '#9b5de5'], spread = opts.spread || 1, sz = opts.size || 1;
    if (t <= 0) return;
    const r = WE.rng(opts.seed || 7), g = (opts.gravity == null ? 900 : opts.gravity) * spread;
    ctx.save();
    for (let i = 0; i < n; i++) {
      const a = -Math.PI / 2 + (r() - 0.5) * Math.PI * (opts.cone || 1.6), v = (380 + r() * 620) * spread;
      const x = cx + Math.cos(a) * v * t, y = cy + Math.sin(a) * v * t + 0.5 * g * t * t;
      const life = 1 - clamp((t - 1.6 - r()) / 0.8);
      if (life <= 0) continue;
      ctx.save(); ctx.globalAlpha *= life; ctx.translate(x, y); ctx.rotate(t * (4 + r() * 8) * (r() > 0.5 ? 1 : -1));
      ctx.fillStyle = colors[i % colors.length];
      const k = r();
      if (k < 0.45) ctx.fillRect(-6 * sz, -3 * sz, 12 * sz, 6 * sz);
      else if (k < 0.75) { ctx.beginPath(); ctx.arc(0, 0, 4.5 * sz, 0, 7); ctx.fill(); }
      else { ctx.scale(1, Math.abs(Math.cos(t * 6 + i))); ctx.beginPath(); ctx.moveTo(0, -7 * sz); ctx.lineTo(6 * sz, 5 * sz); ctx.lineTo(-6 * sz, 5 * sz); ctx.fill(); }
      ctx.restore();
    }
    ctx.restore();
  };
  WE.sparkle = (ctx, x, y, r, color = '#fff', rot = 0) => {
    ctx.save(); ctx.translate(x, y); ctx.rotate(rot); ctx.fillStyle = color; ctx.beginPath();
    for (let i = 0; i < 8; i++) { const a = (i * Math.PI) / 4, rr = i % 2 === 0 ? r : r * 0.22; ctx.lineTo(Math.cos(a) * rr, Math.sin(a) * rr); }
    ctx.closePath(); ctx.fill(); ctx.restore();
  };

  /* ---------------------------------------------------------------- utils */
  WE.clone = (o) => JSON.parse(JSON.stringify(o));
  WE.esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  WE.download = (blobOrUrl, name) => {
    const url = typeof blobOrUrl === 'string' ? blobOrUrl : URL.createObjectURL(blobOrUrl);
    const a = document.createElement('a'); a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove();
    if (typeof blobOrUrl !== 'string') setTimeout(() => URL.revokeObjectURL(url), 4000);
  };
  /** Pick the best recorder container: MP4 (H.264) when the browser supports it, else WebM. */
  WE.pickRecorderType = () => {
    const opts = ['video/mp4;codecs=avc1.42E01E,mp4a.40.2', 'video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];
    for (const t of opts) if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported(t)) return t;
    return '';
  };
})(typeof window !== 'undefined' ? window : globalThis);
