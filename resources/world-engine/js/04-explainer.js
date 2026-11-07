/* ==========================================================================
 * ATG World Engine — 04 Explainer renderer + player
 * Six visual worlds (whiteboard, cartoon, motion, collage, comic, neon),
 * nine shot types, style-aware transitions, karaoke captions, the drawing
 * hand, scene-timed SFX cues, and real-time WebM/MP4 export.
 * ========================================================================== */
(function (root) {
  'use strict';
  const WE = root.WorldEngine;
  const { clamp, lerp, range } = WE.M;
  const E = WE.Ease, C = WE.Color, F = WE.F;

  /* ------------------------------------------------------------- styles */
  const STYLES = {
    whiteboard: { label: 'Whiteboard', emoji: '✏️', blurb: 'Hand-drawn live, marker in hand', mode: 'rough', font: F.hand, head: F.marker, hlStyle: 'marker', transition: 'pan', textFx: () => 'hand' },
    cartoon: { label: 'Cartoon', emoji: '🎈', blurb: 'Bouncy, bold outlines, line-boil', mode: 'cartoon', font: F.round, head: F.comic, hlStyle: 'box', transition: 'iris', textFx: (h) => (h ? 'pop' : 'rise') },
    motion: { label: 'Motion Graphics', emoji: '⚡', blurb: 'Kinetic type, glow, slick moves', mode: 'clean', font: F.grotesk, head: F.condensed, hlStyle: 'color', transition: 'slide', textFx: (h) => (h ? 'slam' : 'rise') },
    collage: { label: 'Paper Collage', emoji: '✂️', blurb: 'Cut paper, tape, editorial serif', mode: 'paper', font: F.serif, head: F.display, hlStyle: 'box', transition: 'paper', textFx: (h) => (h ? 'drop' : 'rise') },
    comic: { label: 'Comic Book', emoji: '💥', blurb: 'Halftone, POW!, caption boxes', mode: 'cartoon', font: F.comic, head: F.comic, hlStyle: 'box', transition: 'panel', textFx: () => 'pop' },
    neon: { label: 'Neon Arcade', emoji: '🌆', blurb: 'Synthwave grid, glowing tubes', mode: 'neon', font: F.sign, head: F.neon, hlStyle: 'glow', transition: 'glitch', textFx: (h) => (h ? 'type' : 'type') },
  };
  const SHOTS_META = {
    hook: 'Hook — big question', talk: 'Character talks to camera', chaos: 'Chaos — everything at once', stat: 'Big number', split: 'Before ↔ After',
    reveal: 'Product reveal (device)', steps: '1-2-3 steps', proof: 'Testimonial / proof', cta: 'Call to action',
  };
  const ASPECTS = { '16:9': [1280, 720], '9:16': [720, 1280], '1:1': [1000, 1000] };

  const layCache = new Map();
  const fitCached = (ctx, text, w, h, font, size, min, lh) => {
    const k = [text, w | 0, h | 0, font, size | 0, min, lh].join('|');
    let v = layCache.get(k);
    if (!v) { v = WE.fitText(ctx, text, w, h, font, size, min, lh); if (layCache.size > 400) layCache.clear(); layCache.set(k, v); }
    return v;
  };
  WE.clearTextCache = () => layCache.clear();

  /* ------------------------------------------------------------ env */
  const makeEnv = (ctx, project, scene, t, W, H, o = {}) => {
    const st = STYLES[project.style] || STYLES.cartoon;
    const brand = project.brand || {};
    const u = Math.min(W, H) / 100;
    const pal = { ink: st.mode === 'neon' ? '#ffffff' : '#1d1b26', a: brand.a || '#ff3d7f', b: brand.b || '#4f7cff', c: brand.c || '#ffd23f', d: brand.d || '#2b2d42', l: '#ffffff', paper: '#fbfaf5' };
    const env = {
      ctx, project, scene, t, W, H, u, st, pal, brand, portrait: H > W * 1.1, square: Math.abs(H - W) < W * 0.1,
      rnd: WE.rng(WE.hash(scene.id || 'x')), tips: [], cues: [], speaking: !!o.speaking, still: !!o.still,
      boil: st.mode === 'cartoon' ? Math.floor(t * 8) : null,
      dur: scene.duration || 6,
    };
    const R = (opts) => Object.assign({ mode: st.mode, pal, seed: WE.hash(scene.id || 's') % 997, boil: env.boil, lw: st.mode === 'rough' ? 3.2 : st.mode === 'neon' ? 3 : 3.6 }, opts || {});
    env.R = R;
    env.p = (start, dur = 1) => range(t, start, start + dur);
    env.tip = (tip, start) => { if (tip) env.tips.push({ tip, start }); };
    env.cue = (time, name) => env.cues.push([time, name]);
    /** Generic reveal transform for non-drawn styles. */
    env.reveal = (p, cx, cy, kind) => {
      if (p <= 0) return false;
      if (st.mode === 'rough') return true;
      const k = kind || st.transition;
      ctx.translate(cx, cy);
      if (st.mode === 'neon') { ctx.globalAlpha *= p < 1 ? (Math.sin(p * 40) > -0.3 ? p : p * 0.3) : 1; }
      else if (k === 'slide' || project.style === 'motion') { const q = E.outCubic(p); ctx.translate(0, (1 - q) * 6 * u); ctx.globalAlpha *= q; ctx.scale(0.9 + 0.1 * q, 0.9 + 0.1 * q); }
      else if (k === 'paper') { const q = E.outBack(p); ctx.rotate((1 - q) * 0.4); ctx.translate(0, (1 - q) * -20 * u); ctx.globalAlpha *= clamp(p * 3); }
      else { const q = E.outBack(p); const sq = 1 + Math.sin(p * Math.PI) * 0.12; ctx.scale(q * (2 - sq), q * sq); }
      ctx.translate(-cx, -cy);
      return true;
    };
    env.prop = (name, x, y, size, start, o2 = {}) => {
      const dur = o2.dur || (st.mode === 'rough' ? 1.1 : 0.55);
      const p = env.p(start, dur);
      if (p <= 0) return;
      if (!env.still && o2.sfx !== false && st.mode !== 'rough') env.cue(start, o2.sfx || 'pop');
      const fl = o2.float === false ? 0 : Math.sin(t * 2.1 + x * 0.01) * size * 0.04;
      ctx.save();
      env.reveal(p, x, y + fl);
      const tip = WE.Cast.drawProp(ctx, name, x, y + fl, size, R({ progress: st.mode === 'rough' || st.mode === 'neon' ? p : 1 }), o2.rot || 0);
      ctx.restore();
      if (p < 1) env.tip(tip, start);
    };
    env.char = (x, y, h, start, o2 = {}) => {
      const cast = Object.assign({}, project.character || {}, o2.cast || {});
      if (cast.hidden) return null;
      const dur = st.mode === 'rough' ? 1.8 : 0.6;
      const p = env.p(start, dur);
      if (p <= 0) return null;
      if (!env.still && st.mode !== 'rough') env.cue(start, project.style === 'comic' ? 'boing' : 'pop');
      ctx.save();
      env.reveal(p, x, y - h / 2);
      const res = WE.Cast.drawCharacter(ctx, x, y, h, Object.assign({}, cast, { mood: o2.mood || scene.mood || 'happy', pose: o2.pose || scene.pose || 'idle', t, talking: o2.talking == null ? env.speaking : o2.talking, flip: o2.flip, seed: 3, still: env.still }), R({ progress: st.mode === 'rough' || st.mode === 'neon' ? p : 1, lw: (st.mode === 'rough' ? 3 : 3.4) }));
      ctx.restore();
      if (p < 1) env.tip(res.tip, start);
      return res;
    };
    env.look = (head, o2 = {}) => {
      const L = { color: pal.ink, hl: pal.a, hlStyle: st.hlStyle };
      if (project.style === 'whiteboard') { L.color = '#1f1f24'; L.hl = pal.c === '#ffffff' ? pal.a : pal.c; }
      if (project.style === 'cartoon') { L.color = head ? '#ffffff' : '#1d1b26'; if (head) { L.stroke = '#1d1b26'; L.strokeW = 0; L.shadow = 'rgba(29,27,38,0.9)'; L.shadowY = u * 0.6; } L.hl = pal.a; L.hlText = '#ffffff'; }
      if (project.style === 'motion') { L.color = '#ffffff'; L.hl = pal.a; L.hlStyle = 'color'; }
      if (project.style === 'collage') { L.color = '#1d1a16'; L.hl = pal.a; L.hlText = '#ffffff'; }
      if (project.style === 'comic') { L.color = '#111111'; L.hl = '#ff2e4c'; L.hlText = '#ffffff'; if (head) { L.color = '#ffffff'; L.stroke = '#111'; L.shadow = '#111'; L.shadowX = u * 0.5; L.shadowY = u * 0.5; } }
      if (project.style === 'neon') { L.color = '#ffffff'; L.shadow = pal.a; L.shadowBlur = u * 2.2; L.hl = pal.c; L.hlStyle = 'glow'; }
      if (L.stroke && !L.strokeW) L.strokeW = null;
      return Object.assign(L, o2);
    };
    env.text = (str, x, y, w, h, o2 = {}) => {
      if (!str) return null;
      const start = o2.start || 0, dur = o2.dur || (st.mode === 'rough' ? Math.min(2.4, 0.35 + String(str).length * 0.035) : 0.9);
      const p = env.p(start, dur);
      if (p <= 0) return null;
      const font = o2.font || (o2.head ? st.head : st.font);
      const lay = fitCached(ctx, str, w, h, font, o2.size || u * 9, o2.min || u * 2.6, o2.lh || (o2.head ? 1.02 : 1.12));
      const ty = o2.valign === 'middle' ? y + (h - lay.h) / 2 : o2.valign === 'bottom' ? y + h - lay.h : y;
      const ax = o2.align === 'center' ? x + w / 2 : o2.align === 'right' ? x + w : x;
      const look = env.look(o2.head, o2.look);
      if (look.stroke && look.strokeW == null) look.strokeW = lay.size * 0.16;
      if (project.style === 'cartoon' && o2.head && !o2.look) { // chunky cartoon title: ink outline + offset shadow
        WE.drawText(ctx, lay, Object.assign({}, look, { x: ax, y: ty + lay.size * 0.07, align: o2.align, effect: o2.effect || st.textFx(o2.head), p, t, color: '#1d1b26', stroke: '#1d1b26', strokeW: lay.size * 0.2, shadow: null, hl: null }));
      }
      const tip = WE.drawText(ctx, lay, Object.assign({}, look, { x: ax, y: ty, align: o2.align, effect: o2.effect || st.textFx(o2.head), p, t }));
      if (tip && p < 1) env.tip(tip, start);
      lay.y = ty; lay.x = ax;
      return lay;
    };
    env.panel = (x, y, w, h, start, fill, o2 = {}) => {
      const p = env.p(start, o2.dur || (st.mode === 'rough' ? 0.9 : 0.5));
      if (p <= 0) return false;
      ctx.save();
      env.reveal(p, x + w / 2, y + h / 2);
      const r = o2.r == null ? u * 2.4 : o2.r;
      if (project.style === 'comic') { ctx.fillStyle = '#111'; WE.roundRect(ctx, x + u * 0.8, y + u * 0.8, w, h, r * 0.3); ctx.fill(); }
      if (project.style === 'collage') { // taped paper card
        ctx.save(); ctx.translate(x + w / 2, y + h / 2); ctx.rotate(((WE.hash(String(x | 0)) % 7) - 3) * 0.006); ctx.translate(-(x + w / 2), -(y + h / 2));
      }
      const tip = WE.renderShapes(ctx, [{ pts: WE.Geo.rrectPts(x, y, w, h, project.style === 'comic' ? r * 0.3 : r), closed: true, fill: fill || 'l', stroke: o2.stroke || 'ink' }], R({ progress: st.mode === 'rough' || st.mode === 'neon' ? p : 1, shadowScale: u * 0.3 }));
      if (project.style === 'collage') {
        ctx.fillStyle = 'rgba(255,240,180,0.75)'; ctx.save(); ctx.translate(x + w / 2, y); ctx.rotate(-0.05); ctx.fillRect(-u * 6, -u * 1.4, u * 12, u * 2.8); ctx.restore();
        ctx.restore();
      }
      ctx.restore();
      if (p < 1) env.tip(tip, start);
      return true;
    };
    env.arrow = (x1, y1, x2, y2, start, o2 = {}) => {
      const p = env.p(start, o2.dur || 0.7);
      if (p <= 0) return;
      const mx = (x1 + x2) / 2 + (y2 - y1) * (o2.bend || 0.25), my = (y1 + y2) / 2 - (x2 - x1) * (o2.bend || 0.25);
      const pts = [];
      for (let i = 0; i <= 20; i++) { const s = i / 20, v = 1 - s; pts.push(v * v * x1 + 2 * v * s * mx + s * s * x2, v * v * y1 + 2 * v * s * my + s * s * y2); }
      const a = Math.atan2(y2 - my, x2 - mx), hl = u * 2.6;
      const head = [x2 + Math.cos(a + 2.6) * hl, y2 + Math.sin(a + 2.6) * hl, x2, y2, x2 + Math.cos(a - 2.6) * hl, y2 + Math.sin(a - 2.6) * hl];
      const shapes = [{ pts, closed: false, fill: null, stroke: o2.color || (project.style === 'motion' || project.style === 'neon' ? 'a' : 'ink'), lw: 1.3 }];
      if (p > 0.85) shapes.push({ pts: head, closed: false, fill: null, stroke: o2.color || (project.style === 'motion' || project.style === 'neon' ? 'a' : 'ink'), lw: 1.3 });
      const tip = WE.renderShapes(ctx, shapes, R({ mode: st.mode === 'cartoon' || st.mode === 'paper' ? 'clean' : st.mode, progress: Math.min(1, p / 0.85), lw: u * 0.7 }));
      if (p < 1) env.tip(tip, start);
    };
    env.burst = (cx, cy, r, start, colors) => {
      const p = env.p(start, 0.6);
      if (p <= 0) return;
      const n = 18, rot = t * 0.25, q = E.outBack(p);
      ctx.save(); ctx.translate(cx, cy); ctx.rotate(rot); ctx.scale(q, q);
      for (let i = 0; i < n; i++) {
        const a0 = (i / n) * Math.PI * 2, a1 = ((i + 0.5) / n) * Math.PI * 2;
        if (st.mode === 'rough') { if (i % 2) continue; WE.renderShapes(ctx, [{ pts: [Math.cos(a0) * r * 0.3, Math.sin(a0) * r * 0.3, Math.cos(a0) * r, Math.sin(a0) * r], closed: false, stroke: 'a', lw: 1.2 }], R({ progress: p })); continue; }
        ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(Math.cos(a0) * r, Math.sin(a0) * r); ctx.lineTo(Math.cos(a1) * r, Math.sin(a1) * r); ctx.closePath();
        ctx.fillStyle = (colors || (project.style === 'motion' || project.style === 'neon' ? [C.rgba(pal.a, 0.1), 'rgba(0,0,0,0)'] : [C.rgba(pal.c, 0.55), C.rgba('#ffffff', 0.18)]))[i % 2]; ctx.fill();
      }
      ctx.restore();
    };
    env.number = (stat, x, y, w, h, start) => {
      const val = parseFloat(String(stat.value).replace(/[^0-9.\-]/g, '')) || 0;
      const from = parseFloat(stat.from || 0) || 0;
      const dec = (String(stat.value).split('.')[1] || '').replace(/[^0-9]/g, '').length;
      const p = env.p(start, 1.6);
      if (p <= 0) return;
      if (!env.still) { env.cue(start, 'typing'); env.cue(start + 1.6, stat.sfx || 'ding'); }
      const v = lerp(from, val, E.outExpo(p));
      const txt = (stat.prefix || '') + v.toLocaleString('en-GB', { minimumFractionDigits: dec, maximumFractionDigits: dec }) + (stat.suffix || '');
      const finalTxt = (stat.prefix || '') + val.toLocaleString('en-GB', { minimumFractionDigits: dec, maximumFractionDigits: dec }) + (stat.suffix || '');
      const lay = fitCached(ctx, finalTxt, w, h, st.head, h, u * 6, 1);
      const sc = 1 + Math.max(0, 1 - Math.abs(p - 1) * 8) * 0.06 + (p >= 1 ? Math.sin(t * 3) * 0.015 : 0);
      ctx.save(); ctx.translate(x + w / 2, y + h / 2); ctx.scale(sc, sc); ctx.translate(-(x + w / 2), -(y + h / 2));
      ctx.font = `${lay.size}px ${st.head}`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      const cx = x + w / 2, cy = y + h / 2;
      if (project.style === 'neon') { ctx.shadowColor = pal.a; ctx.shadowBlur = u * 3; ctx.fillStyle = '#fff'; }
      else if (project.style === 'motion') { const g = ctx.createLinearGradient(cx, cy - lay.size / 2, cx, cy + lay.size / 2); g.addColorStop(0, '#ffffff'); g.addColorStop(1, pal.a); ctx.fillStyle = g; }
      else if (project.style === 'whiteboard') ctx.fillStyle = pal.a;
      else { ctx.lineJoin = 'round'; ctx.lineWidth = lay.size * 0.12; ctx.strokeStyle = '#1d1b26'; ctx.fillStyle = project.style === 'collage' ? pal.a : pal.c; if (project.style !== 'collage') { ctx.fillStyle = '#1d1b26'; ctx.fillText(txt, cx + u * 0.6, cy + u * 0.8); ctx.fillStyle = pal.c; } ctx.strokeText(txt, cx, cy); }
      ctx.fillText(txt, cx, cy);
      ctx.restore();
    };
    env.bubble = (x, y, w, h, tx, ty, start) => {
      const p = env.p(start, st.mode === 'rough' ? 0.9 : 0.45);
      if (p <= 0) return false;
      ctx.save(); env.reveal(p, tx, ty);
      const r = u * 3, bx = clamp(tx, x + r * 1.5, x + w - r * 3);
      const by = ty > y + h ? y + h : y;
      let tail = ty > y + h ? [bx, by - 2, bx + u * 4, by - 2, tx, ty] : [bx, by + 2, bx + u * 4, by + 2, tx, ty];
      if (tx < x || tx > x + w) { const sy = clamp(ty - u * 3, y + r * 1.5, y + h - r * 3), ex = tx < x ? x + 2 : x + w - 2; tail = [ex, sy, ex, sy + u * 5, tx, ty]; }
      const fill = project.style === 'neon' ? 'a' : 'l';
      const tip = WE.renderShapes(ctx, [{ pts: tail, closed: true, fill, stroke: 'ink' }, { pts: WE.Geo.rrectPts(x, y, w, h, r), closed: true, fill, stroke: 'ink' }, ...((st.mode === 'cartoon' || st.mode === 'paper') && tx >= x && tx <= x + w ? [{ pts: [bx + 2, by + (ty > y + h ? -3 : 3), bx + u * 4 - 2, by + (ty > y + h ? -3 : 3)], closed: false, stroke: '#ffffff', lw: 2.2 }] : [])], R({ progress: st.mode === 'rough' || st.mode === 'neon' ? p : 1 }));
      ctx.restore();
      if (p < 1) env.tip(tip, start);
      return true;
    };
    env.device = (cx, cy, size, start, items) => {
      const phone = env.portrait || env.square || env.project.device === 'phone';
      const w = phone ? size * 0.54 : size * 1.5, h = phone ? size : size * 0.92;
      const x = cx - w / 2, y = cy - h / 2;
      const p = env.p(start, st.mode === 'rough' ? 1.2 : 0.7);
      if (p <= 0) return;
      if (!env.still) env.cue(start, 'whoosh');
      ctx.save();
      if (st.mode !== 'rough') { const q = E.outBack(p); ctx.translate(0, (1 - q) * H * 0.5); ctx.globalAlpha *= clamp(p * 3); }
      const dark = project.style === 'motion' || project.style === 'neon';
      const sx = phone ? x + w * 0.06 : x + w * 0.05, sy = phone ? y + h * 0.04 : y + h * 0.05, sw = phone ? w * 0.88 : w * 0.9, sh = phone ? h * 0.92 : h * 0.78;
      const body = [{ pts: WE.Geo.rrectPts(x, y, w, phone ? h : h * 0.88, phone ? w * 0.14 : u * 1.6), closed: true, fill: '#22232e', stroke: 'ink' }];
      if (!phone) body.push({ pts: WE.Geo.rrectPts(x - w * 0.06, y + h * 0.88, w * 1.12, h * 0.07, u), closed: true, fill: '#3a3c4e', stroke: 'ink' });
      body.push({ pts: WE.Geo.rrectPts(sx, sy, sw, sh, phone ? w * 0.09 : u * 0.6), closed: true, fill: dark ? '#0f1424' : '#ffffff', stroke: 'ink' });
      body.push({ pts: WE.Geo.rrectPts(sx, sy, sw, sh * 0.15, phone ? w * 0.09 : u * 0.6), closed: true, fill: 'a', stroke: 'ink' });
      const tip = WE.renderShapes(ctx, body, R({ progress: st.mode === 'rough' || st.mode === 'neon' ? p : 1, lw: st.mode === 'rough' ? 3 : 3.4 }));
      if (p < 1) env.tip(tip, start);
      // app header
      if (p > 0.6) {
        ctx.save(); ctx.globalAlpha *= clamp((p - 0.6) * 3);
        ctx.fillStyle = C.readable(pal.a); ctx.font = `${sh * 0.065}px ${project.style === 'whiteboard' ? F.hand : F.ui}`; ctx.textBaseline = 'middle'; ctx.textAlign = 'left';
        ctx.fillText((brand.name || 'App').slice(0, 22), sx + sw * 0.06, sy + sh * 0.075);
        ctx.restore();
      }
      // cards
      (items || []).slice(0, 4).forEach((label, i) => {
        const cs = start + (st.mode === 'rough' ? 1.3 : 0.7) + i * 0.55;
        const cp = env.p(cs, st.mode === 'rough' ? 0.8 : 0.4);
        if (cp <= 0) return;
        if (!env.still) env.cue(cs + 0.2, i === (items.length - 1) ? 'ding' : 'pop');
        const ch = sh * 0.17, cy2 = sy + sh * 0.2 + i * ch * 1.18, cx2 = sx + sw * 0.06, cw = sw * 0.88;
        ctx.save();
        if (st.mode !== 'rough') { const q = E.outBack(cp); ctx.translate(cx2 + cw / 2, cy2 + ch / 2); ctx.scale(q, q); ctx.translate(-(cx2 + cw / 2), -(cy2 + ch / 2)); }
        const tip2 = WE.renderShapes(ctx, [
          { pts: WE.Geo.rrectPts(cx2, cy2, cw, ch, ch * 0.22), closed: true, fill: dark ? '#1b2238' : '#f3f4f8', stroke: st.mode === 'rough' ? 'ink' : false },
          { pts: WE.Geo.circlePts(cx2 + ch * 0.5, cy2 + ch / 2, ch * 0.28), closed: true, fill: ['a', 'b', 'c', 'a'][i], stroke: st.mode === 'rough' ? 'ink' : false },
        ], R({ mode: st.mode === 'cartoon' || st.mode === 'paper' ? 'clean' : st.mode, progress: st.mode === 'rough' ? cp : 1 }));
        if (cp < 1) env.tip(tip2, cs);
        ctx.fillStyle = dark ? '#e8ecff' : '#1d1b26'; ctx.textBaseline = 'middle'; ctx.textAlign = 'left';
        const fs = Math.min(ch * 0.44, (cw - ch * 1.9) / Math.max(6, String(label).length * 0.5));
        ctx.font = `${fs}px ${project.style === 'whiteboard' ? F.hand : F.ui}`;
        ctx.globalAlpha *= clamp(cp * 2 - 0.3);
        ctx.fillText(String(label).slice(0, 40), cx2 + ch * 0.95, cy2 + ch / 2);
        if (cp >= 1) { const k = E.outBack(env.p(cs + 0.3, 0.3)); ctx.save(); ctx.translate(cx2 + cw - ch * 0.45, cy2 + ch / 2); ctx.scale(k, k); ctx.fillStyle = '#2ecc71'; ctx.beginPath(); ctx.arc(0, 0, ch * 0.24, 0, 7); ctx.fill(); ctx.strokeStyle = '#fff'; ctx.lineWidth = ch * 0.07; ctx.lineCap = 'round'; ctx.beginPath(); ctx.moveTo(-ch * 0.1, 0); ctx.lineTo(-ch * 0.02, ch * 0.08); ctx.lineTo(ch * 0.12, -ch * 0.08); ctx.stroke(); ctx.restore(); }
        ctx.restore();
      });
      ctx.restore();
      return { x, y, w, h };
    };
    return env;
  };

  /* ------------------------------------------------------- backgrounds */
  const drawBackground = (env) => {
    const { ctx, W, H, t, project, scene, pal, u } = env;
    const style = project.style;
    const bg = scene.bg;
    ctx.save();
    if (style === 'whiteboard') {
      ctx.fillStyle = bg || '#fbfaf5'; ctx.fillRect(0, 0, W, H);
      ctx.fillStyle = WE.texture(ctx, 'paper'); ctx.fillRect(0, 0, W, H);
      const r = WE.rng(7); ctx.strokeStyle = 'rgba(80,80,90,0.05)'; ctx.lineWidth = u * 3; ctx.lineCap = 'round';
      for (let i = 0; i < 5; i++) { ctx.beginPath(); const x = r() * W, y = r() * H; ctx.moveTo(x, y); ctx.quadraticCurveTo(x + r() * 200, y - 40, x + 260 * r(), y + 30); ctx.stroke(); }
      const g = ctx.createRadialGradient(W / 2, H / 2, Math.min(W, H) * 0.4, W / 2, H / 2, Math.max(W, H) * 0.75); g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(1, 'rgba(60,50,30,0.12)'); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    } else if (style === 'cartoon') {
      const base = bg || C.shade(pal.b, 0.72);
      const g = ctx.createLinearGradient(0, 0, 0, H); g.addColorStop(0, C.shade(base, 0.25)); g.addColorStop(1, base); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
      const r = WE.rng(3);
      for (let i = 0; i < 7; i++) { const x = ((r() * W + t * (8 + r() * 14)) % (W + 300)) - 150, y = r() * H * 0.55, s = (40 + r() * 70) * u / 7; ctx.fillStyle = 'rgba(255,255,255,0.55)'; ctx.beginPath(); ctx.arc(x, y, s, 0, 7); ctx.arc(x + s * 0.9, y + s * 0.2, s * 0.8, 0, 7); ctx.arc(x - s * 0.9, y + s * 0.25, s * 0.7, 0, 7); ctx.fill(); }
      ctx.fillStyle = C.shade(base, -0.12); ctx.beginPath(); ctx.ellipse(W / 2, H * 1.08, W * 0.85, H * 0.22, 0, 0, 7); ctx.fill();
      ctx.globalAlpha = 0.25; ctx.fillStyle = WE.texture(ctx, 'dots', '#ffffff'); ctx.fillRect(0, 0, W, H);
    } else if (style === 'motion') {
      const g = ctx.createLinearGradient(0, 0, W, H); g.addColorStop(0, bg || '#0a0f1f'); g.addColorStop(1, C.mix(bg || '#0a0f1f', pal.b, 0.35)); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
      [[pal.a, 0.28, 0.25], [pal.b, 0.75, 0.7], [pal.c, 0.85, 0.15]].forEach(([c, fx, fy], i) => { const x = W * fx + Math.sin(t * 0.4 + i) * W * 0.06, y = H * fy + Math.cos(t * 0.35 + i) * H * 0.06, rr = Math.max(W, H) * 0.42; const rg = ctx.createRadialGradient(x, y, 0, x, y, rr); rg.addColorStop(0, C.rgba(c, 0.32)); rg.addColorStop(1, C.rgba(c, 0)); ctx.fillStyle = rg; ctx.fillRect(0, 0, W, H); });
      ctx.globalAlpha = 0.07; ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 1; const gs = u * 8, off = (t * u * 2) % gs;
      for (let x = -gs + off; x < W; x += gs) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke(); }
      for (let y = -gs + off; y < H; y += gs) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke(); }
      ctx.globalAlpha = 1; ctx.fillStyle = WE.texture(ctx, 'grain'); ctx.fillRect(0, 0, W, H);
    } else if (style === 'collage') {
      ctx.fillStyle = bg || '#efe3cc'; ctx.fillRect(0, 0, W, H);
      const r = WE.rng(WE.hash(scene.id || 'c'));
      const cols = [pal.a, pal.b, pal.c, '#ffffff'];
      for (let i = 0; i < 5; i++) {
        ctx.save(); const x = r() * W, y = (i % 2 ? 0.9 : 0.05) * H + r() * H * 0.1; ctx.translate(x, y); ctx.rotate((r() - 0.5) * 0.6);
        ctx.shadowColor = 'rgba(40,25,10,0.25)'; ctx.shadowOffsetY = u * 0.5; ctx.shadowBlur = u; ctx.fillStyle = C.rgba(cols[i % 4], 0.9);
        ctx.beginPath(); const w = W * (0.18 + r() * 0.2), h = H * (0.1 + r() * 0.12); ctx.moveTo(-w / 2, -h / 2);
        for (let k = 0; k <= 12; k++) ctx.lineTo(-w / 2 + (w * k) / 12, -h / 2 + (r() - 0.5) * u * 1.2);
        ctx.lineTo(w / 2, h / 2); for (let k = 12; k >= 0; k--) ctx.lineTo(-w / 2 + (w * k) / 12, h / 2 + (r() - 0.5) * u * 1.2);
        ctx.fill(); ctx.restore();
      }
      ctx.globalAlpha = 0.18; ctx.fillStyle = WE.texture(ctx, 'halftone', '#7a5c3a'); ctx.fillRect(W * 0.6, 0, W * 0.4, H * 0.35);
      ctx.globalAlpha = 1; ctx.fillStyle = WE.texture(ctx, 'paper'); ctx.fillRect(0, 0, W, H);
    } else if (style === 'comic') {
      ctx.fillStyle = bg || C.shade(pal.c, 0.1); ctx.fillRect(0, 0, W, H);
      ctx.save(); ctx.translate(W / 2, H / 2); ctx.rotate(t * 0.05);
      for (let i = 0; i < 24; i++) { const a0 = (i / 24) * Math.PI * 2, a1 = a0 + Math.PI / 24; ctx.fillStyle = i % 2 ? 'rgba(255,255,255,0.28)' : 'rgba(255,255,255,0)'; ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(Math.cos(a0) * W, Math.sin(a0) * W); ctx.lineTo(Math.cos(a1) * W, Math.sin(a1) * W); ctx.fill(); }
      ctx.restore();
      ctx.globalAlpha = 0.22; ctx.fillStyle = WE.texture(ctx, 'halftone', '#b8282b'); ctx.fillRect(0, 0, W, H); ctx.globalAlpha = 1;
    } else if (style === 'neon') {
      const g = ctx.createLinearGradient(0, 0, 0, H); g.addColorStop(0, bg || '#0d0221'); g.addColorStop(0.62, '#2a0845'); g.addColorStop(1, '#0d0221'); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
      const hy = H * 0.64, sr = Math.min(W, H) * 0.22;
      const sg = ctx.createLinearGradient(0, hy - sr * 2, 0, hy); sg.addColorStop(0, '#ffd319'); sg.addColorStop(1, '#ff2975'); ctx.fillStyle = sg;
      ctx.save(); ctx.beginPath(); ctx.arc(W / 2, hy, sr, Math.PI, 0); ctx.fill(); ctx.globalCompositeOperation = 'destination-out';
      for (let i = 0; i < 6; i++) ctx.fillRect(W / 2 - sr, hy - sr * 0.12 - i * sr * 0.15, sr * 2, sr * 0.035 * (6 - i) * 0.5);
      ctx.restore();
      ctx.strokeStyle = C.rgba('#ff2bd6', 0.75); ctx.lineWidth = 2; ctx.shadowColor = '#ff2bd6'; ctx.shadowBlur = 8;
      for (let i = -14; i <= 14; i++) { ctx.beginPath(); ctx.moveTo(W / 2 + i * W * 0.012, hy); ctx.lineTo(W / 2 + i * W * 0.16, H); ctx.stroke(); }
      const off = (t * 0.6) % 1;
      for (let k = 0; k < 10; k++) { const z = (k + off) / 10, y = hy + (H - hy) * z * z; ctx.globalAlpha = z; ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke(); }
      ctx.globalAlpha = 1; ctx.shadowBlur = 0;
      const r = WE.rng(9); ctx.fillStyle = '#ffffff'; for (let i = 0; i < 60; i++) { ctx.globalAlpha = 0.3 + 0.7 * Math.abs(Math.sin(t * 2 + i)); ctx.fillRect(r() * W, r() * hy * 0.9, 1.6, 1.6); }
    } else { ctx.fillStyle = bg || '#fff'; ctx.fillRect(0, 0, W, H); }
    ctx.restore();
  };

  /* ------------------------------------------------------------- shots */
  const pick = (arr, i, fallback) => (arr && arr[i]) || fallback;
  const SHOTS = {
    hook(e) {
      const { W, H, u, scene: s } = e, por = e.portrait;
      const tw = por ? W * 0.86 : W * 0.54, tx = por ? W * 0.07 : W * 0.06, ty = por ? H * 0.1 : H * 0.16;
      if (e.project.style !== 'whiteboard') e.burst(por ? W / 2 : W * 0.34, por ? H * 0.27 : H * 0.45, Math.max(W, H) * 0.5, 0.05, e.project.style === 'motion' || e.project.style === 'neon' ? [C.rgba(e.pal.a, 0.08), 'rgba(0,0,0,0)'] : null);
      e.cue(0.05, 'whoosh');
      const lay = e.text(s.headline, tx, ty, tw, por ? H * 0.3 : H * 0.48, { head: true, size: u * (por ? 13 : 15), start: 0.15 });
      const subY = lay ? lay.y + lay.h + u * 3 : ty + H * 0.5;
      e.text(s.sub, tx, subY, tw, H * 0.18, { size: u * 5, start: 1.2 });
      e.char(por ? W * 0.5 : W * 0.79, H * 0.94, por ? H * 0.44 : H * 0.7, 0.6, { mood: s.mood || 'shocked', pose: s.pose || 'shrug' });
      const spots = por ? [[0.16, 0.52], [0.84, 0.5], [0.2, 0.74]] : [[0.66, 0.2], [0.95, 0.4], [0.65, 0.72]];
      (s.props || []).slice(0, 3).forEach((p, i) => e.prop(p, W * spots[i][0], H * spots[i][1], u * (por ? 16 : 13), 1.3 + i * 0.35, { rot: (i - 1) * 0.12 }));
    },
    talk(e) {
      const { W, H, u, scene: s } = e, por = e.portrait;
      const ch = e.char(por ? W * 0.5 : W * 0.24, H * 0.95, por ? H * 0.46 : H * 0.72, 0.1, { mood: s.mood || 'happy', pose: s.pose || 'wave' });
      const bx = por ? W * 0.07 : W * 0.42, by = por ? H * 0.06 : H * 0.1, bw = por ? W * 0.86 : W * 0.52, bh = por ? H * 0.3 : H * 0.5;
      const head = ch ? ch.head : [W * 0.24, H * 0.3];
      if (e.bubble(bx, by, bw, bh, por ? W * 0.52 : head[0] + u * 7, por ? H * 0.42 : head[1] + u * 2, 0.5)) e.text(s.headline, bx + u * 3, by + u * 2.5, bw - u * 6, bh - u * 5, { head: e.project.style !== 'whiteboard', size: u * 9, start: 0.8, valign: 'middle', align: 'center', look: e.project.style === 'whiteboard' ? undefined : { color: e.project.style === 'neon' ? '#ffffff' : '#1d1b26', stroke: null, shadow: e.project.style === 'neon' ? e.pal.a : null, shadowBlur: e.project.style === 'neon' ? u : 0, shadowX: 0, shadowY: 0 } });
      e.text(s.sub, por ? W * 0.07 : W * 0.42, por ? H * 0.38 : H * 0.66, por ? W * 0.86 : W * 0.52, H * 0.14, { size: u * 4.6, start: 1.6, align: por ? 'center' : 'left' });
      (s.props || []).slice(0, 2).forEach((p, i) => e.prop(p, por ? W * (0.2 + i * 0.6) : W * (0.62 + i * 0.22), por ? H * 0.52 : H * 0.84, u * 12, 1.8 + i * 0.3));
    },
    chaos(e) {
      const { W, H, u, t, scene: s } = e, por = e.portrait;
      e.text(s.headline, W * 0.06, H * 0.05, W * 0.88, por ? H * 0.18 : H * 0.2, { head: true, size: u * 10, align: 'center', start: 0.1 });
      const cx = W * 0.5, cy = por ? H * 0.62 : H * 0.62;
      e.char(cx, H * 0.97, por ? H * 0.42 : H * 0.6, 0.3, { mood: s.mood || 'stressed', pose: s.pose || 'stressed' });
      const props = (s.props && s.props.length ? s.props : ['chat', 'bell', 'doc', 'clock', 'warning']).slice(0, 6);
      e.cue(0.7, 'alert');
      props.forEach((p, i) => {
        const a = Math.PI * 1.02 + ((i + 0.5) / props.length) * Math.PI * 0.96 + Math.sin(t * 0.9 + i) * 0.07, rx = por ? W * 0.36 : W * 0.34, ry = por ? H * 0.2 : H * 0.36;
        const shake = Math.sin(t * 30 + i) * u * 0.4;
        const x = cx + Math.cos(a) * rx + shake, y = cy + H * 0.08 + Math.sin(a) * ry;
        const st0 = 0.6 + i * 0.3;
        e.prop(p, x, y, u * (por ? 15 : 12), st0, { float: false, rot: Math.sin(t * 3 + i) * 0.15 });
        const bp = e.p(st0 + 0.5, 0.3);
        if (bp > 0 && e.project.style !== 'whiteboard') {
          const n = 3 + Math.floor(WE.rng(i + 5)() * 96), q = E.outBack(bp);
          e.ctx.save(); e.ctx.translate(x + u * 5, y - u * 5); e.ctx.scale(q, q);
          e.ctx.fillStyle = '#ff3b30'; e.ctx.beginPath(); e.ctx.arc(0, 0, u * 2.6, 0, 7); e.ctx.fill(); e.ctx.strokeStyle = '#fff'; e.ctx.lineWidth = u * 0.4; e.ctx.stroke();
          e.ctx.fillStyle = '#fff'; e.ctx.font = `${u * 2.6}px ${F.ui}`; e.ctx.textAlign = 'center'; e.ctx.textBaseline = 'middle'; e.ctx.fillText(String(n), 0, u * 0.1); e.ctx.restore();
        }
      });
      e.text(s.sub, W * 0.1, por ? H * 0.22 : H * 0.24, W * 0.8, H * 0.1, { size: u * 4.4, align: 'center', start: 2.2 });
    },
    stat(e) {
      const { W, H, u, scene: s } = e, por = e.portrait;
      e.text(s.headline, W * 0.06, H * 0.06, W * 0.88, H * 0.17, { head: true, size: u * 8, align: 'center', start: 0.05 });
      const nx = por ? W * 0.06 : W * 0.2, ny = por ? H * 0.26 : H * 0.27, nw = por ? W * 0.88 : W * 0.5, nh = por ? H * 0.2 : H * 0.38;
      if (e.project.style !== 'whiteboard') e.burst(nx + nw / 2, ny + nh / 2, nh * 1.2, 0.3);
      e.number(s.stat || { value: 100 }, nx, ny, nw, nh, 0.4);
      e.text((s.stat && s.stat.label) || '', nx, ny + nh + u * 1.5, nw, H * 0.14, { size: u * 5.5, align: 'center', start: 1.9 });
      if (s.props && s.props[0]) e.prop(s.props[0], por ? W * 0.22 : W * 0.12, por ? H * 0.66 : H * 0.48, u * (por ? 18 : 14), 0.9);
      e.char(por ? W * 0.68 : W * 0.84, H * 0.96, por ? H * 0.36 : H * 0.6, 1.0, { mood: s.mood || 'shocked', pose: s.pose || 'pointLeft' });
      e.text(s.sub, W * 0.1, por ? H * 0.88 : H * 0.86, por ? W * 0.5 : W * 0.6, H * 0.1, { size: u * 4, start: 2.4, align: 'center' });
    },
    split(e) {
      const { ctx, W, H, u, t, scene: s, pal } = e, por = e.portrait;
      const wipe = E.inOutCubic(e.p(1.4, 0.9));
      e.cue(1.4, 'swoosh');
      // left (before) muted, right (after) branded
      ctx.save();
      ctx.fillStyle = e.project.style === 'motion' || e.project.style === 'neon' ? 'rgba(0,0,0,0.35)' : 'rgba(40,40,60,0.08)';
      if (por) ctx.fillRect(0, H * 0.2, W, H * 0.4); else ctx.fillRect(0, H * 0.2, W / 2, H * 0.8);
      ctx.fillStyle = C.rgba(pal.a, e.project.style === 'whiteboard' ? 0.1 : 0.18);
      if (por) ctx.fillRect(0, H * 0.6, W * wipe, H * 0.4); else ctx.fillRect(W / 2, H * 0.2, (W / 2) * wipe, H * 0.8);
      ctx.restore();
      e.text(s.headline, W * 0.06, H * 0.04, W * 0.88, H * 0.14, { head: true, size: u * 8, align: 'center', start: 0.05 });
      const L = pick(s.bullets, 0, 'Before'), R = pick(s.bullets, 1, 'After');
      if (por) {
        e.text(L, W * 0.06, H * 0.21, W * 0.5, H * 0.08, { size: u * 6, start: 0.4 });
        e.char(W * 0.72, H * 0.6, H * 0.34, 0.5, { mood: 'stressed', pose: 'stressed', talking: false });
        if (s.props && s.props[0]) e.prop(s.props[0], W * 0.24, H * 0.42, u * 18, 0.9);
        e.text(R, W * 0.06, H * 0.62, W * 0.5, H * 0.08, { size: u * 6, start: 2.2 });
        e.char(W * 0.72, H * 0.99, H * 0.34, 2.3, { mood: 'happy', pose: 'celebrate' });
        if (s.props && s.props[1]) e.prop(s.props[1], W * 0.24, H * 0.82, u * 18, 2.6);
        e.arrow(W * 0.24, H * 0.52, W * 0.24, H * 0.7, 2.0, { bend: -0.4 });
      } else {
        e.text(L, W * 0.04, H * 0.2, W * 0.42, H * 0.1, { size: u * 6.5, align: 'center', start: 0.4 });
        e.char(W * 0.32, H * 0.97, H * 0.56, 0.5, { mood: 'stressed', pose: 'stressed', talking: false });
        if (s.props && s.props[0]) e.prop(s.props[0], W * 0.12, H * 0.58, u * 15, 0.9);
        e.text(R, W * 0.54, H * 0.2, W * 0.42, H * 0.1, { size: u * 6.5, align: 'center', start: 2.2 });
        e.char(W * 0.82, H * 0.97, H * 0.56, 2.3, { mood: 'happy', pose: 'celebrate' });
        if (s.props && s.props[1]) e.prop(s.props[1], W * 0.62, H * 0.58, u * 15, 2.6);
        e.arrow(W * 0.44, H * 0.42, W * 0.56, H * 0.42, 2.0, { bend: 0.3 });
      }
      if (e.p(2.3, 0.1) > 0 && e.project.style !== 'whiteboard') WE.confetti(ctx, por ? W * 0.7 : W * 0.82, H * 0.5, t - 2.5, { n: 40, colors: [pal.a, pal.b, pal.c, '#fff'], spread: u / 9, seed: 4 });
    },
    reveal(e) {
      const { W, H, u, t, scene: s, pal } = e, por = e.portrait;
      const hl = e.text(s.headline, por ? W * 0.06 : W * 0.05, por ? H * 0.05 : H * 0.12, por ? W * 0.88 : W * 0.34, por ? H * 0.16 : H * 0.42, { head: true, size: u * 9, start: 0.05, align: por ? 'center' : 'left' });
      e.text(s.sub, por ? W * 0.08 : W * 0.05, por ? H * 0.2 : (hl ? hl.y + hl.h + u * 3 : H * 0.56), por ? W * 0.84 : W * 0.33, H * 0.22, { size: u * 4.6, start: 0.9, align: por ? 'center' : 'left' });
      const dx = por ? W * 0.5 : W * 0.6, dy = por ? H * 0.55 : H * 0.52, size = por ? H * 0.42 : H * 0.46;
      const items = (s.bullets && s.bullets.length ? s.bullets : ['Sorted', 'Synced', 'Smiling']);
      const dev = e.device(dx, dy, size, 0.4, items);
      if (dev && e.project.style !== 'whiteboard' && t > 2.4) for (let i = 0; i < 6; i++) { const a = i * 1.05 + t * 0.6, rr = size * 0.62; WE.sparkle(e.ctx, dx + Math.cos(a) * rr * (por ? 0.7 : 1.1), dy + Math.sin(a) * rr * 0.75, u * (1.2 + Math.abs(Math.sin(t * 3 + i)) * 1.4), i % 2 ? pal.c : '#ffffff', t); }
      e.char(por ? W * 0.84 : W * 0.9, H * 0.97, por ? H * 0.3 : H * 0.54, 1.1, { mood: s.mood || 'excited', pose: s.pose || 'pointLeft' });
      e.cue(2.6, 'sparkle');
    },
    steps(e) {
      const { W, H, u, scene: s, pal } = e, por = e.portrait;
      e.text(s.headline, W * 0.06, H * 0.05, W * 0.88, H * 0.17, { head: true, size: u * 8.5, align: 'center', start: 0.05 });
      const items = (s.bullets && s.bullets.length ? s.bullets : ['Sign up', 'Add details', 'Done']).slice(0, 3);
      const props = s.props && s.props.length ? s.props : ['phone', 'clipboard', 'trophy'];
      items.forEach((label, i) => {
        const st0 = 0.6 + i * 1.0;
        const cx = por ? W * 0.5 : W * (0.17 + i * 0.29), cy = por ? H * (0.33 + i * 0.22) : H * 0.46;
        const sz = por ? u * 15 : u * 18;
        const px = por ? W * 0.25 : cx, py = cy;
        e.prop(props[i % props.length], px, py, sz, st0);
        // number badge
        const bp = e.p(st0 + 0.2, 0.4);
        if (bp > 0) {
          const bx = px - sz * 0.55, by = py - sz * 0.5, q = e.project.style === 'whiteboard' ? 1 : E.outBack(bp);
          e.ctx.save(); e.ctx.translate(bx, by); e.ctx.scale(q, q);
          WE.renderShapes(e.ctx, [{ pts: WE.Geo.circlePts(0, 0, u * 4), closed: true, fill: 'a', stroke: 'ink' }], e.R({ progress: e.project.style === 'whiteboard' ? bp : 1 }));
          e.ctx.fillStyle = e.project.style === 'whiteboard' ? '#1f1f24' : C.readable(pal.a); e.ctx.font = `${u * 4.6}px ${e.st.head}`; e.ctx.textAlign = 'center'; e.ctx.textBaseline = 'middle'; e.ctx.globalAlpha *= clamp(bp * 2); e.ctx.fillText(String(i + 1), 0, u * 0.3); e.ctx.restore();
        }
        if (por) e.text(label, W * 0.4, cy - u * 6, W * 0.54, u * 12, { size: u * 6, start: st0 + 0.3, valign: 'middle' });
        else e.text(label, cx - W * 0.12, cy + sz * 0.62, W * 0.24, H * 0.2, { size: u * 5.4, start: st0 + 0.3, align: 'center' });
        if (i < items.length - 1) { if (por) e.arrow(W * 0.25, cy + sz * 0.62, W * 0.25, cy + H * 0.22 - sz * 0.62, st0 + 0.7, { bend: 0.4, dur: 0.4 }); else e.arrow(cx + sz * 0.62, cy - u * 3, cx + W * 0.3 - sz * 0.62, cy - u * 3, st0 + 0.7, { bend: 0.25, dur: 0.4 }); }
      });
      if (!por) e.char(W * 0.94, H * 0.99, H * 0.3, 3.6, { mood: 'happy', pose: 'thumbsUp' });
    },
    proof(e) {
      const { ctx, W, H, u, t, scene: s, pal } = e, por = e.portrait;
      e.text(s.headline, W * 0.06, H * 0.05, W * 0.88, H * 0.15, { head: true, size: u * 8, align: 'center', start: 0.05 });
      const cx = por ? W * 0.07 : W * 0.3, cy = por ? H * 0.24 : H * 0.24, cw = por ? W * 0.86 : W * 0.62, chh = por ? H * 0.4 : H * 0.56;
      if (e.panel(cx, cy, cw, chh, 0.3, e.project.style === 'neon' ? '#1a0b33' : e.project.style === 'motion' ? '#141a30' : 'l')) {
        for (let i = 0; i < 5; i++) { const sp = e.p(0.9 + i * 0.15, 0.35); if (sp <= 0) continue; if (i === 0) e.cue(0.9, 'sparkle'); ctx.save(); const sx = cx + u * 6 + i * u * 6.5, sy = cy + u * 6, q = e.project.style === 'whiteboard' ? 1 : E.outBack(sp); ctx.translate(sx, sy); ctx.scale(q, q); WE.Cast.drawProp(ctx, 'star', 0, 0, u * 5.6, e.R({ progress: e.project.style === 'whiteboard' ? sp : 1 })); ctx.restore(); }
        const dark = e.project.style === 'motion' || e.project.style === 'neon';
        e.text('“' + (s.quote || s.sub || 'Life-changing.') + '”', cx + u * 4, cy + u * 11, cw - u * 8, chh - u * 22, { size: u * 6, start: 1.4, font: e.project.style === 'whiteboard' ? F.hand : F.serif, look: { color: dark ? '#ffffff' : '#1d1b26', stroke: null, shadow: null } });
        e.text('— ' + (s.author || 'A very happy customer'), cx + u * 4, cy + chh - u * 9, cw - u * 8, u * 6, { size: u * 3.8, start: 2.4, font: e.project.style === 'whiteboard' ? F.hand : F.ui, look: { color: dark ? '#c9d2ff' : '#55526a', stroke: null, shadow: null } });
      }
      e.char(por ? W * 0.5 : W * 0.15, H * 0.97, por ? H * 0.32 : H * 0.6, 0.5, { mood: s.mood || 'proud', pose: s.pose || 'thumbsUp' });
      void t;
    },
    cta(e) {
      const { ctx, W, H, u, t, scene: s, pal, brand } = e, por = e.portrait;
      if (e.project.style !== 'whiteboard') e.burst(W / 2, por ? H * 0.32 : H * 0.42, Math.max(W, H) * 0.7, 0.05, e.project.style === 'neon' || e.project.style === 'motion' ? [C.rgba(pal.a, 0.12), 'rgba(0,0,0,0)'] : null);
      e.cue(0.1, 'tada'); e.cue(0.9, 'cheer');
      const lay = e.text(brand.name || 'Your Brand', W * 0.06, por ? H * 0.12 : H * 0.1, W * 0.88, por ? H * 0.16 : H * 0.24, { head: true, size: u * 17, align: 'center', start: 0.1, effect: e.project.style === 'whiteboard' ? 'hand' : e.project.style === 'neon' ? 'type' : 'slam' });
      const hy = lay ? lay.y + lay.h + u * 2 : H * 0.36;
      e.text(s.headline, W * 0.1, hy, W * 0.8, por ? H * 0.16 : H * 0.18, { size: u * 6.5, align: 'center', start: 0.7 });
      // button
      const bp = e.p(1.3, 0.5);
      if (bp > 0) {
        e.cue(1.3, 'pop');
        const label = (s.cta || 'Get started free') + '  →';
        ctx.save(); ctx.font = `${u * 5}px ${e.st.head}`;
        const bw = Math.min(W * 0.8, ctx.measureText(label).width + u * 10), bh = u * 10.5;
        const bx = W / 2 - bw / 2, by = por ? H * 0.52 : H * 0.62;
        const pulse = 1 + Math.sin(t * 5) * 0.03 * (bp >= 1 ? 1 : 0), q = e.project.style === 'whiteboard' ? 1 : E.outBack(bp) * pulse;
        ctx.translate(W / 2, by + bh / 2); ctx.scale(q, q); ctx.translate(-W / 2, -(by + bh / 2));
        const tip = WE.renderShapes(ctx, [{ pts: WE.Geo.rrectPts(bx, by, bw, bh, bh / 2), closed: true, fill: 'a', stroke: 'ink' }], e.R({ progress: e.project.style === 'whiteboard' || e.project.style === 'neon' ? bp : 1 }));
        if (bp < 1) e.tip(tip, 1.3);
        ctx.fillStyle = e.project.style === 'whiteboard' ? '#1f1f24' : C.readable(pal.a); ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.globalAlpha *= clamp(bp * 2 - 0.5);
        if (e.project.style === 'neon') { ctx.shadowColor = '#fff'; ctx.shadowBlur = u; }
        ctx.fillText(label, W / 2, by + bh / 2 + u * 0.3);
        ctx.restore();
        e.text(s.url || brand.url || '', W * 0.1, by + bh + u * 2.5, W * 0.8, u * 7, { size: u * 4.4, align: 'center', start: 1.7, font: F.ui, effect: 'type' });
        if (s.qr && (s.url || brand.url)) { const qs = u * 15, qp = e.p(2, 0.4); if (qp > 0) { ctx.save(); ctx.globalAlpha *= qp; WE.QR.draw(ctx, /^https?:/.test(s.url || brand.url) ? (s.url || brand.url) : 'https://' + (s.url || brand.url), por ? W / 2 - qs / 2 : W * 0.06, por ? H * 0.72 : H * 0.62, qs); ctx.restore(); } }
      }
      e.char(por ? W * 0.8 : W * 0.86, H * 0.98, por ? H * 0.26 : H * 0.48, 0.5, { mood: 'excited', pose: 'celebrate' });
      if (e.project.style !== 'whiteboard') { WE.confetti(ctx, W * 0.3, H * 0.9, t - 0.8, { n: 60, colors: [pal.a, pal.b, pal.c, '#fff'], spread: u / 8, seed: 11 }); WE.confetti(ctx, W * 0.7, H * 0.9, t - 1.0, { n: 60, colors: [pal.a, pal.b, pal.c, '#fff'], spread: u / 8, seed: 12 }); }
    },
  };

  /* -------------------------------------------------------- frame render */
  const transitionMask = (ctx, kind, p, W, H, t) => {
    ctx.beginPath();
    if (kind === 'iris') { ctx.arc(W / 2, H / 2, Math.hypot(W, H) * 0.55 * E.inOutCubic(p), 0, 7); }
    else if (kind === 'panel') { const x = (W + H) * E.inOutCubic(p); ctx.moveTo(0, 0); ctx.lineTo(x, 0); ctx.lineTo(x - H * 0.6, H); ctx.lineTo(0, H); }
    else ctx.rect(0, 0, W, H);
    void t;
  };
  /**
   * Draw one frame of a scene. Returns { tips, cues }.
   * o: { speaking, still, captions:{text, progress}, brand bug }
   */
  const renderScene = (ctx, project, sceneIdx, t, W, H, o = {}) => {
    const scene = project.scenes[sceneIdx];
    if (!scene) return { tips: [], cues: [] };
    const env = makeEnv(ctx, project, scene, t, W, H, o);
    drawBackground(env);
    ctx.save();
    // camera
    const cam = scene.camera || (scene.shot === 'chaos' ? 'shake' : 'push');
    if (project.style !== 'whiteboard') {
      const k = cam === 'static' ? 0 : clamp(t / (env.dur + 2));
      const sc = 1 + (cam === 'push' ? 0.045 * E.inOutSine(k) : cam === 'pull' ? 0.05 * (1 - k) : 0);
      ctx.translate(W / 2, H / 2); ctx.scale(sc, sc); ctx.translate(-W / 2, -H / 2);
      if (cam === 'shake') ctx.translate(Math.sin(t * 37) * env.u * 0.35, Math.cos(t * 29) * env.u * 0.3);
    }
    try { (SHOTS[scene.shot] || SHOTS.hook)(env); } catch (err) { if (root.console) console.warn('[WorldEngine] shot error', err); }
    ctx.restore();
    if (project.style === 'comic') { ctx.save(); ctx.strokeStyle = '#111'; ctx.lineWidth = env.u * 1.4; ctx.strokeRect(env.u * 1.2, env.u * 1.2, W - env.u * 2.4, H - env.u * 2.4); ctx.restore(); }
    return env;
  };
  const drawCaptions = (ctx, project, text, prog, W, H) => {
    const words = String(text || '').replace(/\[[^\]]*\]/g, ' ').replace(/\*/g, '').split(/\s+/).filter(Boolean);
    if (!words.length || prog < 0 || prog > 1.15) return;
    const u = Math.min(W, H) / 100, idx = Math.min(words.length - 1, Math.floor(clamp(prog) * words.length));
    const chunk = 7, start = Math.floor(idx / chunk) * chunk, seg = words.slice(start, start + chunk);
    const style = project.style;
    const font = style === 'whiteboard' ? F.hand : style === 'comic' ? F.comic : style === 'neon' ? F.sign : F.ui;
    const size = u * (style === 'whiteboard' ? 5.4 : 4.2);
    ctx.save(); ctx.font = `${size}px ${font}`; ctx.textBaseline = 'middle';
    const space = ctx.measureText(' ').width, widths = seg.map((w) => ctx.measureText(w).width);
    let total = widths.reduce((a, b) => a + b, 0) + space * (seg.length - 1);
    if (total > W * 0.62) { const k = (W * 0.62) / total; ctx.font = `${size * k}px ${font}`; for (let i = 0; i < widths.length; i++) widths[i] *= k; total *= k; }
    const bx = W / 2 - total / 2 - u * 2.5, by = H - u * 11, bw = total + u * 5, bh = size * 1.7;
    ctx.fillStyle = style === 'comic' ? '#fff35c' : style === 'whiteboard' ? 'rgba(255,255,255,0.85)' : 'rgba(10,10,20,0.72)';
    WE.roundRect(ctx, bx, by, bw, bh, style === 'comic' ? 4 : bh / 2); ctx.fill();
    if (style === 'comic') { ctx.strokeStyle = '#111'; ctx.lineWidth = 3; ctx.stroke(); }
    let x = W / 2 - total / 2;
    const base = style === 'comic' || style === 'whiteboard' ? '#1d1b26' : '#ffffff';
    seg.forEach((w, i) => { const on = start + i === idx; ctx.fillStyle = on ? (style === 'comic' ? '#e0182d' : (project.brand && project.brand.c) || '#ffd23f') : base; if (on && style !== 'comic' && style !== 'whiteboard') { ctx.save(); ctx.translate(x + widths[i] / 2, by + bh / 2); ctx.scale(1.08, 1.08); ctx.fillText(w, -widths[i] / 2, 0); ctx.restore(); } else ctx.fillText(w, x, by + bh / 2); x += widths[i] + space; });
    ctx.restore();
  };
  const drawBug = (ctx, project, W, H, progress) => {
    const u = Math.min(W, H) / 100, b = project.brand || {};
    ctx.save();
    ctx.font = `${u * 2.6}px ${F.ui}`; const label = b.name || '';
    if (label && project.showBrand !== false) {
      const w = ctx.measureText(label).width + u * 4;
      const by = H - u * 7.4; ctx.globalAlpha = 0.85;
      ctx.fillStyle = project.style === 'motion' || project.style === 'neon' ? 'rgba(255,255,255,0.14)' : 'rgba(255,255,255,0.7)'; WE.roundRect(ctx, u * 2.2, by, w, u * 4.6, u * 2.3); ctx.fill();
      ctx.fillStyle = project.style === 'motion' || project.style === 'neon' ? '#fff' : '#1d1b26'; ctx.textBaseline = 'middle'; ctx.fillText(label, u * 4.2, by + u * 2.4); ctx.globalAlpha = 1;
    }
    if (progress != null) { ctx.fillStyle = 'rgba(0,0,0,0.12)'; ctx.fillRect(0, H - u * 0.7, W, u * 0.7); ctx.fillStyle = b.a || '#ff3d7f'; ctx.fillRect(0, H - u * 0.7, W * progress, u * 0.7); }
    ctx.restore();
  };

  /* --------------------------------------------------------------- player */
  class Player {
    constructor(canvas, opts = {}) {
      this.canvas = canvas; this.ctx = canvas.getContext('2d');
      this.opts = opts; this.clips = opts.clips || new Map();
      this.idx = 0; this.t = 0; this.playing = false; this.hand = null; this.trans = null;
      this.quality = 1;
    }
    setProject(p) { this.project = p; this.resize(); }
    dims() { const [w, h] = ASPECTS[this.project.aspect] || ASPECTS['16:9']; return [Math.round(w * this.quality), Math.round(h * this.quality)]; }
    resize() { const [w, h] = this.dims(); if (this.canvas.width !== w || this.canvas.height !== h) { this.canvas.width = w; this.canvas.height = h; WE.clearTextCache(); } }
    voiceEst(i) { const s = this.project.scenes[i]; if (!s) return 0; const clip = this.clips.get(s.id); if (clip && clip.duration) return clip.duration; if (this.project.voice && this.project.voice.enabled === false) return 0; return WE.Voice.estimate(s.narration || '', s.voice || (this.project.voice && this.project.voice.preset) || 'narrator', this.project.voice && this.project.voice.tune); }
    sceneLen(i) { const s = this.project.scenes[i]; return s ? Math.max(s.duration || 5, this.voiceEst(i) + 0.9) : 0; }
    totalLen() { return this.project.scenes.reduce((a, _, i) => a + this.sceneLen(i), 0); }
    heroTime(i) { return Math.max(3.8, this.sceneLen(i) - 0.2); }
    /** Draw a still frame (editing preview). */
    still(i = this.idx, t = null) {
      this.resize();
      const [W, H] = this.dims();
      this.ctx.save(); this.ctx.clearRect(0, 0, W, H);
      renderScene(this.ctx, this.project, i, t == null ? this.heroTime(i) : t, W, H, { still: true });
      drawBug(this.ctx, this.project, W, H, null);
      this.ctx.restore();
    }
    /** Render a scene thumbnail onto another canvas. */
    thumb(i, canvas) {
      const [W, H] = ASPECTS[this.project.aspect] || ASPECTS['16:9'];
      const c = canvas.getContext('2d'); const sc = canvas.width / W;
      c.save(); c.setTransform(sc, 0, 0, sc, 0, 0); renderScene(c, this.project, i, this.heroTime(i), W, H, { still: true }); c.restore();
    }
    async play(from = this.idx) {
      if (this.playing) this.stop(true);
      WE.Audio.ensure();
      this.playing = true; this.idx = from; this.trans = null;
      const music = this.project.music || {};
      WE.Audio.setMusicVolume(music.volume == null ? 0.55 : music.volume);
      WE.Audio.startMusic(music.genre || 'none', WE.hash(this.project.title || 'x'));
      this._startScene(from);
      this.last = performance.now();
      const loop = (now) => { if (!this.playing) return; const dt = Math.min(0.1, (now - this.last) / 1000); this.last = now; this._step(dt); this.raf = requestAnimationFrame(loop); };
      this.raf = requestAnimationFrame(loop);
    }
    _startScene(i) {
      this.idx = i; this.t = 0; this.speechDone = false; this.sceneToken = (this.sceneToken || 0) + 1;
      const tok = this.sceneToken, s = this.project.scenes[i];
      if (this.opts.onScene) this.opts.onScene(i);
      if (s.sfx) WE.Audio.sfx(s.sfx, 0.05);
      const clip = this.clips.get(s.id);
      const v = this.project.voice || {};
      const done = () => { if (tok === this.sceneToken) this.speechDone = true; };
      setTimeout(() => {
        if (tok !== this.sceneToken || !this.playing) return;
        if (clip && clip.blob) { this.clipPlaying = true; WE.Audio.playClip(clip.blob, clip.fx || 'none').then(() => { this.clipPlaying = false; done(); }).catch(() => { this.clipPlaying = false; done(); }); this._clipCues(s, clip); }
        else if (v.enabled !== false && s.narration) WE.Voice.speak(s.narration, { preset: s.voice || v.preset || 'narrator', tune: v.tune, voiceURI: v.voiceURI, audio: WE.Audio }).then(done);
        else done();
      }, 250);
    }
    _clipCues(s, clip) { // inline [sfx:x] cues positioned proportionally for pre-rendered narration
      const txt = String(s.narration || ''), clean = txt.replace(/\[[^\]]*\]/g, '');
      const re = /\[sfx:\s*(\w+)\]/g; let m;
      while ((m = re.exec(txt))) { const pos = txt.slice(0, m.index).replace(/\[[^\]]*\]/g, '').length / Math.max(1, clean.length); const tok = this.sceneToken; setTimeout(() => { if (tok === this.sceneToken && this.playing) WE.Audio.sfx(m[1]); }, pos * (clip.duration || 3) * 1000); }
    }
    _step(dt) {
      const prevT = this.t; this.t += dt;
      const len = this.sceneLen(this.idx);
      const [W, H] = this.dims();
      const ctx = this.ctx;
      const speaking = !!(WE.Voice.speaking || this.clipPlaying);
      ctx.save(); ctx.clearRect(0, 0, W, H);
      let env;
      const TR = 0.65;
      if (this.trans && this.t < TR) {
        const p = this.t / TR, kind = (STYLES[this.project.style] || STYLES.cartoon).transition;
        ctx.save();
        if (kind === 'pan') ctx.translate(-W * E.inOutCubic(p), 0);
        if (kind === 'slide') { ctx.translate(W / 2, H / 2); ctx.scale(1 - 0.1 * p, 1 - 0.1 * p); ctx.translate(-W / 2, -H / 2); ctx.globalAlpha = 1 - p * 0.5; }
        renderScene(ctx, this.project, this.trans.from, this.trans.t, W, H, { still: true });
        ctx.restore();
        ctx.save();
        if (kind === 'pan') ctx.translate(W * (1 - E.inOutCubic(p)), 0);
        else if (kind === 'slide') ctx.translate(W * (1 - E.outCubic(p)), 0);
        else if (kind === 'paper') { const q = E.outBack(p); ctx.translate(W / 2, H * 1.2); ctx.rotate((1 - q) * 0.5); ctx.translate(-W / 2, -H * 1.2); ctx.shadowColor = 'rgba(0,0,0,0.4)'; }
        else if (kind === 'glitch') { if (Math.random() < 0.5) ctx.translate((Math.random() - 0.5) * W * 0.05, 0); }
        else { transitionMask(ctx, kind, p, W, H, this.t); ctx.clip(); }
        env = renderScene(ctx, this.project, this.idx, this.t, W, H, { speaking });
        ctx.restore();
        if (kind === 'glitch' && p < 0.6) { ctx.save(); ctx.globalCompositeOperation = 'lighter'; ctx.fillStyle = C.rgba(this.project.brand.a || '#ff2bd6', 0.25 * (1 - p)); for (let i = 0; i < 6; i++) ctx.fillRect(0, Math.random() * H, W, Math.random() * H * 0.05); ctx.restore(); }
      } else {
        this.trans = null;
        env = renderScene(ctx, this.project, this.idx, this.t, W, H, { speaking });
      }
      // hand
      if (this.project.style === 'whiteboard') {
        const tip = env.tips.sort((a, b) => b.start - a.start)[0];
        const target = tip ? tip.tip : [W * 1.1, H * 1.25];
        if (!this.hand) this.hand = [W * 1.1, H * 1.2];
        const k = 1 - Math.exp(-dt * (tip ? 22 : 4));
        this.hand = [lerp(this.hand[0], target[0], k), lerp(this.hand[1], target[1], k)];
        WE.Cast.drawHand(ctx, this.hand[0], this.hand[1], Math.min(W, H) * 0.3, (WE.Cast.SKINS[(this.project.character || {}).skin] || '#e8b48a'), this.project.brand.d || '#1f1f24');
        if (tip && (!this._lastScrib || this.t - this._lastScrib > 0.55 || this.t < this._lastScrib)) { this._lastScrib = this.t; WE.Audio.sfx('scribble'); }
      }
      // cues (sfx authored by shots)
      if (this.project.sfx !== false) for (const [ct, name] of env.cues) if (ct > prevT && ct <= this.t) WE.Audio.sfx(name);
      // captions + bug
      const s = this.project.scenes[this.idx];
      if (this.project.captions !== false && s.narration) { const est = this.voiceEst(this.idx) || 1; drawCaptions(ctx, this.project, s.narration, (this.t - 0.3) / est, W, H); }
      const before = this.project.scenes.slice(0, this.idx).reduce((a, _, i) => a + this.sceneLen(i), 0);
      drawBug(ctx, this.project, W, H, (before + Math.min(this.t, len)) / this.totalLen());
      ctx.restore();
      if (this.opts.onTime) this.opts.onTime(this.idx, this.t, len);
      // advance
      if (this.t >= len && (this.speechDone || this.t > len + 6)) {
        if (this.idx < this.project.scenes.length - 1) { this.trans = { from: this.idx, t: this.t }; this._startScene(this.idx + 1); }
        else if (this.t >= len + 0.6) this.stop(false, true);
      }
    }
    stop(silent, ended) {
      this.playing = false; cancelAnimationFrame(this.raf); this.sceneToken++;
      WE.Voice.cancel(); WE.Audio.stopClip(); WE.Audio.stopMusic(); this.clipPlaying = false; this.hand = null;
      if (!silent && this.opts.onStop) this.opts.onStop(!!ended);
    }
    /**
     * Real-time recording of the whole explainer. opts.narration: 'clips' | 'tab' ; returns Blob.
     */
    async record(opts = {}) {
      const type = WE.pickRecorderType();
      if (!type || !this.canvas.captureStream) throw new Error('This browser cannot record video. Please use a recent Chrome, Edge or Safari.');
      WE.Audio.ensure();
      const vs = this.canvas.captureStream(opts.fps || 30);
      const tracks = [...vs.getVideoTracks()];
      let display = null, displaySrc = null;
      if (opts.narration === 'tab') {
        display = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: { suppressLocalAudioPlayback: false }, preferCurrentTab: true, selfBrowserSurface: 'include', systemAudio: 'include' });
        if (display.getAudioTracks().length) { displaySrc = WE.Audio.ctx.createMediaStreamSource(new MediaStream(display.getAudioTracks())); displaySrc.connect(WE.Audio.recDest); }
      }
      tracks.push(...WE.Audio.recDest.stream.getAudioTracks());
      const rec = new MediaRecorder(new MediaStream(tracks), { mimeType: type, videoBitsPerSecond: opts.bitrate || 8_000_000 });
      const chunks = [];
      rec.ondataavailable = (e) => e.data.size && chunks.push(e.data);
      const finished = new Promise((res) => (rec.onstop = res));
      const prevStop = this.opts.onStop;
      const ended = new Promise((res) => { this.opts.onStop = (end) => { this.opts.onStop = prevStop; res(end); if (prevStop) prevStop(end); }; });
      rec.start(250);
      await new Promise((r) => setTimeout(r, 200));
      this.play(0);
      await ended;
      await new Promise((r) => setTimeout(r, 400));
      rec.stop(); await finished;
      if (displaySrc) displaySrc.disconnect();
      if (display) display.getTracks().forEach((t) => t.stop());
      vs.getTracks().forEach((t) => t.stop());
      return new Blob(chunks, { type: type.split(';')[0] });
    }
  }

  /* ------------------------------------------------------------ subtitles */
  const subtitles = (player, fmt = 'vtt') => {
    const pad = (n, l = 2) => String(Math.floor(n)).padStart(l, '0');
    const ts = (s) => `${pad(s / 3600)}:${pad((s % 3600) / 60)}:${pad(s % 60)}${fmt === 'vtt' ? '.' : ','}${pad((s % 1) * 1000, 3)}`;
    let at = 0, out = fmt === 'vtt' ? 'WEBVTT\n\n' : '', n = 1;
    player.project.scenes.forEach((s, i) => {
      const len = player.sceneLen(i), txt = String(s.narration || '').replace(/\[[^\]]*\]/g, '').replace(/\*/g, '').replace(/\s+/g, ' ').trim();
      if (txt) { const st = at + 0.3, en = st + Math.max(1, player.voiceEst(i)); out += `${fmt === 'srt' ? n++ + '\n' : ''}${ts(st)} --> ${ts(Math.min(en, at + len))}\n${txt}\n\n`; }
      at += len;
    });
    return out;
  };

  WE.Explainer = { STYLES, SHOTS: SHOTS_META, ASPECTS, Player, renderScene, subtitles, STAGES: { hook: 'Hook', problem: 'The Problem', solution: 'The Solution', proof: 'Proof', cta: 'Call to Action' } };
})(typeof window !== 'undefined' ? window : globalThis);
