/* ==========================================================================
 * ATG World Engine — 08 CapCut-Class Video & Photo Lab (Multi-Track NLE)
 * Multi-track timeline (V1 Video/Photo, V2 Overlay/Sticker, T1 Text/Captions,
 * A1 Audio), keyframe interpolation, razor split, speed ramp, transitions,
 * LUTs, colour grading, chroma key, smart subject cutout, masks, blend modes,
 * SRT captions, and real-time WebM/MP4 + PNG/JPEG/WebP + NLE JSON export.
 * ========================================================================== */
(function (root) {
  'use strict';
  const WE = (root.WorldEngine = root.WorldEngine || {});
  const { clamp, lerp } = WE.M;

  const ASPECTS = {
    '9:16': { label: '9:16 Vertical (Reels/TikTok)', w: 720, h: 1280 },
    '16:9': { label: '16:9 Landscape (YouTube)', w: 1280, h: 720 },
    '1:1':  { label: '1:1 Square (Feed)', w: 1080, h: 1080 },
    '4:5':  { label: '4:5 Portrait (Instagram)', w: 1080, h: 1350 },
  };

  const LUTS = {
    none:               { label: 'Original',          filter: '' },
    heritage_gold:      { label: 'Heritage Gold',     filter: 'contrast(1.08) brightness(1.02) saturate(1.18) sepia(0.14)' },
    cinema_teal_orange: { label: 'Cinema Teal/Orange',filter: 'contrast(1.14) saturate(1.15) hue-rotate(-6deg)' },
    vintage_kodak:      { label: 'Vintage Kodak',     filter: 'contrast(1.06) saturate(0.88) sepia(0.24)' },
    crisp_natural:      { label: 'Crisp Natural',     filter: 'contrast(1.05) brightness(1.03) saturate(1.08)' },
    noir_mono:          { label: 'Noir Monochrome',   filter: 'grayscale(1) contrast(1.25) brightness(0.98)' },
    vivid_pop:          { label: 'Vivid Pop',         filter: 'contrast(1.12) saturate(1.38) brightness(1.04)' },
  };

  const BLEND_MODES = ['source-over', 'screen', 'multiply', 'overlay', 'lighten', 'darken'];
  const TRANSITIONS = ['none', 'crossfade', 'slide-left', 'zoom-in', 'wipe', 'glitch'];
  const MASKS = ['none', 'circle', 'rounded', 'oval', 'split-left', 'split-right'];

  const TRACKS = [
    { id: 'T1', label: 'T1 · Text & Captions', type: 'text',    colour: '#f59e0b' },
    { id: 'V2', label: 'V2 · Overlay / PiP',   type: 'overlay', colour: '#ec4899' },
    { id: 'V1', label: 'V1 · Main Video/Photo',type: 'video',   colour: '#6366f1' },
    { id: 'A1', label: 'A1 · Audio / Music',   type: 'audio',   colour: '#10b981' },
  ];

  const uid = () => 'c_' + Math.random().toString(36).slice(2, 9);
  const fmtTime = (s) => {
    const m = Math.floor(s / 60);
    const sec = (s % 60).toFixed(1).padStart(4, '0');
    return `${String(m).padStart(2, '0')}:${sec}`;
  };

  /* ---------------------------------------------- keyframe interpolation */
  const evalClipProps = (clip, localTime) => {
    const base = {
      x: clip.x ?? 0,
      y: clip.y ?? 0,
      scale: clip.scale ?? 1,
      rotation: clip.rotation ?? 0,
      opacity: clip.opacity ?? 1,
    };
    const kfs = Array.isArray(clip.keyframes) ? clip.keyframes : [];
    if (!kfs.length) return base;
    const sorted = kfs.slice().sort((a, b) => a.t - b.t);
    if (localTime <= sorted[0].t) return Object.assign({}, base, sorted[0]);
    if (localTime >= sorted[sorted.length - 1].t) return Object.assign({}, base, sorted[sorted.length - 1]);
    for (let i = 0; i < sorted.length - 1; i++) {
      const a = sorted[i], b = sorted[i + 1];
      if (localTime >= a.t && localTime <= b.t) {
        const span = Math.max(0.0001, b.t - a.t);
        const raw = (localTime - a.t) / span;
        const t = WE.Ease ? WE.Ease.inOutQuad(raw) : raw;
        return {
          x: lerp(a.x ?? base.x, b.x ?? base.x, t),
          y: lerp(a.y ?? base.y, b.y ?? base.y, t),
          scale: lerp(a.scale ?? base.scale, b.scale ?? base.scale, t),
          rotation: lerp(a.rotation ?? base.rotation, b.rotation ?? base.rotation, t),
          opacity: lerp(a.opacity ?? base.opacity, b.opacity ?? base.opacity, t),
        };
      }
    }
    return base;
  };

  /* ------------------------------------ pixel pipeline (chroma + cutout) */
  let scratchCanvas = null;
  let scratchCtx = null;
  const getScratch = (w, h) => {
    if (!scratchCanvas) {
      scratchCanvas = document.createElement('canvas');
      scratchCtx = scratchCanvas.getContext('2d', { willReadFrequently: true });
    }
    if (scratchCanvas.width !== w || scratchCanvas.height !== h) {
      scratchCanvas.width = w;
      scratchCanvas.height = h;
    }
    return [scratchCanvas, scratchCtx];
  };

  const applyChromaAndCutout = (src, clip, maxDim = 640) => {
    const sw = src.videoWidth || src.naturalWidth || src.width || 640;
    const sh = src.videoHeight || src.naturalHeight || src.height || 360;
    const scale = Math.min(1, maxDim / Math.max(sw, sh));
    const w = Math.max(16, Math.round(sw * scale));
    const h = Math.max(16, Math.round(sh * scale));
    const [sc, sctx] = getScratch(w, h);
    sctx.clearRect(0, 0, w, h);
    sctx.drawImage(src, 0, 0, w, h);
    const imgData = sctx.getImageData(0, 0, w, h);
    const d = imgData.data;

    if (clip.chromaEnabled) {
      const [kr, kg, kb] = WE.Color.hexToRgb(clip.chromaColour || '#00ff00');
      const sim = (clip.chromaSimilarity ?? 0.25) * 442;
      const soft = Math.max(1, (clip.chromaSoftness ?? 0.1) * 200);
      for (let i = 0; i < d.length; i += 4) {
        const dist = Math.hypot(d[i] - kr, d[i + 1] - kg, d[i + 2] - kb);
        if (dist < sim) {
          d[i + 3] = 0;
        } else if (dist < sim + soft) {
          d[i + 3] = Math.round(d[i + 3] * ((dist - sim) / soft));
        }
      }
    }

    if (clip.bgCutout) {
      // Sample 4 corners to estimate backdrop colour, then feather matching pixels
      const corners = [
        0,
        (w - 1) * 4,
        ((h - 1) * w) * 4,
        ((h * w) - 1) * 4,
      ];
      let br = 0, bg = 0, bb = 0;
      corners.forEach((idx) => { br += d[idx]; bg += d[idx + 1]; bb += d[idx + 2]; });
      br /= 4; bg /= 4; bb /= 4;
      const tol = (clip.cutoutTolerance ?? 0.22) * 442;
      const feather = 45;
      for (let i = 0; i < d.length; i += 4) {
        const dist = Math.hypot(d[i] - br, d[i + 1] - bg, d[i + 2] - bb);
        if (dist < tol) {
          d[i + 3] = 0;
        } else if (dist < tol + feather) {
          d[i + 3] = Math.round(d[i + 3] * ((dist - tol) / feather));
        }
      }
    }

    sctx.putImageData(imgData, 0, 0);
    return sc;
  };

  /* --------------------------------------------------- mask path helper */
  const applyMaskPath = (ctx, mask, w, h) => {
    if (!mask || mask === 'none') return;
    ctx.beginPath();
    if (mask === 'circle') {
      ctx.arc(0, 0, Math.min(w, h) * 0.48, 0, Math.PI * 2);
    } else if (mask === 'rounded') {
      WE.roundRect(ctx, -w / 2, -h / 2, w, h, Math.min(w, h) * 0.12);
    } else if (mask === 'oval') {
      ctx.ellipse(0, 0, w * 0.45, h * 0.45, 0, 0, Math.PI * 2);
    } else if (mask === 'split-left') {
      ctx.rect(-w / 2, -h / 2, w / 2, h);
    } else if (mask === 'split-right') {
      ctx.rect(0, -h / 2, w / 2, h);
    }
    ctx.clip();
  };

  /* --------------------------------------------------- SRT parser */
  const parseSrt = (srtText) => {
    const blocks = String(srtText || '').trim().split(/\r?\n\r?\n+/);
    const cues = [];
    const toSec = (ts) => {
      const m = String(ts).trim().match(/(\d+):(\d+):(\d+)[,.](\d+)/);
      if (!m) return 0;
      return (+m[1]) * 3600 + (+m[2]) * 60 + (+m[3]) + (+m[4]) / 1000;
    };
    blocks.forEach((b) => {
      const lines = b.split(/\r?\n/);
      const timeLine = lines.find((l) => l.includes('-->'));
      if (!timeLine) return;
      const [s, e] = timeLine.split('-->');
      const start = toSec(s);
      const end = Math.max(start + 0.5, toSec(e));
      const text = lines.slice(lines.indexOf(timeLine) + 1).join(' ').trim();
      if (text) cues.push({ start, duration: +(end - start).toFixed(2), text });
    });
    return cues;
  };

  /* ------------------------------------------------ sample media factory */
  const createSampleAssets = (brand) => {
    const mkCard = (title, sub, c1, c2) => {
      const c = document.createElement('canvas');
      c.width = 1080; c.height = 1080;
      const g = c.getContext('2d');
      const gr = g.createLinearGradient(0, 0, 1080, 1080);
      gr.addColorStop(0, c1); gr.addColorStop(1, c2);
      g.fillStyle = gr; g.fillRect(0, 0, 1080, 1080);
      g.fillStyle = 'rgba(255,255,255,0.08)';
      g.beginPath(); g.arc(820, 240, 260, 0, Math.PI * 2); g.fill();
      g.beginPath(); g.arc(240, 860, 200, 0, Math.PI * 2); g.fill();
      g.fillStyle = '#ffffff';
      g.font = '800 72px ' + (WE.F ? WE.F.grotesk : 'sans-serif');
      g.textAlign = 'center';
      g.fillText(title, 540, 500);
      g.font = '500 36px ' + (WE.F ? WE.F.ui : 'sans-serif');
      g.fillStyle = 'rgba(255,255,255,0.82)';
      g.fillText(sub, 540, 575);
      return c;
    };
    return {
      card1: mkCard((brand && brand.name) || 'World Studio', 'Multi-Track NLE & Photo Lab', (brand && brand.a) || '#7c5cff', '#14172b'),
      card2: mkCard('60fps Keyframes', 'LUTs · Chroma Key · Split · Export', (brand && brand.b) || '#ff3d7f', '#1e1035'),
    };
  };

  /* ------------------------------------------------------------ mount UI */
  const mount = (rootEl, studio, deps = {}) => {
    const esc = WE.esc;
    const $ = (r, s) => r.querySelector(s);
    const $$ = (r, s) => Array.from(r.querySelectorAll(s));
    const h = (html) => { const d = document.createElement('div'); d.innerHTML = html.trim(); return d.firstElementChild; };
    const toast = studio.toast || (() => {});
    const History = deps.History;
    const hist = History ? new History(60) : null;
    const KEY = 'we:nle:' + (studio.cfg.app || 'app');

    const mediaBin = new Map(); // mediaId -> { id, name, kind: 'video'|'image'|'audio'|'canvas', el, duration, url }
    const samples = createSampleAssets(studio.cfg.brand);
    mediaBin.set('sample_1', { id: 'sample_1', name: 'Brand Intro Card', kind: 'canvas', el: samples.card1, duration: 4.0 });
    mediaBin.set('sample_2', { id: 'sample_2', name: 'Feature Showcase Card', kind: 'canvas', el: samples.card2, duration: 4.0 });

    let project = {
      aspect: '9:16',
      bg: '#090b14',
      zoomPxPerSec: 42,
      clips: [
        {
          id: uid(), track: 'V1', mediaId: 'sample_1', name: 'Brand Intro Card', kind: 'canvas',
          start: 0, duration: 3.5, trimStart: 0, speed: 1,
          x: 0, y: 0, scale: 1, rotation: 0, opacity: 1,
          blendMode: 'source-over', transitionIn: 'zoom-in', mask: 'none',
          lut: 'none', brightness: 0, contrast: 0, saturation: 0, temperature: 0, blur: 0, vignette: 15,
          keyframes: [{ t: 0, scale: 0.92, opacity: 0.2 }, { t: 0.8, scale: 1.0, opacity: 1 }],
        },
        {
          id: uid(), track: 'V1', mediaId: 'sample_2', name: 'Feature Showcase Card', kind: 'canvas',
          start: 3.5, duration: 3.5, trimStart: 0, speed: 1,
          x: 0, y: 0, scale: 1, rotation: 0, opacity: 1,
          blendMode: 'source-over', transitionIn: 'slide-left', mask: 'none',
          lut: 'cinema_teal_orange', brightness: 0, contrast: 8, saturation: 10, temperature: 0, blur: 0, vignette: 20,
          keyframes: [],
        },
        {
          id: uid(), track: 'T1', kind: 'text', name: 'Headline Caption',
          text: 'Create without limits', font: 'grotesk', fontSize: 58, colour: '#ffffff', bgColour: '#7c5cff',
          textAnim: 'pop', start: 0.4, duration: 3.0, x: 0, y: 34, scale: 1, rotation: 0, opacity: 1, keyframes: [],
        },
      ],
    };

    let selectedId = project.clips[0].id;
    let playhead = 0;
    let playing = false;
    let raf = 0;
    let lastTs = 0;
    let activeTab = 'media';

    const getDuration = () => Math.max(5, project.clips.reduce((m, c) => Math.max(m, (c.start || 0) + (c.duration || 0)), 0));
    const getSelected = () => project.clips.find((c) => c.id === selectedId) || null;

    const commit = (fn) => {
      if (hist) hist.push(project);
      fn();
      renderTimeline();
      renderInspector();
      drawFrame(playhead);
    };

    rootEl.innerHTML = '';
    const el = h(`<div class="wes-nle">
      <div class="wes-nle-main">
        <div class="wes-stagecol">
          <div class="wes-stagebar">
            <select class="wes-select wes-nle-aspect" aria-label="Canvas aspect ratio" style="width:auto;min-width:190px">
              ${Object.entries(ASPECTS).map(([k, v]) => `<option value="${k}" ${project.aspect === k ? 'selected' : ''}>${esc(v.label)}</option>`).join('')}
            </select>
            <button type="button" class="wes-btn" data-act="undo" title="Undo">↩ Undo</button>
            <button type="button" class="wes-btn" data-act="redo" title="Redo">↪ Redo</button>
            <div class="wes-spacer"></div>
            <button type="button" class="wes-btn" data-act="export-png">🖼️ Save Photo (PNG)</button>
            <button type="button" class="wes-btn" data-act="export-json">📄 NLE JSON</button>
            <button type="button" class="wes-btn wes-primary" data-act="export-video">🎬 Export Video</button>
          </div>
          <div class="wes-stage"><canvas class="wes-canvas" width="720" height="1280"></canvas></div>
          <div class="wes-transport">
            <button type="button" class="wes-btn wes-primary" data-act="play">▶ Play</button>
            <button type="button" class="wes-btn" data-act="stop">⏹ Stop</button>
            <button type="button" class="wes-btn" data-act="split" title="Split selected clip at playhead">✂️ Split</button>
            <button type="button" class="wes-btn" data-act="dup" title="Duplicate selected clip">⧉ Duplicate</button>
            <button type="button" class="wes-btn wes-danger" data-act="del" title="Delete selected clip">🗑 Delete</button>
            <span class="wes-time" data-timecode>00:00.0 / 00:07.0</span>
          </div>
        </div>
        <aside class="wes-side">
          <div class="wes-tabs">
            <div class="wes-tabbar" role="tablist">
              <button type="button" class="wes-tab on" data-ntab="media">📁 Media</button>
              <button type="button" class="wes-tab" data-ntab="transform">🎛️ Transform</button>
              <button type="button" class="wes-tab" data-ntab="colour">🎨 Colour &amp; FX</button>
              <button type="button" class="wes-tab" data-ntab="text">💬 Text &amp; Audio</button>
            </div>
            <div class="wes-tabpanes">
              <div class="wes-pane on" data-npane="media"></div>
              <div class="wes-pane" data-npane="transform"></div>
              <div class="wes-pane" data-npane="colour"></div>
              <div class="wes-pane" data-npane="text"></div>
            </div>
          </div>
        </aside>
      </div>
      <div class="wes-nle-timeline-wrap">
        <div class="wes-nle-tl-bar">
          <b>Multi-Track Timeline</b>
          <label class="wes-nle-zoom"><span>Zoom</span><input type="range" min="18" max="110" value="${project.zoomPxPerSec}" data-tl-zoom></label>
          <span class="wes-hint" data-tl-hint>Click ruler to scrub · Drag clips to reposition · ✂️ Split at playhead</span>
        </div>
        <div class="wes-nle-ruler" data-ruler></div>
        <div class="wes-nle-tracks" data-tracks></div>
      </div>
    </div>`);
    rootEl.appendChild(el);

    const canvas = $(el, '.wes-canvas');
    const ctx = canvas.getContext('2d');
    const timeEl = $(el, '[data-timecode]');

    /* --------------------------------------------------- media syncing */
    const syncMediaElements = (t, isPlaying) => {
      project.clips.forEach((c) => {
        if (c.kind !== 'video' && c.kind !== 'audio') return;
        const m = mediaBin.get(c.mediaId);
        if (!m || !m.el) return;
        const active = t >= c.start && t < c.start + c.duration;
        if (active) {
          const target = Math.max(0, (c.trimStart || 0) + (t - c.start) * (c.speed || 1));
          m.el.playbackRate = clamp(c.speed || 1, 0.25, 4);
          m.el.volume = clamp(c.volume ?? 1, 0, 1);
          if (Math.abs(m.el.currentTime - target) > 0.25) {
            try { m.el.currentTime = target; } catch (e) { /* seek guard */ }
          }
          if (isPlaying && m.el.paused) m.el.play().catch(() => {});
        } else if (!m.el.paused) {
          m.el.pause();
        }
      });
    };

    /* --------------------------------------------------- frame renderer */
    const drawFrame = (t) => {
      const dim = ASPECTS[project.aspect] || ASPECTS['9:16'];
      if (canvas.width !== dim.w || canvas.height !== dim.h) {
        canvas.width = dim.w; canvas.height = dim.h;
      }
      const W = dim.w, H = dim.h;
      ctx.save();
      ctx.fillStyle = project.bg || '#090b14';
      ctx.fillRect(0, 0, W, H);

      // Render order: V1 -> V2 -> T1
      const order = { V1: 1, V2: 2, T1: 3 };
      const active = project.clips
        .filter((c) => c.track !== 'A1' && t >= c.start && t < c.start + c.duration)
        .sort((a, b) => (order[a.track] || 9) - (order[b.track] || 9));

      active.forEach((c) => {
        const localT = t - c.start;
        const props = evalClipProps(c, localT);
        let alpha = clamp(props.opacity, 0, 1);
        let tx = (props.x / 100) * W;
        let ty = (props.y / 100) * H;
        let sc = props.scale;

        // Transitions (first 0.55s)
        const trDur = Math.min(0.55, c.duration * 0.45);
        if (c.transitionIn && c.transitionIn !== 'none' && localT < trDur) {
          const p = WE.Ease ? WE.Ease.outCubic(localT / trDur) : localT / trDur;
          if (c.transitionIn === 'crossfade') alpha *= p;
          else if (c.transitionIn === 'slide-left') tx += (1 - p) * W * 0.65;
          else if (c.transitionIn === 'zoom-in') { sc *= lerp(1.35, 1, p); alpha *= p; }
          else if (c.transitionIn === 'glitch') { tx += (Math.sin(localT * 90) * (1 - p) * 24); }
        }

        ctx.save();
        ctx.globalAlpha = alpha;
        ctx.globalCompositeOperation = c.blendMode || 'source-over';
        ctx.translate(W / 2 + tx, H / 2 + ty);
        ctx.rotate((props.rotation * Math.PI) / 180);
        ctx.scale(sc, sc);

        // CSS-style GPU colour grading filter
        const lut = LUTS[c.lut || 'none'] ? LUTS[c.lut || 'none'].filter : '';
        const b = 1 + (c.brightness || 0) / 100;
        const ct = 1 + (c.contrast || 0) / 100;
        const sat = 1 + (c.saturation || 0) / 100;
        const bl = c.blur || 0;
        const parts = [lut, `brightness(${b})`, `contrast(${ct})`, `saturate(${sat})`];
        if (bl > 0) parts.push(`blur(${bl}px)`);
        ctx.filter = parts.filter(Boolean).join(' ') || 'none';

        if (c.kind === 'text') {
          const fs = c.fontSize || 52;
          const fontFam = (WE.F && WE.F[c.font || 'grotesk']) || 'sans-serif';
          ctx.font = `800 ${fs}px ${fontFam}`;
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          let txt = String(c.text || '');
          if (c.textAnim === 'typewriter') {
            const pct = clamp(localT / Math.min(1.2, c.duration), 0, 1);
            txt = txt.slice(0, Math.max(1, Math.round(txt.length * pct)));
          } else if (c.textAnim === 'pop' && localT < 0.35) {
            const p = WE.Ease ? WE.Ease.outBack(localT / 0.35) : 1;
            ctx.scale(p, p);
          }
          const tw = ctx.measureText(txt).width;
          if (c.bgColour && c.bgColour !== 'transparent') {
            ctx.fillStyle = c.bgColour;
            WE.roundRect(ctx, -tw / 2 - 26, -fs * 0.72, tw + 52, fs * 1.44, 16);
            ctx.fill();
          }
          ctx.fillStyle = c.colour || '#ffffff';
          ctx.fillText(txt, 0, 0);
        } else if (c.kind === 'sticker' && WE.Cast) {
          WE.Cast.drawProp(ctx, c.prop || 'star', 0, 0, Math.min(W, H) * 0.28, {
            mode: 'cartoon',
            pal: { ink: '#1d1b26', a: '#ff3d7f', b: '#4f7cff', c: '#ffd23f', d: '#2b2d42', l: '#ffffff' },
          });
        } else {
          const m = mediaBin.get(c.mediaId);
          if (m && m.el) {
            let src = m.el;
            if (c.chromaEnabled || c.bgCutout) {
              src = applyChromaAndCutout(m.el, c);
            }
            const sw = src.videoWidth || src.naturalWidth || src.width || W;
            const sh = src.videoHeight || src.naturalHeight || src.height || H;
            const fit = c.track === 'V2' ? Math.min((W * 0.55) / sw, (H * 0.55) / sh) : Math.max(W / sw, H / sh);
            const dw = sw * fit, dh = sh * fit;
            if (c.mask && c.mask !== 'none') applyMaskPath(ctx, c.mask, dw, dh);
            if (c.transitionIn === 'wipe' && localT < trDur) {
              const p = localT / trDur;
              ctx.beginPath(); ctx.rect(-dw / 2, -dh / 2, dw * p, dh); ctx.clip();
            }
            ctx.drawImage(src, -dw / 2, -dh / 2, dw, dh);

            // Warmth / temperature tint
            if (c.temperature) {
              ctx.filter = 'none';
              ctx.fillStyle = c.temperature > 0
                ? `rgba(255,160,60,${clamp(c.temperature / 220, 0, 0.35)})`
                : `rgba(60,150,255,${clamp(-c.temperature / 220, 0, 0.35)})`;
              ctx.fillRect(-dw / 2, -dh / 2, dw, dh);
            }
          }
        }

        // Vignette overlay per V1 clip
        if (c.track === 'V1' && (c.vignette || 0) > 0) {
          ctx.filter = 'none';
          const vg = ctx.createRadialGradient(0, 0, Math.min(W, H) * 0.25, 0, 0, Math.max(W, H) * 0.72);
          vg.addColorStop(0, 'rgba(0,0,0,0)');
          vg.addColorStop(1, `rgba(0,0,0,${clamp(c.vignette / 120, 0, 0.85)})`);
          ctx.fillStyle = vg;
          ctx.fillRect(-W, -H, W * 2, H * 2);
        }

        ctx.restore();
      });

      ctx.restore();
      timeEl.textContent = `${fmtTime(t)} / ${fmtTime(getDuration())}`;
    };

    /* --------------------------------------------------- inspector tabs */
    const panes = {
      media: $(el, '[data-npane="media"]'),
      transform: $(el, '[data-npane="transform"]'),
      colour: $(el, '[data-npane="colour"]'),
      text: $(el, '[data-npane="text"]'),
    };
    const setTab = (tab) => {
      activeTab = tab;
      $$(el, '[data-ntab]').forEach((b) => b.classList.toggle('on', b.dataset.ntab === tab));
      Object.entries(panes).forEach(([k, p]) => p.classList.toggle('on', k === tab));
      renderInspector();
    };
    $$(el, '[data-ntab]').forEach((b) => b.addEventListener('click', () => setTab(b.dataset.ntab)));

    const renderInspector = () => {
      const sel = getSelected();
      // Media Tab
      panes.media.innerHTML = `<div class="wes-form">
        <div class="wes-field"><span>Import Video, Photo or Audio</span>
          <label class="wes-btn wes-primary wes-file" style="width:100%">+ Upload Local Media
            <input type="file" accept="video/*,image/*,audio/*" multiple data-nle-upload style="display:none">
          </label>
          <small>100% browser-local (zero server upload). Supports MP4, WebM, PNG, JPG, WebP, MP3, WAV.</small>
        </div>
        <div class="wes-subhead">Project Media Bin (${mediaBin.size})</div>
        <div class="wes-nle-bin">
          ${Array.from(mediaBin.values()).map((m) => `
            <div class="wes-nle-bin-item">
              <div><b>${esc(m.name)}</b><small>${esc(m.kind)} · ${m.duration.toFixed(1)}s</small></div>
              <div style="display:flex;gap:6px">
                ${m.kind === 'audio'
                  ? `<button type="button" class="wes-btn wes-mini" data-add-media="${esc(m.id)}" data-to-track="A1">+ A1</button>`
                  : `<button type="button" class="wes-btn wes-mini" data-add-media="${esc(m.id)}" data-to-track="V1">+ V1</button>
                     <button type="button" class="wes-btn wes-mini" data-add-media="${esc(m.id)}" data-to-track="V2">+ PiP</button>`}
              </div>
            </div>`).join('')}
        </div>
      </div>`;
      $(panes.media, '[data-nle-upload]').addEventListener('change', (e) => {
        Array.from(e.target.files || []).forEach((f) => {
          const url = URL.createObjectURL(f);
          const id = uid();
          if (f.type.startsWith('video/')) {
            const v = document.createElement('video');
            v.src = url; v.muted = false; v.playsInline = true; v.crossOrigin = 'anonymous';
            v.onloadedmetadata = () => {
              mediaBin.set(id, { id, name: f.name, kind: 'video', el: v, duration: clamp(v.duration || 5, 1, 300), url });
              renderInspector();
            };
          } else if (f.type.startsWith('audio/')) {
            const a = document.createElement('audio');
            a.src = url;
            a.onloadedmetadata = () => {
              mediaBin.set(id, { id, name: f.name, kind: 'audio', el: a, duration: clamp(a.duration || 10, 1, 600), url });
              renderInspector();
            };
          } else if (f.type.startsWith('image/')) {
            const img = new Image();
            img.onload = () => {
              mediaBin.set(id, { id, name: f.name, kind: 'image', el: img, duration: 4.0, url });
              renderInspector();
            };
            img.src = url;
          }
        });
      });
      $$(panes.media, '[data-add-media]').forEach((b) => b.addEventListener('click', () => {
        const m = mediaBin.get(b.dataset.addMedia);
        if (!m) return;
        const tr = b.dataset.toTrack;
        const trackEnd = project.clips.filter((c) => c.track === tr).reduce((mx, c) => Math.max(mx, c.start + c.duration), 0);
        const nc = {
          id: uid(), track: tr, mediaId: m.id, name: m.name, kind: m.kind,
          start: tr === 'V1' ? trackEnd : playhead, duration: Math.min(m.duration, 10), trimStart: 0, speed: 1, volume: 1,
          x: 0, y: 0, scale: tr === 'V2' ? 0.65 : 1, rotation: 0, opacity: 1,
          blendMode: 'source-over', transitionIn: 'crossfade', mask: 'none',
          lut: 'none', brightness: 0, contrast: 0, saturation: 0, temperature: 0, blur: 0, vignette: 0, keyframes: [],
        };
        commit(() => { project.clips.push(nc); selectedId = nc.id; });
      }));

      // Transform & Keyframes Tab
      if (!sel) {
        panes.transform.innerHTML = `<p class="wes-hint">Select a clip on the timeline to edit its transform, speed, transitions, and keyframes.</p>`;
        panes.colour.innerHTML = `<p class="wes-hint">Select a video or photo clip on the timeline to grade colours, apply LUTs, or remove backgrounds.</p>`;
      } else {
        panes.transform.innerHTML = `<div class="wes-form">
          <div class="wes-subhead">${esc(sel.name)} (${esc(sel.track)})</div>
          <div class="wes-row2">
            <label class="wes-field"><span>Start (s)</span><input class="wes-input" type="number" step="0.1" min="0" value="${sel.start}" data-prop="start"></label>
            <label class="wes-field"><span>Duration (s)</span><input class="wes-input" type="number" step="0.1" min="0.2" max="300" value="${sel.duration}" data-prop="duration"></label>
          </div>
          <div class="wes-row2">
            <label class="wes-field"><span>Speed <output>${(sel.speed || 1).toFixed(2)}x</output></span><input type="range" min="0.25" max="4" step="0.25" value="${sel.speed || 1}" data-range="speed"></label>
            <label class="wes-field"><span>Opacity <output>${Math.round((sel.opacity ?? 1) * 100)}%</output></span><input type="range" min="0" max="1" step="0.05" value="${sel.opacity ?? 1}" data-range="opacity"></label>
          </div>
          <div class="wes-row3">
            <label class="wes-field"><span>X % <output>${sel.x || 0}</output></span><input type="range" min="-100" max="100" value="${sel.x || 0}" data-range="x"></label>
            <label class="wes-field"><span>Y % <output>${sel.y || 0}</output></span><input type="range" min="-100" max="100" value="${sel.y || 0}" data-range="y"></label>
            <label class="wes-field"><span>Scale <output>${(sel.scale ?? 1).toFixed(2)}</output></span><input type="range" min="0.1" max="3" step="0.05" value="${sel.scale ?? 1}" data-range="scale"></label>
          </div>
          <div class="wes-row2">
            <label class="wes-field"><span>Rotation <output>${sel.rotation || 0}°</output></span><input type="range" min="-180" max="180" value="${sel.rotation || 0}" data-range="rotation"></label>
            <label class="wes-field"><span>Transition In</span><select class="wes-select" data-sel="transitionIn">${TRANSITIONS.map((t) => `<option value="${t}" ${sel.transitionIn === t ? 'selected' : ''}>${t}</option>`).join('')}</select></label>
          </div>
          <div class="wes-row2">
            <label class="wes-field"><span>Blend Mode</span><select class="wes-select" data-sel="blendMode">${BLEND_MODES.map((m) => `<option value="${m}" ${sel.blendMode === m ? 'selected' : ''}>${m}</option>`).join('')}</select></label>
            <label class="wes-field"><span>Mask Shape</span><select class="wes-select" data-sel="mask">${MASKS.map((m) => `<option value="${m}" ${sel.mask === m ? 'selected' : ''}>${m}</option>`).join('')}</select></label>
          </div>
          <div class="wes-subhead">Keyframes (${(sel.keyframes || []).length})</div>
          <div class="wes-row2">
            <button type="button" class="wes-btn" data-kf="add">◆ Add Keyframe Here</button>
            <button type="button" class="wes-btn wes-danger" data-kf="clear">Clear Keyframes</button>
          </div>
        </div>`;

        $$(panes.transform, '[data-prop]').forEach((i) => i.addEventListener('change', () => commit(() => { sel[i.dataset.prop] = Math.max(0, +i.value || 0); })));
        $$(panes.transform, '[data-sel]').forEach((i) => i.addEventListener('change', () => commit(() => { sel[i.dataset.sel] = i.value; })));
        $$(panes.transform, '[data-range]').forEach((i) => {
          i.addEventListener('input', () => {
            sel[i.dataset.range] = +i.value;
            const out = i.closest('label').querySelector('output');
            if (out) out.textContent = i.dataset.range === 'opacity' ? Math.round(i.value * 100) + '%' : i.value;
            drawFrame(playhead);
          });
          i.addEventListener('change', () => commit(() => { sel[i.dataset.range] = +i.value; }));
        });
        $(panes.transform, '[data-kf="add"]').addEventListener('click', () => {
          const lt = clamp(playhead - sel.start, 0, sel.duration);
          commit(() => {
            sel.keyframes = (sel.keyframes || []).filter((k) => Math.abs(k.t - lt) > 0.05);
            sel.keyframes.push({ t: +lt.toFixed(2), x: sel.x || 0, y: sel.y || 0, scale: sel.scale ?? 1, rotation: sel.rotation || 0, opacity: sel.opacity ?? 1 });
          });
          toast('Keyframe added ◆');
        });
        $(panes.transform, '[data-kf="clear"]').addEventListener('click', () => commit(() => { sel.keyframes = []; }));

        // Colour & FX Tab
        panes.colour.innerHTML = `<div class="wes-form">
          <label class="wes-field"><span>LUT Preset</span>
            <select class="wes-select" data-lut>${Object.entries(LUTS).map(([k, v]) => `<option value="${k}" ${sel.lut === k ? 'selected' : ''}>${esc(v.label)}</option>`).join('')}</select>
          </label>
          <div class="wes-row2">
            <label class="wes-field"><span>Brightness <output>${sel.brightness || 0}</output></span><input type="range" min="-50" max="50" value="${sel.brightness || 0}" data-fx="brightness"></label>
            <label class="wes-field"><span>Contrast <output>${sel.contrast || 0}</output></span><input type="range" min="-50" max="50" value="${sel.contrast || 0}" data-fx="contrast"></label>
          </div>
          <div class="wes-row2">
            <label class="wes-field"><span>Saturation <output>${sel.saturation || 0}</output></span><input type="range" min="-100" max="100" value="${sel.saturation || 0}" data-fx="saturation"></label>
            <label class="wes-field"><span>Warmth <output>${sel.temperature || 0}</output></span><input type="range" min="-50" max="50" value="${sel.temperature || 0}" data-fx="temperature"></label>
          </div>
          <div class="wes-row2">
            <label class="wes-field"><span>Blur <output>${sel.blur || 0}px</output></span><input type="range" min="0" max="20" value="${sel.blur || 0}" data-fx="blur"></label>
            <label class="wes-field"><span>Vignette <output>${sel.vignette || 0}%</output></span><input type="range" min="0" max="100" value="${sel.vignette || 0}" data-fx="vignette"></label>
          </div>
          <div class="wes-subhead">Chroma Key (Green Screen) &amp; Smart Cutout</div>
          <label class="wes-check"><input type="checkbox" data-chk="chromaEnabled" ${sel.chromaEnabled ? 'checked' : ''}> Enable Chroma Key</label>
          <div class="wes-row2">
            <label class="wes-field"><span>Key Colour</span><input type="color" class="wes-color" value="${esc(sel.chromaColour || '#00ff00')}" data-col="chromaColour"></label>
            <label class="wes-field"><span>Tolerance <output>${Math.round((sel.chromaSimilarity ?? 0.25) * 100)}%</output></span><input type="range" min="0.05" max="0.6" step="0.02" value="${sel.chromaSimilarity ?? 0.25}" data-fx="chromaSimilarity"></label>
          </div>
          <label class="wes-check"><input type="checkbox" data-chk="bgCutout" ${sel.bgCutout ? 'checked' : ''}> Smart Subject Cutout (Auto-Remove Backdrop)</label>
        </div>`;
        $(panes.colour, '[data-lut]').addEventListener('change', (e) => commit(() => { sel.lut = e.target.value; }));
        $$(panes.colour, '[data-fx]').forEach((i) => {
          i.addEventListener('input', () => {
            sel[i.dataset.fx] = +i.value;
            const out = i.closest('label').querySelector('output');
            if (out) out.textContent = i.dataset.fx === 'chromaSimilarity' ? Math.round(i.value * 100) + '%' : i.value;
            drawFrame(playhead);
          });
          i.addEventListener('change', () => commit(() => { sel[i.dataset.fx] = +i.value; }));
        });
        $$(panes.colour, '[data-chk]').forEach((i) => i.addEventListener('change', () => commit(() => { sel[i.dataset.chk] = i.checked; })));
        $(panes.colour, '[data-col="chromaColour"]').addEventListener('change', (e) => commit(() => { sel.chromaColour = e.target.value; }));
      }

      // Text, Captions & Stickers Tab
      const isText = sel && sel.kind === 'text';
      panes.text.innerHTML = `<div class="wes-form">
        <button type="button" class="wes-btn wes-primary" data-add-text>+ Add Text / Caption Layer</button>
        ${isText ? `
          <label class="wes-field"><span>Caption Text</span><input class="wes-input" type="text" value="${esc(sel.text || '')}" data-txt="text"></label>
          <div class="wes-row2">
            <label class="wes-field"><span>Font Size</span><input class="wes-input" type="number" min="18" max="160" value="${sel.fontSize || 52}" data-txtnum="fontSize"></label>
            <label class="wes-field"><span>Animation</span><select class="wes-select" data-txtsel="textAnim">${['none', 'pop', 'typewriter'].map((a) => `<option value="${a}" ${sel.textAnim === a ? 'selected' : ''}>${a}</option>`).join('')}</select></label>
          </div>
          <div class="wes-row2">
            <label class="wes-field"><span>Text Colour</span><input type="color" class="wes-color" value="${esc(sel.colour || '#ffffff')}" data-txt="colour"></label>
            <label class="wes-field"><span>Pill Colour</span><input type="color" class="wes-color" value="${esc(sel.bgColour || '#7c5cff')}" data-txt="bgColour"></label>
          </div>
        ` : ''}
        <div class="wes-subhead">Import SRT Subtitles</div>
        <textarea class="wes-input" rows="3" placeholder="1&#10;00:00:00,500 --> 00:00:03,000&#10;Your caption here" data-srt-input></textarea>
        <button type="button" class="wes-btn" data-import-srt>Import SRT Cues to T1</button>
        ${WE.Cast ? `<div class="wes-subhead">Add Stickers to V2</div>
          <div class="wes-props">${WE.Cast.PROPS.slice(0, 12).map((p) => `<button type="button" class="wes-prop" data-sticker="${p}" title="Add ${p}"><canvas width="44" height="44" data-sdraw="${p}"></canvas></button>`).join('')}</div>` : ''}
      </div>`;
      $(panes.text, '[data-add-text]').addEventListener('click', () => {
        const nc = {
          id: uid(), track: 'T1', kind: 'text', name: 'Caption', text: 'New Caption',
          font: 'grotesk', fontSize: 54, colour: '#ffffff', bgColour: '#ff3d7f', textAnim: 'pop',
          start: playhead, duration: 3.0, x: 0, y: 30, scale: 1, rotation: 0, opacity: 1, keyframes: [],
        };
        commit(() => { project.clips.push(nc); selectedId = nc.id; });
      });
      if (isText) {
        $$(panes.text, '[data-txt]').forEach((i) => i.addEventListener('input', () => { sel[i.dataset.txt] = i.value; drawFrame(playhead); }));
        $$(panes.text, '[data-txtnum]').forEach((i) => i.addEventListener('change', () => commit(() => { sel[i.dataset.txtnum] = +i.value || 48; })));
        $$(panes.text, '[data-txtsel]').forEach((i) => i.addEventListener('change', () => commit(() => { sel[i.dataset.txtsel] = i.value; })));
      }
      $(panes.text, '[data-import-srt]').addEventListener('click', () => {
        const cues = parseSrt($(panes.text, '[data-srt-input]').value);
        if (!cues.length) return toast('No valid SRT cues found.');
        commit(() => {
          cues.forEach((q) => project.clips.push({
            id: uid(), track: 'T1', kind: 'text', name: q.text.slice(0, 18), text: q.text,
            font: 'grotesk', fontSize: 44, colour: '#ffffff', bgColour: '#111827', textAnim: 'none',
            start: q.start, duration: q.duration, x: 0, y: 36, scale: 1, rotation: 0, opacity: 1, keyframes: [],
          }));
        });
        toast(`Imported ${cues.length} SRT captions ✅`);
      });
      $$(panes.text, 'canvas[data-sdraw]').forEach((c) => WE.Cast.drawProp(c.getContext('2d'), c.dataset.sdraw, 22, 22, 36, { mode: 'cartoon', pal: { ink: '#1d1b26', a: '#ff3d7f', b: '#4f7cff', c: '#ffd23f', d: '#2b2d42', l: '#fff' }, lw: 1.6 }));
      $$(panes.text, '[data-sticker]').forEach((b) => b.addEventListener('click', () => {
        const nc = {
          id: uid(), track: 'V2', kind: 'sticker', prop: b.dataset.sticker, name: 'Sticker: ' + b.dataset.sticker,
          start: playhead, duration: 3.0, x: 0, y: -20, scale: 1, rotation: 0, opacity: 1,
          blendMode: 'source-over', transitionIn: 'zoom-in', mask: 'none', keyframes: [],
        };
        commit(() => { project.clips.push(nc); selectedId = nc.id; });
      }));
    };

    /* --------------------------------------------------- timeline UI */
    const rulerEl = $(el, '[data-ruler]');
    const tracksEl = $(el, '[data-tracks]');

    const renderTimeline = () => {
      const px = project.zoomPxPerSec || 42;
      const dur = getDuration() + 2;
      const totalW = Math.max(600, Math.round(dur * px));

      // Ruler ticks
      let ticks = '';
      for (let s = 0; s <= Math.ceil(dur); s++) {
        ticks += `<span class="wes-nle-tick" style="left:${s * px}px">${s}s</span>`;
      }
      rulerEl.innerHTML = `<div class="wes-nle-ruler-inner" style="width:${totalW}px">${ticks}<div class="wes-nle-head" style="left:${playhead * px}px"></div></div>`;

      // Tracks
      tracksEl.innerHTML = TRACKS.map((tr) => {
        const clips = project.clips.filter((c) => c.track === tr.id);
        return `<div class="wes-nle-track">
          <div class="wes-nle-track-lbl" style="border-left:4px solid ${tr.colour}">${esc(tr.label)}</div>
          <div class="wes-nle-lane" style="width:${totalW}px" data-lane="${tr.id}">
            ${clips.map((c) => {
              const left = Math.round(c.start * px);
              const width = Math.max(28, Math.round(c.duration * px));
              const kfs = (c.keyframes || []).map((k) => `<i class="wes-nle-kf" style="left:${Math.round(k.t * px)}px" title="Keyframe @ ${k.t}s"></i>`).join('');
              return `<button type="button" class="wes-nle-clip ${c.id === selectedId ? 'on' : ''}" data-clip="${c.id}" style="left:${left}px;width:${width}px;background:${tr.colour}22;border-color:${c.id === selectedId ? '#fff' : tr.colour}">
                <span>${esc(c.name || c.text || 'Clip')}</span>
                <small>${c.duration.toFixed(1)}s</small>
                ${kfs}
              </button>`;
            }).join('')}
            <div class="wes-nle-needle" style="left:${playhead * px}px"></div>
          </div>
        </div>`;
      }).join('');

      // Clip selection + horizontal drag
      $$(tracksEl, '[data-clip]').forEach((b) => {
        b.addEventListener('pointerdown', (e) => {
          const cid = b.dataset.clip;
          selectedId = cid;
          const clip = getSelected();
          if (!clip) return;
          const startX = e.clientX;
          const origStart = clip.start;
          const move = (ev) => {
            const dx = (ev.clientX - startX) / px;
            clip.start = Math.max(0, +(origStart + dx).toFixed(2));
            b.style.left = Math.round(clip.start * px) + 'px';
            drawFrame(playhead);
          };
          const up = () => {
            root.removeEventListener('pointermove', move);
            root.removeEventListener('pointerup', up);
            renderTimeline();
            renderInspector();
          };
          root.addEventListener('pointermove', move);
          root.addEventListener('pointerup', up);
        });
      });

      // Click ruler to scrub playhead
      const scrubAt = (clientX) => {
        const rect = rulerEl.querySelector('.wes-nle-ruler-inner').getBoundingClientRect();
        playhead = clamp((clientX - rect.left) / px, 0, getDuration());
        syncMediaElements(playhead, playing);
        drawFrame(playhead);
        $$(el, '.wes-nle-head, .wes-nle-needle').forEach((n) => (n.style.left = playhead * px + 'px'));
      };
      rulerEl.onpointerdown = (e) => {
        scrubAt(e.clientX);
        const mv = (ev) => scrubAt(ev.clientX);
        const up = () => { root.removeEventListener('pointermove', mv); root.removeEventListener('pointerup', up); };
        root.addEventListener('pointermove', mv);
        root.addEventListener('pointerup', up);
      };
    };

    /* --------------------------------------------------- transport & actions */
    const tick = (ts) => {
      if (!playing) return;
      const dt = lastTs ? Math.min(0.1, (ts - lastTs) / 1000) : 0;
      lastTs = ts;
      playhead += dt;
      const dur = getDuration();
      if (playhead >= dur) playhead = 0;
      syncMediaElements(playhead, true);
      drawFrame(playhead);
      const px = project.zoomPxPerSec || 42;
      $$(el, '.wes-nle-head, .wes-nle-needle').forEach((n) => (n.style.left = playhead * px + 'px'));
      raf = requestAnimationFrame(tick);
    };

    const setPlaying = (on) => {
      playing = on;
      $(el, '[data-act="play"]').textContent = on ? '⏸ Pause' : '▶ Play';
      if (on) { lastTs = performance.now(); syncMediaElements(playhead, true); raf = requestAnimationFrame(tick); }
      else { cancelAnimationFrame(raf); syncMediaElements(playhead, false); }
    };

    $(el, '[data-act="play"]').addEventListener('click', () => setPlaying(!playing));
    $(el, '[data-act="stop"]').addEventListener('click', () => { setPlaying(false); playhead = 0; drawFrame(0); renderTimeline(); });
    $(el, '[data-act="split"]').addEventListener('click', () => {
      const c = getSelected();
      if (!c) return toast('Select a clip to split.');
      if (playhead <= c.start + 0.1 || playhead >= c.start + c.duration - 0.1) {
        return toast('Move the playhead inside the selected clip to split.');
      }
      const leftDur = +(playhead - c.start).toFixed(2);
      const rightDur = +(c.duration - leftDur).toFixed(2);
      const clone = JSON.parse(JSON.stringify(c));
      clone.id = uid();
      clone.start = +playhead.toFixed(2);
      clone.duration = rightDur;
      clone.trimStart = +((c.trimStart || 0) + leftDur * (c.speed || 1)).toFixed(2);
      clone.keyframes = [];
      commit(() => { c.duration = leftDur; project.clips.push(clone); selectedId = clone.id; });
      toast('Clip split at playhead ✂️');
    });
    $(el, '[data-act="dup"]').addEventListener('click', () => {
      const c = getSelected();
      if (!c) return;
      const clone = JSON.parse(JSON.stringify(c));
      clone.id = uid();
      clone.start = +(c.start + c.duration).toFixed(2);
      commit(() => { project.clips.push(clone); selectedId = clone.id; });
    });
    $(el, '[data-act="del"]').addEventListener('click', () => {
      if (!selectedId) return;
      commit(() => {
        project.clips = project.clips.filter((c) => c.id !== selectedId);
        selectedId = project.clips[0] ? project.clips[0].id : null;
      });
    });
    $(el, '[data-act="undo"]').addEventListener('click', () => {
      if (!hist) return;
      const prev = hist.undo(project);
      if (prev) { project = prev; renderTimeline(); renderInspector(); drawFrame(playhead); }
    });
    $(el, '[data-act="redo"]').addEventListener('click', () => {
      if (!hist) return;
      const next = hist.redo(project);
      if (next) { project = next; renderTimeline(); renderInspector(); drawFrame(playhead); }
    });
    $(el, '.wes-nle-aspect').addEventListener('change', (e) => commit(() => { project.aspect = e.target.value; }));
    $(el, '[data-tl-zoom]').addEventListener('input', (e) => { project.zoomPxPerSec = +e.target.value; renderTimeline(); });

    $(el, '[data-act="export-png"]').addEventListener('click', () => {
      drawFrame(playhead);
      canvas.toBlob((b) => { WE.download(b, 'world-studio-frame.png'); toast('Photo exported (PNG) ✅'); }, 'image/png');
    });
    $(el, '[data-act="export-json"]').addEventListener('click', () => {
      const dim = ASPECTS[project.aspect] || ASPECTS['9:16'];
      const payload = { width: dim.w, height: dim.h, tracks: TRACKS.map((t) => t.id), clips: project.clips };
      WE.download(new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' }), 'world-studio-nle-project.json');
      toast('NLE Project JSON exported ✅');
    });
    $(el, '[data-act="export-video"]').addEventListener('click', async () => {
      const type = WE.pickRecorderType ? WE.pickRecorderType() : 'video/webm';
      if (!type) return toast('This browser cannot record video.');
      setPlaying(false);
      const dur = getDuration();
      const stream = canvas.captureStream(30);
      const rec = new MediaRecorder(stream, { mimeType: type, videoBitsPerSecond: 8e6 });
      const chunks = [];
      rec.ondataavailable = (e) => e.data.size && chunks.push(e.data);
      const done = new Promise((r) => (rec.onstop = r));
      toast(`Rendering ${dur.toFixed(1)}s video…`, Math.ceil(dur * 1000));
      rec.start(250);
      const st = performance.now();
      await new Promise((res) => {
        const step = () => {
          const t = (performance.now() - st) / 1000;
          syncMediaElements(t, true);
          drawFrame(t);
          if (t < dur) requestAnimationFrame(step);
          else res();
        };
        step();
      });
      rec.stop(); await done;
      stream.getTracks().forEach((t) => t.stop());
      syncMediaElements(0, false);
      WE.download(new Blob(chunks, { type: type.split(';')[0] }), 'world-studio-edit' + (type.includes('mp4') ? '.mp4' : '.webm'));
      toast('Video exported ✅');
      drawFrame(playhead);
    });

    renderTimeline();
    renderInspector();
    drawFrame(0);

    // Pre-load same-origin gallery/feed media if passed via ?media_url=
    const initM = studio.cfg && studio.cfg.initialMedia;
    if (initM && initM.url && typeof initM.url === 'string' && initM.url.startsWith('/') && !initM.url.startsWith('//')) {
      const mId = 'init_' + uid();
      const loadIntoV1 = (mObj) => {
        mediaBin.set(mId, mObj);
        project.clips = [
          {
            id: uid(), track: 'V1', mediaId: mId, name: mObj.name, kind: mObj.kind,
            start: 0, duration: Math.min(mObj.duration || 5, 15), trimStart: 0, speed: 1, volume: 1,
            x: 0, y: 0, scale: 1, rotation: 0, opacity: 1,
            blendMode: 'source-over', transitionIn: 'none', mask: 'none',
            lut: 'none', brightness: 0, contrast: 0, saturation: 0, temperature: 0, blur: 0, vignette: 0, keyframes: [],
          },
        ];
        selectedId = project.clips[0].id;
        renderTimeline();
        renderInspector();
        drawFrame(0);
        toast('Loaded media into Video & Photo Lab ✅');
      };
      if (initM.kind === 'video') {
        const v = document.createElement('video');
        v.src = initM.url; v.muted = false; v.playsInline = true;
        v.onloadedmetadata = () => loadIntoV1({ id: mId, name: 'Feed Video', kind: 'video', el: v, duration: clamp(v.duration || 6, 1, 300), url: initM.url });
      } else {
        const img = new Image();
        img.onload = () => loadIntoV1({ id: mId, name: 'Gallery Photo', kind: 'image', el: img, duration: 5.0, url: initM.url });
        img.src = initM.url;
      }
    }

    // Auto-assemble multi-clip sequence (e.g. Goodwill Cinema Premiere reel)
    const initSeq = studio.cfg && Array.isArray(studio.cfg.initialSequence) ? studio.cfg.initialSequence : [];
    if (initSeq.length) {
      project.clips = [];
      let cursor = 0;
      let loadedCount = 0;
      initSeq.forEach((item, idx) => {
        if (!item || typeof item.url !== 'string' || !item.url.startsWith('/') || item.url.startsWith('//')) return;
        const mId = 'seq_' + idx + '_' + uid();
        const dur = clamp(Number(item.duration) || 6, 1, 120);
        const start = +cursor.toFixed(2);
        cursor += dur;
        const vClipId = uid();
        const label = String(item.label || ('Clip ' + (idx + 1)));

        project.clips.push({
          id: vClipId, track: 'V1', mediaId: mId, name: label, kind: item.kind === 'image' ? 'image' : 'video',
          start, duration: dur, trimStart: 0, speed: 1, volume: 1,
          x: 0, y: 0, scale: 1, rotation: 0, opacity: 1,
          blendMode: 'source-over', transitionIn: idx === 0 ? 'zoom-in' : 'crossfade', mask: 'none',
          lut: 'crisp_natural', brightness: 0, contrast: 4, saturation: 8, temperature: 0, blur: 0, vignette: 12, keyframes: [],
        });
        project.clips.push({
          id: uid(), track: 'T1', kind: 'text', name: 'Guest: ' + label.slice(0, 16),
          text: label, font: 'grotesk', fontSize: 42, colour: '#ffffff', bgColour: '#7c5cff',
          textAnim: 'pop', start: +(start + 0.3).toFixed(2), duration: Math.min(3.5, Math.max(1.2, dur - 0.5)),
          x: 0, y: 38, scale: 1, rotation: 0, opacity: 1, keyframes: [],
        });

        if (item.kind === 'image') {
          const img = new Image();
          img.onload = () => {
            mediaBin.set(mId, { id: mId, name: label, kind: 'image', el: img, duration: dur, url: item.url });
            loadedCount++;
            if (loadedCount === 1) drawFrame(0);
          };
          img.src = item.url;
        } else {
          const v = document.createElement('video');
          v.src = item.url; v.muted = false; v.playsInline = true;
          v.onloadedmetadata = () => {
            const actualDur = clamp(v.duration || dur, 1, 120);
            mediaBin.set(mId, { id: mId, name: label, kind: 'video', el: v, duration: actualDur, url: item.url });
            loadedCount++;
            if (loadedCount === 1) drawFrame(0);
          };
        }
      });
      if (project.clips.length) selectedId = project.clips[0].id;
      renderTimeline();
      renderInspector();
      drawFrame(0);
      toast(`Auto-assembled ${initSeq.length} clips into Video & Photo Lab ✅`);
    }

    return {
      redraw: () => { renderTimeline(); renderInspector(); drawFrame(playhead); },
      destroy: () => setPlaying(false),
    };
  };

  WE.NLE = { mount, ASPECTS, LUTS, TRACKS, parseSrt, evalClipProps };
})(typeof window !== 'undefined' ? window : globalThis);
