/* ==========================================================================
 * ATG World Engine — 07 Studio UI
 * One self-contained studio with two rooms:
 *   🎬 Explainer — live stage, scene timeline, Director chat, scene inspector,
 *                  look/character designer, voice & sound, export
 *   🖼️ Posters   — template gallery, live motion preview, Director chat,
 *                  content (blank by default — samples are placeholders only),
 *                  style, photo, PNG / motion-video export
 * Mount: WorldEngine.Studio.mount(rootEl, config)
 * ========================================================================== */
(function (root) {
  'use strict';
  const WE = root.WorldEngine;
  const esc = WE.esc;
  const $ = (r, s) => r.querySelector(s);
  const $$ = (r, s) => Array.from(r.querySelectorAll(s));
  const h = (html) => { const d = document.createElement('div'); d.innerHTML = html.trim(); return d.firstElementChild; };

  /* --------------------------------------------------------- persistence */
  const store = {
    get(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* quota */ } },
  };
  const idb = (() => {
    let dbp = null;
    const db = () => dbp || (dbp = new Promise((res, rej) => { const r = indexedDB.open('world-engine', 1); r.onupgradeneeded = () => r.result.createObjectStore('blobs'); r.onsuccess = () => res(r.result); r.onerror = () => rej(r.error); }));
    const tx = (mode, fn) => db().then((d) => new Promise((res, rej) => { const t = d.transaction('blobs', mode); const r = fn(t.objectStore('blobs')); t.oncomplete = () => res(r && r.result); t.onerror = () => rej(t.error); }));
    return { put: (k, v) => tx('readwrite', (s) => s.put(v, k)).catch(() => null), get: (k) => tx('readonly', (s) => s.get(k)).catch(() => null), del: (k) => tx('readwrite', (s) => s.delete(k)).catch(() => null) };
  })();

  class History {
    constructor(limit = 60) { this.stack = []; this.redoStack = []; this.limit = limit; }
    push(state) { this.stack.push(JSON.stringify(state)); if (this.stack.length > this.limit) this.stack.shift(); this.redoStack = []; }
    undo(cur) { if (!this.stack.length) return null; this.redoStack.push(JSON.stringify(cur)); return JSON.parse(this.stack.pop()); }
    redo(cur) { if (!this.redoStack.length) return null; this.stack.push(JSON.stringify(cur)); return JSON.parse(this.redoStack.pop()); }
  }

  /* --------------------------------------------------------------- shell */
  const mount = (rootEl, cfg) => {
    cfg = Object.assign({ app: 'app', brand: {}, explainers: [], posters: [], fontUrl: '/poster-studio?asset={file}', fontFallback: {}, aiEndpoint: null, csrf: null }, cfg || {});
    WE.loadFonts(cfg.fontUrl, cfg.fontFallback).then(() => { WE.clearTextCache(); studio.redraw(); });
    WE.Voice.load().then(() => studio.voicesLoaded && studio.voicesLoaded());
    rootEl.innerHTML = '';
    const el = h(`<div class="wes">
      <div class="wes-top">
        <div class="wes-brand"><span class="wes-logo">◆</span><div><b>World Engine</b><small>${esc(cfg.brand.name || '')} · in-house creative studio</small></div></div>
        <div class="wes-rooms" role="tablist">
          <button class="wes-room on" data-room="explainer" role="tab">🎬 Explainer videos</button>
          <button class="wes-room" data-room="poster" role="tab">🖼️ Posters &amp; flyers</button>
        </div>
      </div>
      <div class="wes-body"></div>
      <div class="wes-toast" aria-live="polite"></div>
    </div>`);
    rootEl.appendChild(el);
    const toast = (msg, ms = 2600) => { const t = $(el, '.wes-toast'); t.textContent = msg; t.classList.add('on'); clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('on'), ms); };
    const studio = { el, cfg, toast, room: null, redraw() { if (studio.room) studio.room.redraw(); } };
    const open = (name) => {
      $$(el, '.wes-room').forEach((b) => b.classList.toggle('on', b.dataset.room === name));
      if (studio.room && studio.room.destroy) studio.room.destroy();
      const body = $(el, '.wes-body'); body.innerHTML = '';
      studio.room = name === 'poster' ? posterRoom(body, studio) : explainerRoom(body, studio);
      store.set('we:room:' + cfg.app, name);
    };
    $$(el, '.wes-room').forEach((b) => b.addEventListener('click', () => open(b.dataset.room)));
    open(store.get('we:room:' + cfg.app) || 'explainer');
    return studio;
  };

  /* ----------------------------------------------------------- chat box */
  const chatBox = (host, { placeholder, chips, onSend, intro }) => {
    const box = h(`<div class="wes-chat">
      <div class="wes-chat-log" aria-live="polite"></div>
      <div class="wes-chips">${chips.map((c) => `<button type="button" class="wes-chip">${esc(c)}</button>`).join('')}</div>
      <form class="wes-chat-form"><input type="text" class="wes-input" placeholder="${esc(placeholder)}" aria-label="Tell the Director what to change" maxlength="400"><button class="wes-btn wes-primary" type="submit">Send</button></form>
    </div>`);
    host.appendChild(box);
    const log = $(box, '.wes-chat-log');
    const say = (who, text) => { const m = h(`<div class="wes-msg ${who}"><span>${esc(text)}</span></div>`); log.appendChild(m); log.scrollTop = log.scrollHeight; };
    if (intro) say('bot', intro);
    const send = async (text) => { text = text.trim(); if (!text) return; say('me', text); const r = await onSend(text); if (r) say('bot', r); };
    $(box, 'form').addEventListener('submit', (ev) => { ev.preventDefault(); const i = $(box, 'input'); send(i.value); i.value = ''; });
    $$(box, '.wes-chip').forEach((c) => c.addEventListener('click', () => send(c.textContent)));
    return { say, send };
  };
  const askAI = async (cfg, kind, text, state) => {
    if (!cfg.aiEndpoint) return null;
    try {
      const res = await fetch(cfg.aiEndpoint, { method: 'POST', credentials: 'same-origin', headers: Object.assign({ 'Content-Type': 'application/json' }, cfg.csrf ? { 'X-CSRF-Token': cfg.csrf } : {}), body: JSON.stringify({ action: 'world_director', kind, command: text, state }) });
      if (!res.ok) return null;
      return await res.json();
    } catch (e) { return null; }
  };
  const tabs = (host, defs) => {
    const wrap = h(`<div class="wes-tabs"><div class="wes-tabbar" role="tablist">${defs.map((d, i) => `<button type="button" role="tab" class="wes-tab ${i ? '' : 'on'}" data-t="${d[0]}">${d[1]}</button>`).join('')}</div><div class="wes-tabpanes">${defs.map((d, i) => `<div class="wes-pane ${i ? '' : 'on'}" data-p="${d[0]}"></div>`).join('')}</div></div>`);
    host.appendChild(wrap);
    $$(wrap, '.wes-tab').forEach((b) => b.addEventListener('click', () => { $$(wrap, '.wes-tab').forEach((x) => x.classList.toggle('on', x === b)); $$(wrap, '.wes-pane').forEach((p) => p.classList.toggle('on', p.dataset.p === b.dataset.t)); }));
    const panes = {}; $$(wrap, '.wes-pane').forEach((p) => (panes[p.dataset.p] = p));
    return panes;
  };
  const field = (label, inner, hint) => `<label class="wes-field"><span>${label}</span>${inner}${hint ? `<small>${hint}</small>` : ''}</label>`;
  const options = (map, sel) => Object.entries(map).map(([k, v]) => `<option value="${esc(k)}"${k === sel ? ' selected' : ''}>${esc(v)}</option>`).join('');

  /* ============================================================ EXPLAINER */
  const explainerRoom = (host, studio) => {
    const { cfg, toast } = studio;
    const KEY = 'we:explainer:' + cfg.app;
    const blueprints = cfg.explainers || [];
    const fresh = (i = 0) => { const b = WE.clone(blueprints[i] || blueprints[0] || { title: 'My explainer', scenes: [] }); b.brand = Object.assign({}, cfg.brand, b.brand); b.character = Object.assign({}, cfg.character || {}, b.character || {}); b.blueprint = i; return b; };
    let P = store.get(KEY) || fresh(0);
    if (!P.scenes || !P.scenes.length) P = fresh(0);
    let cur = 0, dirty = false;
    store.set(KEY, P);
    const hist = new History();
    const clips = new Map();

    const el = h(`<div class="wes-ex">
      <div class="wes-stagecol">
        <div class="wes-stagebar">
          <select class="wes-select wes-bp" aria-label="Storyboard">${blueprints.map((b, i) => `<option value="${i}">${esc(b.title)}</option>`).join('')}</select>
          <div class="wes-spacer"></div>
          <button class="wes-btn" data-act="undo" title="Undo">↶</button><button class="wes-btn" data-act="redo" title="Redo">↷</button>
        </div>
        <div class="wes-stage"><canvas class="wes-canvas" aria-label="Explainer preview"></canvas><div class="wes-stage-hint">Press play — or click a scene below to edit it</div></div>
        <div class="wes-transport">
          <button class="wes-btn wes-primary wes-play" data-act="play">▶ Play</button>
          <button class="wes-btn" data-act="playscene">▶ This scene</button>
          <button class="wes-btn" data-act="stop">■ Stop</button>
          <span class="wes-time">0:00 / 0:00</span>
        </div>
        <div class="wes-timeline" aria-label="Scenes"></div>
      </div>
      <div class="wes-side"></div>
    </div>`);
    host.appendChild(el);
    const canvas = $(el, '.wes-canvas');
    const player = new WE.Explainer.Player(canvas, {
      clips,
      onScene: (i) => { cur = i; markTimeline(); },
      onTime: (i, t, len) => { $(el, '.wes-time').textContent = fmt(player.project.scenes.slice(0, i).reduce((a, _, k) => a + player.sceneLen(k), 0) + Math.min(t, len)) + ' / ' + fmt(player.totalLen()); },
      onStop: () => { $(el, '.wes-play').textContent = '▶ Play'; player.still(cur); $(el, '.wes-stage').classList.remove('playing'); },
    });
    const fmt = (s) => Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0');
    const save = () => { store.set(KEY, P); };
    const commit = (fn, quiet) => { hist.push(P); fn(); save(); refresh(); if (!quiet) dirty = true; };

    /* timeline */
    const timeline = $(el, '.wes-timeline');
    const renderTimeline = () => {
      timeline.innerHTML = '';
      P.scenes.forEach((s, i) => {
        const card = h(`<div class="wes-scene ${i === cur ? 'on' : ''}" draggable="true" data-i="${i}" tabindex="0" role="button" aria-label="Scene ${i + 1}">
          <canvas width="192" height="${Math.round(192 * (WE.Explainer.ASPECTS[P.aspect] || [16, 9])[1] / (WE.Explainer.ASPECTS[P.aspect] || [16, 9])[0])}"></canvas>
          <div class="wes-scene-meta"><b>${i + 1}</b><span class="wes-stage-tag ${esc(s.stage || '')}">${esc((WE.Explainer.STAGES[s.stage] || s.stage || '').replace('The ', ''))}</span><em>${Math.round(player.sceneLen(i))}s</em></div>
        </div>`);
        card.addEventListener('click', () => select(i));
        card.addEventListener('keydown', (e) => { if (e.key === 'Enter') select(i); });
        card.addEventListener('dragstart', (e) => { e.dataTransfer.setData('text/plain', String(i)); card.classList.add('drag'); });
        card.addEventListener('dragend', () => card.classList.remove('drag'));
        card.addEventListener('dragover', (e) => e.preventDefault());
        card.addEventListener('drop', (e) => { e.preventDefault(); const from = +e.dataTransfer.getData('text/plain'); if (from === i) return; commit(() => { const [x] = P.scenes.splice(from, 1); P.scenes.splice(i, 0, x); cur = i; }); toast(`Moved scene ${from + 1} → ${i + 1}`); });
        timeline.appendChild(card);
        requestAnimationFrame(() => player.thumb(i, $(card, 'canvas')));
      });
      const add = h(`<button class="wes-scene wes-add" aria-label="Add scene">＋<small>Add scene</small></button>`);
      add.addEventListener('click', () => commit(() => { P.scenes.splice(cur + 1, 0, { id: 's' + Date.now().toString(36), stage: 'solution', shot: 'talk', duration: 6, headline: 'Your next big point', sub: '', narration: 'Here is the next big point. *Make it count.*', props: ['star'], mood: 'happy', bullets: [] }); cur = cur + 1; }));
      timeline.appendChild(add);
    };
    const markTimeline = () => $$(timeline, '.wes-scene').forEach((c) => c.classList.toggle('on', +c.dataset.i === cur));
    const select = (i) => { if (player.playing) player.stop(true); cur = Math.max(0, Math.min(P.scenes.length - 1, i)); markTimeline(); player.still(cur); renderInspector(); };

    /* side panel */
    const panes = tabs($(el, '.wes-side'), [['dir', '💬 Director'], ['scene', '✏️ Scene'], ['look', '🎨 Look'], ['sound', '🎙️ Voice & sound'], ['out', '⬇️ Export']]);
    const chat = chatBox(panes.dir, {
      placeholder: 'e.g. “make scene 2 funnier and use the robot voice”',
      chips: ['Make it funnier', 'Switch to whiteboard', 'Use the movie trailer voice', 'Vertical for TikTok', 'Make scene 2 a before and after', 'Give the character curly hair and glasses', 'Lo-fi music', 'Surprise me'],
      intro: 'Hi! I’m your Director. Tell me what to change — tone, style, voice, music, words, characters, colours, scenes — and I’ll redo it live. Say “help” for ideas, “undo” to go back.',
      onSend: async (text) => {
        const draft = WE.clone(P);
        const r = WE.Director.explainer(text, draft, cur);
        if (r.undo) { doUndo(); return r.reply; }
        if (r.redo) { doRedo(); return r.reply; }
        if (r.changed) { hist.push(P); P = draft; if (r.select != null) cur = r.select; cur = Math.min(cur, P.scenes.length - 1); save(); refresh(); return r.reply; }
        if (r.unknown && cfg.aiEndpoint) {
          chat.say('bot', 'Thinking…');
          const ai = await askAI(cfg, 'explainer', text, { project: P, current: cur });
          if (ai && Array.isArray(ai.ops)) { const d2 = WE.clone(P); const n = WE.Director.applyOps(d2, ai.ops); if (n) { hist.push(P); P = d2; save(); refresh(); return (ai.reply || 'Done.') + ' ✅'; } }
        }
        return r.reply;
      },
    });

    const renderInspector = () => {
      const s = P.scenes[cur]; if (!s) return;
      const propsHtml = WE.Cast.PROPS.map((p) => `<button type="button" class="wes-prop ${s.props && s.props.includes(p) ? 'on' : ''}" data-prop="${p}" title="${p}"><canvas width="44" height="44" data-draw="${p}"></canvas></button>`).join('');
      panes.scene.innerHTML = `<div class="wes-form">
        <div class="wes-row2">${field('Scene', `<select class="wes-select" data-k="stage">${options({ hook: 'Hook', problem: 'The Problem', solution: 'The Solution', proof: 'Proof', cta: 'Call to Action' }, s.stage)}</select>`)}${field('Shot', `<select class="wes-select" data-k="shot">${options(WE.Explainer.SHOTS, s.shot)}</select>`)}</div>
        ${field('Headline', `<textarea class="wes-input" rows="2" data-k="headline">${esc(s.headline || '')}</textarea>`, 'Wrap words in *stars* to highlight them.')}
        ${field('Sub-line', `<input class="wes-input" data-k="sub" value="${esc(s.sub || '')}">`)}
        ${field('Narration', `<textarea class="wes-input" rows="4" data-k="narration">${esc(s.narration || '')}</textarea>`, 'Performance tags: *emphasis*, … or [pause], [sfx:boing], [voice:mascot] to switch speaker mid-line.')}
        <div class="wes-row2">${field('Mood', `<select class="wes-select" data-k="mood">${options(Object.fromEntries(WE.Cast.MOODS.map((m) => [m, m])), s.mood)}</select>`)}${field('Pose', `<select class="wes-select" data-k="pose"><option value="">auto</option>${options(Object.fromEntries(WE.Cast.POSES.map((m) => [m, m])), s.pose)}</select>`)}</div>
        <div class="wes-row2">${field('Min. length (s)', `<input class="wes-input" type="number" min="2" max="30" step="0.5" data-k="duration" value="${s.duration || 6}">`)}${field('Entry sound', `<select class="wes-select" data-k="sfx"><option value="">none</option>${options(WE.SFX, s.sfx)}</select>`)}</div>
        <div class="wes-row2">${field('Scene voice', `<select class="wes-select" data-k="voice"><option value="">project voice</option>${options(Object.fromEntries(Object.entries(WE.Voice.PRESETS).map(([k, v]) => [k, v.emoji + ' ' + v.label])), s.voice)}</select>`)}${field('Background', `<input class="wes-color" type="color" data-k="bg" value="${esc(s.bg || '#ffffff')}"><button type="button" class="wes-link" data-act="clearbg">reset</button>`)}</div>
        ${s.shot === 'stat' ? `<div class="wes-row3">${field('Number', `<input class="wes-input" data-k="stat.value" value="${esc((s.stat || {}).value || '')}">`)}${field('Prefix', `<input class="wes-input" data-k="stat.prefix" value="${esc((s.stat || {}).prefix || '')}">`)}${field('Suffix', `<input class="wes-input" data-k="stat.suffix" value="${esc((s.stat || {}).suffix || '')}">`)}</div>${field('Number label', `<input class="wes-input" data-k="stat.label" value="${esc((s.stat || {}).label || '')}">`)}` : ''}
        ${['steps', 'reveal', 'split'].includes(s.shot) ? field(s.shot === 'split' ? 'Before | After labels' : 'List items', `<input class="wes-input" data-k="bullets" value="${esc((s.bullets || []).join(' | '))}">`, 'Separate with |') : ''}
        ${s.shot === 'proof' ? field('Quote', `<input class="wes-input" data-k="quote" value="${esc(s.quote || '')}">`) + field('Who said it', `<input class="wes-input" data-k="author" value="${esc(s.author || '')}">`) : ''}
        ${s.shot === 'cta' ? `<div class="wes-row2">${field('Button', `<input class="wes-input" data-k="cta" value="${esc(s.cta || '')}">`)}${field('Link', `<input class="wes-input" data-k="url" value="${esc(s.url || '')}">`)}</div><label class="wes-check"><input type="checkbox" data-k="qr" ${s.qr ? 'checked' : ''}> Show scannable QR code</label>` : ''}
        <div class="wes-field"><span>Props (tap to toggle, max 5)</span><div class="wes-props">${propsHtml}</div></div>
        <div class="wes-row2 wes-actions"><button type="button" class="wes-btn" data-act="dup">Duplicate scene</button><button type="button" class="wes-btn wes-danger" data-act="del">Delete scene</button></div>
        <div class="wes-rec"><b>🎤 Record your own voice for this scene</b><small>Your recording plays instead of the computer voice, goes through the voice-changer, and is baked into exported videos.</small>
          <div class="wes-row2"><select class="wes-select" data-fx>${options(WE.VOICE_FX, (clips.get(s.id) || {}).fx || 'none')}</select><button type="button" class="wes-btn" data-act="rec">● Record</button></div>
          <div class="wes-row2"><button type="button" class="wes-btn" data-act="playclip" ${clips.get(s.id) ? '' : 'disabled'}>▶ Hear it</button><button type="button" class="wes-btn" data-act="delclip" ${clips.get(s.id) ? '' : 'disabled'}>Remove</button></div>
          <label class="wes-btn wes-file">⬆ Upload audio<input type="file" accept="audio/*" data-act="upclip" hidden></label>
        </div>
      </div>`;
      $$(panes.scene, 'canvas[data-draw]').forEach((c) => { const g = c.getContext('2d'); WE.Cast.drawProp(g, c.dataset.draw, 22, 22, 38, { mode: 'cartoon', pal: { ink: '#1d1b26', a: P.brand.a, b: P.brand.b, c: P.brand.c, d: '#2b2d42', l: '#fff' }, lw: 1.6 }); });
      const setK = (k, v) => commit(() => { const sc = P.scenes[cur]; if (k.startsWith('stat.')) { sc.stat = Object.assign({}, sc.stat, { [k.slice(5)]: v }); } else if (k === 'bullets') sc.bullets = String(v).split('|').map((x) => x.trim()).filter(Boolean); else if (k === 'duration') sc.duration = Math.max(2, Math.min(30, +v || 6)); else if (v === '' && ['pose', 'voice', 'sfx'].includes(k)) delete sc[k]; else sc[k] = v; }, true);
      $$(panes.scene, '[data-k]').forEach((inp) => {
        const ev = inp.tagName === 'SELECT' || inp.type === 'color' || inp.type === 'checkbox' ? 'change' : 'input';
        let tm; inp.addEventListener(ev, () => { clearTimeout(tm); tm = setTimeout(() => setK(inp.dataset.k, inp.type === 'checkbox' ? inp.checked : inp.value), ev === 'input' ? 350 : 0); });
      });
      $$(panes.scene, '.wes-prop').forEach((b) => b.addEventListener('click', () => commit(() => { const sc = P.scenes[cur]; sc.props = sc.props || []; const p = b.dataset.prop; sc.props = sc.props.includes(p) ? sc.props.filter((x) => x !== p) : sc.props.concat(p).slice(-5); }, true) || renderInspector()));
      const act = (a, fn) => { const b = $(panes.scene, `[data-act="${a}"]`); if (b) b.addEventListener(b.type === 'file' ? 'change' : 'click', fn); };
      act('clearbg', () => commit(() => delete P.scenes[cur].bg));
      act('dup', () => commit(() => { const c = WE.clone(P.scenes[cur]); c.id = 's' + Date.now().toString(36); P.scenes.splice(cur + 1, 0, c); cur++; }));
      act('del', () => { if (P.scenes.length < 2) return toast('You need at least one scene.'); commit(() => { P.scenes.splice(cur, 1); cur = Math.max(0, cur - 1); }); });
      const fxSel = $(panes.scene, '[data-fx]');
      fxSel.addEventListener('change', () => { const c = clips.get(s.id); if (c) { c.fx = fxSel.value; idb.put(KEY + ':' + s.id, c); } });
      let rec = null;
      act('rec', async (ev) => {
        const b = ev.currentTarget;
        if (rec) { const blob = await rec.stop(); rec = null; b.textContent = '● Record'; b.classList.remove('wes-live'); await storeClip(s.id, blob, fxSel.value); toast('Recorded! Press “Hear it”.'); renderInspector(); return; }
        try { rec = await WE.Mic.start(); b.textContent = '■ Stop recording'; b.classList.add('wes-live'); toast('Recording… read your narration, then press stop.'); } catch (e) { toast('Microphone unavailable — check browser permissions.'); }
      });
      act('playclip', () => { const c = clips.get(s.id); if (c) WE.Audio.playClip(c.blob, fxSel.value); });
      act('delclip', () => { clips.delete(s.id); idb.del(KEY + ':' + s.id); renderInspector(); refreshTimes(); });
      act('upclip', async (ev) => { const f = ev.target.files[0]; if (!f) return; if (f.size > 15e6) return toast('That file is over 15 MB.'); await storeClip(s.id, f, fxSel.value); renderInspector(); });
    };
    const storeClip = async (id, blob, fx) => {
      let duration = 0; try { duration = (await WE.Audio.decode(blob)).duration; } catch (e) { toast('Could not read that audio.'); return; }
      const c = { blob, fx, duration }; clips.set(id, c); await idb.put(KEY + ':' + id, c); refreshTimes();
    };
    const refreshTimes = () => renderTimeline();
    // restore stored clips
    P.scenes.forEach((s) => idb.get(KEY + ':' + s.id).then((c) => { if (c && c.blob) { clips.set(s.id, c); } }));

    const renderLook = () => {
      const S = WE.Explainer.STYLES, ch = P.character || {};
      panes.look.innerHTML = `<div class="wes-form">
        <div class="wes-field"><span>Visual world</span><div class="wes-styles">${Object.entries(S).map(([k, v]) => `<button type="button" class="wes-stylecard ${P.style === k ? 'on' : ''}" data-style="${k}"><canvas width="160" height="90"></canvas><b>${v.emoji} ${v.label}</b><small>${v.blurb}</small></button>`).join('')}</div></div>
        ${field('Shape', `<div class="wes-seg">${[['16:9', 'Widescreen'], ['9:16', 'Vertical'], ['1:1', 'Square']].map(([k, l]) => `<button type="button" class="wes-btn ${P.aspect === k ? 'on' : ''}" data-aspect="${k}">${l}<small>${k}</small></button>`).join('')}</div>`)}
        <div class="wes-row4">${['a', 'b', 'c', 'd'].map((k, i) => field(['Primary', 'Accent', 'Highlight', 'Dark'][i], `<input type="color" class="wes-color" data-brand="${k}" value="${esc(P.brand[k] || '#888888')}">`)).join('')}</div>
        <div class="wes-row2">${field('Brand name', `<input class="wes-input" data-brandtxt="name" value="${esc(P.brand.name || '')}">`)}${field('Website', `<input class="wes-input" data-brandtxt="url" value="${esc(P.brand.url || '')}">`)}</div>
        <div class="wes-subhead">Your character</div>
        <div class="wes-chardesign"><canvas class="wes-charprev" width="180" height="220"></canvas><div class="wes-form">
          ${field('Skin', `<div class="wes-swatches">${Object.entries(WE.Cast.SKINS).map(([k, v]) => `<button type="button" class="wes-sw ${ch.skin === k ? 'on' : ''}" data-ch="skin" data-v="${k}" style="background:${v}" aria-label="${k}"></button>`).join('')}</div>`)}
          <div class="wes-row2">${field('Hair', `<select class="wes-select" data-chsel="hairStyle">${options(Object.fromEntries(WE.Cast.HAIR_STYLES.map((x) => [x, x])), ch.hairStyle)}</select>`)}${field('Hair colour', `<select class="wes-select" data-chsel="hair">${options(Object.fromEntries(Object.keys(WE.Cast.HAIR).map((x) => [x, x])), ch.hair)}</select>`)}</div>
          <div class="wes-row3">${field('Top', `<input type="color" class="wes-color" data-chcol="top" value="${esc(ch.top || '#4f7cff')}">`)}${field('Trousers', `<input type="color" class="wes-color" data-chcol="bottom" value="${esc(ch.bottom || '#27315a')}">`)}${field('Extra', `<select class="wes-select" data-chsel="acc">${options(Object.fromEntries(WE.Cast.ACCESSORIES.map((x) => [x, x])), ch.acc || 'none')}</select>`)}</div>
          <label class="wes-check"><input type="checkbox" data-chhide ${ch.hidden ? 'checked' : ''}> Hide the character</label>
        </div></div>
        <label class="wes-check"><input type="checkbox" data-captions ${P.captions !== false ? 'checked' : ''}> Karaoke captions</label>
        <label class="wes-check"><input type="checkbox" data-bug ${P.showBrand !== false ? 'checked' : ''}> Brand watermark</label>
      </div>`;
      $$(panes.look, '.wes-stylecard').forEach((b) => { const c = $(b, 'canvas'); const pr = Object.assign(WE.clone(P), { style: b.dataset.style, aspect: '16:9' }); const g = c.getContext('2d'); g.setTransform(160 / 1280, 0, 0, 160 / 1280, 0, 0); WE.Explainer.renderScene(g, pr, Math.min(cur, P.scenes.length - 1), 9, 1280, 720, { still: true }); b.addEventListener('click', () => commit(() => (P.style = b.dataset.style))); });
      $$(panes.look, '[data-aspect]').forEach((b) => b.addEventListener('click', () => commit(() => (P.aspect = b.dataset.aspect))));
      $$(panes.look, '[data-brand]').forEach((i) => i.addEventListener('change', () => commit(() => (P.brand = Object.assign({}, P.brand, { [i.dataset.brand]: i.value })))));
      $$(panes.look, '[data-brandtxt]').forEach((i) => i.addEventListener('change', () => commit(() => (P.brand = Object.assign({}, P.brand, { [i.dataset.brandtxt]: i.value })))));
      $$(panes.look, '[data-ch]').forEach((b) => b.addEventListener('click', () => commit(() => (P.character = Object.assign({}, P.character, { [b.dataset.ch]: b.dataset.v })))));
      $$(panes.look, '[data-chsel]').forEach((i) => i.addEventListener('change', () => commit(() => (P.character = Object.assign({}, P.character, { [i.dataset.chsel]: i.value })))));
      $$(panes.look, '[data-chcol]').forEach((i) => i.addEventListener('change', () => commit(() => (P.character = Object.assign({}, P.character, { [i.dataset.chcol]: i.value })))));
      $(panes.look, '[data-chhide]').addEventListener('change', (e) => commit(() => (P.character = Object.assign({}, P.character, { hidden: e.target.checked }))));
      $(panes.look, '[data-captions]').addEventListener('change', (e) => commit(() => (P.captions = e.target.checked)));
      $(panes.look, '[data-bug]').addEventListener('change', (e) => commit(() => (P.showBrand = e.target.checked)));
      const cp = $(panes.look, '.wes-charprev'), g = cp.getContext('2d');
      let t0 = performance.now();
      const anim = () => { if (!cp.isConnected) return; g.clearRect(0, 0, 180, 220); WE.Cast.drawCharacter(g, 90, 212, 200, Object.assign({}, P.character, { mood: 'happy', pose: 'wave', t: (performance.now() - t0) / 1000 }), { mode: 'cartoon', pal: { ink: '#1d1b26' }, lw: 2.4, boil: Math.floor((performance.now() - t0) / 125) }); requestAnimationFrame(anim); };
      anim();
    };

    const renderSound = () => {
      const V = P.voice || {}, tune = Object.assign({ pitch: 1, rate: 1, energy: 1 }, V.tune), M = P.music || {};
      const voices = WE.Voice.voices.filter((v) => /^en/i.test(v.lang));
      panes.sound.innerHTML = `<div class="wes-form">
        <div class="wes-field"><span>Narrator</span><div class="wes-voices">${Object.entries(WE.Voice.PRESETS).map(([k, v]) => { const pv = WE.Voice.pick(k); return `<div class="wes-voice ${V.preset === k ? 'on' : ''}" data-voice="${k}" role="button" tabindex="0"><b>${v.emoji} ${v.label}</b><small>${esc(v.blurb)}</small><em>${pv ? esc(pv.name) : 'browser default'}</em><button type="button" class="wes-btn wes-mini" data-try="${k}" aria-label="Hear ${esc(v.label)}">▶</button></div>`; }).join('')}</div></div>
        ${field('Exact device voice (optional)', `<select class="wes-select" data-vuri><option value="">Best match for the narrator</option>${voices.map((v) => `<option value="${esc(v.voiceURI)}" ${V.voiceURI === v.voiceURI ? 'selected' : ''}>${esc(v.name)} (${esc(v.lang)})</option>`).join('')}</select>`, 'Tip: on a Mac, install “Premium” or “Enhanced” voices in System Settings → Accessibility → Spoken Content for much richer narration.')}
        <div class="wes-row3">${field(`Pitch <output>${tune.pitch.toFixed(2)}×</output>`, `<input type="range" min="0.5" max="1.6" step="0.02" data-tune="pitch" value="${tune.pitch}">`)}${field(`Speed <output>${tune.rate.toFixed(2)}×</output>`, `<input type="range" min="0.6" max="1.5" step="0.02" data-tune="rate" value="${tune.rate}">`)}${field(`Expression <output>${tune.energy.toFixed(1)}</output>`, `<input type="range" min="0" max="2" step="0.1" data-tune="energy" value="${tune.energy}">`)}</div>
        <label class="wes-check"><input type="checkbox" data-voiceon ${V.enabled !== false ? 'checked' : ''}> Narration on</label>
        <button type="button" class="wes-btn" data-act="speakscene">🔊 Rehearse this scene’s line</button>
        <div class="wes-subhead">Music &amp; sound effects</div>
        <div class="wes-row2">${field('Music bed', `<select class="wes-select" data-music><option value="none">No music</option>${options(WE.GENRES, M.genre)}</select>`)}${field(`Music volume <output>${Math.round((M.volume == null ? 0.55 : M.volume) * 100)}%</output>`, `<input type="range" min="0" max="1" step="0.05" data-mvol value="${M.volume == null ? 0.55 : M.volume}">`)}</div>
        <button type="button" class="wes-btn" data-act="trymusic">▶ Preview music</button>
        <label class="wes-check"><input type="checkbox" data-sfx ${P.sfx !== false ? 'checked' : ''}> Cartoon sound effects (pops, whooshes, ta-das)</label>
        <div class="wes-field"><span>Sound board — tap to hear</span><div class="wes-sfxboard">${Object.entries(WE.SFX).map(([k, v]) => `<button type="button" class="wes-btn wes-mini" data-sfxtry="${k}">${esc(v)}</button>`).join('')}</div></div>
      </div>`;
      $$(panes.sound, '.wes-voice').forEach((d) => d.addEventListener('click', (e) => { if (e.target.dataset.try) return; commit(() => (P.voice = Object.assign({}, P.voice, { preset: d.dataset.voice, enabled: true }))); }));
      $$(panes.sound, '[data-try]').forEach((b) => b.addEventListener('click', () => { WE.Audio.ensure(); const lines = { trailer: 'In a world… where planning is chaos… one app… changes… *everything*.', robot: 'Beep boop. I have analysed your problem. [pause] It is solvable. Beep.', mascot: 'Ooh ooh! Pick me! [sfx:boing] I am *so* ready for this!', whisper: 'Psst… come closer… [pause] this is the good bit.', posh: 'Splendid. Do allow me to show you something *rather* marvellous.', jester: 'Hello hello, this one is a song, and you are going to love it.' }; WE.Voice.speak(lines[b.dataset.try] || `Hello! I'm ${WE.Voice.PRESETS[b.dataset.try].label}. *This* is how ${P.brand.name || 'your story'} sounds with me. [sfx:pop]`, { preset: b.dataset.try, tune: (P.voice || {}).tune, audio: WE.Audio }); }));
      $(panes.sound, '[data-vuri]').addEventListener('change', (e) => commit(() => (P.voice = Object.assign({}, P.voice, { voiceURI: e.target.value || undefined }))));
      $$(panes.sound, '[data-tune]').forEach((r) => { r.addEventListener('input', () => { r.closest('label').querySelector('output').textContent = (+r.value).toFixed(r.dataset.tune === 'energy' ? 1 : 2) + (r.dataset.tune === 'energy' ? '' : '×'); }); r.addEventListener('change', () => commit(() => (P.voice = Object.assign({}, P.voice, { tune: Object.assign({}, (P.voice || {}).tune, { [r.dataset.tune]: +r.value }) })), true)); });
      $(panes.sound, '[data-voiceon]').addEventListener('change', (e) => commit(() => (P.voice = Object.assign({}, P.voice, { enabled: e.target.checked }))));
      $(panes.sound, '[data-act="speakscene"]').addEventListener('click', () => { const s = P.scenes[cur]; WE.Audio.ensure(); WE.Voice.speak(s.narration || '', { preset: s.voice || (P.voice || {}).preset || 'narrator', tune: (P.voice || {}).tune, voiceURI: (P.voice || {}).voiceURI, audio: WE.Audio }); });
      $(panes.sound, '[data-music]').addEventListener('change', (e) => commit(() => (P.music = Object.assign({}, P.music, { genre: e.target.value }))));
      const mv = $(panes.sound, '[data-mvol]'); mv.addEventListener('input', () => { mv.closest('label').querySelector('output').textContent = Math.round(mv.value * 100) + '%'; WE.Audio.setMusicVolume(+mv.value); }); mv.addEventListener('change', () => commit(() => (P.music = Object.assign({}, P.music, { volume: +mv.value })), true));
      let playingMusic = false;
      $(panes.sound, '[data-act="trymusic"]').addEventListener('click', (e) => { WE.Audio.ensure(); if (playingMusic) { WE.Audio.stopMusic(); e.target.textContent = '▶ Preview music'; } else { WE.Audio.setMusicVolume((P.music || {}).volume == null ? 0.55 : P.music.volume); WE.Audio.startMusic((P.music || {}).genre || 'bouncy', 3); e.target.textContent = '■ Stop music'; } playingMusic = !playingMusic; });
      $(panes.sound, '[data-sfx]').addEventListener('change', (e) => commit(() => (P.sfx = e.target.checked)));
      $$(panes.sound, '[data-sfxtry]').forEach((b) => b.addEventListener('click', () => WE.Audio.sfx(b.dataset.sfxtry)));
    };
    studio.voicesLoaded = () => { if (panes.sound.isConnected) renderSound(); };

    const renderExport = () => {
      const type = WE.pickRecorderType();
      const hasClips = P.scenes.some((s) => clips.get(s.id));
      panes.out.innerHTML = `<div class="wes-form">
        <div class="wes-note">Videos are recorded in real time, straight from this page: ${type.includes('mp4') ? '<b>MP4</b>' : type ? '<b>WebM</b>' : 'not supported in this browser'} · music, sound effects and any recorded voices are included automatically.</div>
        ${field('Quality', `<select class="wes-select" data-q><option value="1">Standard (720p)</option><option value="1.5">Full HD (1080p)</option></select>`)}
        <button type="button" class="wes-btn wes-primary" data-act="rec" ${type ? '' : 'disabled'}>🎬 Export video ${hasClips ? '(with your recorded voices)' : '(music + effects)'}</button>
        <button type="button" class="wes-btn" data-act="rectab" ${type && navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia ? '' : 'disabled'}>🎙️ Export with computer narration (share this tab’s audio)</button>
        <small class="wes-hint">Browser voices can’t be recorded directly, so for computer narration Chrome asks you to share this tab with “Share tab audio” ticked. For the best result, record your own voice per scene in the Scene tab.</small>
        <div class="wes-row2"><button type="button" class="wes-btn" data-act="vtt">Subtitles (.vtt)</button><button type="button" class="wes-btn" data-act="srt">Subtitles (.srt)</button></div>
        <div class="wes-row2"><button type="button" class="wes-btn" data-act="png">Scene still (.png)</button><button type="button" class="wes-btn" data-act="json">Save project (.json)</button></div>
        <label class="wes-btn wes-file">Open project (.json)<input type="file" accept="application/json" data-act="load" hidden></label>
        <button type="button" class="wes-btn wes-danger" data-act="reset">Reset to the original storyboard</button>
      </div>`;
      const go = async (narration) => {
        try {
          player.quality = +$(panes.out, '[data-q]').value; player.resize();
          toast('Recording… let it play to the end.', 4000); $(el, '.wes-stage').classList.add('playing');
          const blob = await player.record({ narration });
          player.quality = 1; player.resize(); player.still(cur);
          WE.download(blob, `${(P.brand.name || 'explainer').replace(/\W+/g, '-').toLowerCase()}-${P.style}.${blob.type.includes('mp4') ? 'mp4' : 'webm'}`);
          toast('Video saved ✅');
        } catch (e) { player.quality = 1; player.resize(); toast(e.message || 'Export was cancelled.'); }
      };
      $(panes.out, '[data-act="rec"]').addEventListener('click', () => go('clips'));
      $(panes.out, '[data-act="rectab"]').addEventListener('click', () => go('tab'));
      $(panes.out, '[data-act="vtt"]').addEventListener('click', () => WE.download(new Blob([WE.Explainer.subtitles(player, 'vtt')], { type: 'text/vtt' }), 'captions.vtt'));
      $(panes.out, '[data-act="srt"]').addEventListener('click', () => WE.download(new Blob([WE.Explainer.subtitles(player, 'srt')], { type: 'text/plain' }), 'captions.srt'));
      $(panes.out, '[data-act="png"]').addEventListener('click', () => { player.still(cur); canvas.toBlob((b) => WE.download(b, `scene-${cur + 1}.png`)); });
      $(panes.out, '[data-act="json"]').addEventListener('click', () => WE.download(new Blob([JSON.stringify(P, null, 2)], { type: 'application/json' }), 'explainer-project.json'));
      $(panes.out, '[data-act="load"]').addEventListener('change', async (e) => { try { const d = JSON.parse(await e.target.files[0].text()); if (!Array.isArray(d.scenes)) throw new Error(); commit(() => { P = d; cur = 0; }); toast('Project loaded.'); } catch (err) { toast('That file isn’t a World Engine project.'); } });
      $(panes.out, '[data-act="reset"]').addEventListener('click', () => commit(() => { P = fresh(P.blueprint || 0); cur = 0; }));
    };

    const doUndo = () => { const s = hist.undo(P); if (s) { P = s; cur = Math.min(cur, P.scenes.length - 1); save(); refresh(); } else toast('Nothing to undo.'); };
    const doRedo = () => { const s = hist.redo(P); if (s) { P = s; save(); refresh(); } };
    $(el, '[data-act="undo"]').addEventListener('click', doUndo);
    $(el, '[data-act="redo"]').addEventListener('click', doRedo);
    $(el, '.wes-bp').value = String(P.blueprint || 0);
    $(el, '.wes-bp').addEventListener('change', (e) => commit(() => { P = fresh(+e.target.value); cur = 0; }));
    $(el, '[data-act="play"]').addEventListener('click', () => { if (player.playing) { player.stop(); return; } $(el, '.wes-play').textContent = '❚❚ Pause'; $(el, '.wes-stage').classList.add('playing'); player.play(player.idx === cur ? cur : cur); });
    $(el, '[data-act="playscene"]').addEventListener('click', () => { const one = WE.clone(P); one.scenes = [P.scenes[cur]]; player.setProject(one); $(el, '.wes-stage').classList.add('playing'); const prev = player.opts.onStop; player.opts.onStop = (x) => { player.opts.onStop = prev; player.setProject(P); prev(x); }; player.play(0); });
    $(el, '[data-act="stop"]').addEventListener('click', () => player.stop());

    const refresh = () => { player.setProject(P); player.still(cur); if (!player.playing) $(el, '.wes-time').textContent = '0:00 / ' + fmt(player.totalLen()); renderTimeline(); renderInspector(); renderLook(); renderSound(); renderExport(); };
    refresh();
    return { redraw: () => { player.still(cur); renderTimeline(); renderLook(); }, destroy: () => player.stop(true) };
  };

  /* ============================================================== POSTERS */
  const posterRoom = (host, studio) => {
    const { cfg, toast } = studio;
    const KEY = 'we:poster:' + cfg.app;
    const samples = cfg.posters || [];
    const blank = () => ({ template: (samples[0] && samples[0].template) || 'riso', palette: 0, size: 'portrait', seed: 7, kicker: '', headline: '', sub: '', date: '', time: '', venue: '', details: [], cta: '', url: (cfg.brand && cfg.brand.url) || '', badge: '', qr: true, props: (samples[0] && samples[0].props) || [], character: Object.assign({}, cfg.character || {}), brand: Object.assign({}, cfg.brand), motion: true, showCharacter: true });
    let P = store.get(KEY) || blank();
    let sample = null; // the sample currently loaded (for "still sample text" warnings)
    const hist = new History();
    const el = h(`<div class="wes-po">
      <div class="wes-gallery" aria-label="Templates"></div>
      <div class="wes-stagecol">
        <div class="wes-stagebar"><select class="wes-select wes-sizes" aria-label="Size">${Object.entries(WE.Poster.SIZES).map(([k, v]) => `<option value="${k}">${v.label}</option>`).join('')}</select>
          <button class="wes-btn" data-act="remix" title="New variation">🎲 Remix</button><button class="wes-btn" data-act="surprise">✨ Surprise me</button><div class="wes-spacer"></div>
          <label class="wes-check wes-inline"><input type="checkbox" data-motion> Motion</label>
          <button class="wes-btn" data-act="undo">↶</button><button class="wes-btn" data-act="redo">↷</button></div>
        <div class="wes-stage wes-poster-stage"><canvas class="wes-canvas"></canvas></div>
        <div class="wes-warn" hidden></div>
        <div class="wes-transport"><button class="wes-btn wes-primary" data-act="png">⬇ Download PNG</button><button class="wes-btn" data-act="video">🎞️ Motion poster (video)</button><button class="wes-btn" data-act="share">📤 Share</button></div>
      </div>
      <div class="wes-side"></div>
    </div>`);
    host.appendChild(el);
    const canvas = $(el, '.wes-canvas'), ctx = canvas.getContext('2d');
    let raf = null, t0 = performance.now(), photoImg = null;
    const sizeOf = () => WE.Poster.SIZES[P.size] || WE.Poster.SIZES.portrait;
    const draw = (t, still) => { const s = sizeOf(); if (canvas.width !== s.w || canvas.height !== s.h) { canvas.width = s.w; canvas.height = s.h; } ctx.clearRect(0, 0, s.w, s.h); WE.Poster.render(ctx, Object.assign({}, P, { photoImg }), s.w, s.h, t, { still }); };
    const loop = () => { draw((performance.now() - t0) / 1000, false); raf = requestAnimationFrame(loop); };
    const start = () => { cancelAnimationFrame(raf); if (P.motion !== false) { t0 = performance.now(); loop(); } else draw(10, true); };
    const save = () => { const c = Object.assign({}, P); delete c.photoImg; store.set(KEY, c); };
    const commit = (fn, keepAnim) => { hist.push(Object.assign({}, P, { photoImg: undefined })); fn(); save(); afterChange(keepAnim); };
    const warn = () => { const w = WE.Poster.sampleWarnings(P, sample); const box = $(el, '.wes-warn'); box.hidden = !w.length; box.textContent = w.length ? `Heads-up: ${w.join(', ')} ${w.length === 1 ? 'is' : 'are'} still the sample text — change ${w.length === 1 ? 'it' : 'them'} before you publish.` : ''; return w; };
    const afterChange = (keepAnim) => { $(el, '.wes-sizes').value = P.size; $(el, '[data-motion]').checked = P.motion !== false; markGallery(); if (!keepAnim) start(); else if (P.motion === false) draw(10, true); renderContent(); renderStyle(); warn(); };

    /* gallery */
    const gallery = $(el, '.wes-gallery');
    const renderGallery = () => {
      gallery.innerHTML = '<div class="wes-subhead">Templates</div>';
      Object.entries(WE.Poster.TEMPLATES).forEach(([k, v]) => {
        const b = h(`<button type="button" class="wes-tpl ${P.template === k ? 'on' : ''}" data-tpl="${k}"><canvas width="108" height="135"></canvas><b>${esc(v.label)}</b><small>${esc(v.blurb)}</small></button>`);
        b.addEventListener('click', () => commit(() => { P.template = k; P.palette = 0; }));
        gallery.appendChild(b);
        requestAnimationFrame(() => { const c = $(b, 'canvas'), g = c.getContext('2d'); g.setTransform(108 / 1080, 0, 0, 108 / 1080, 0, 0); WE.Poster.render(g, Object.assign({}, previewContent(), { template: k, palette: 0, size: 'portrait', photoImg }), 1080, 1350, 9, { still: true }); });
      });
    };
    const previewContent = () => { const s = samples[0] || {}; return Object.assign({}, s, ...['kicker', 'headline', 'sub', 'date', 'time', 'venue'].filter((k) => WE.Poster.has(P[k])).map((k) => ({ [k]: P[k] })), { character: P.character, props: P.props && P.props.length ? P.props : s.props, brand: P.brand }); };
    const markGallery = () => $$(gallery, '.wes-tpl').forEach((b) => b.classList.toggle('on', b.dataset.tpl === P.template));

    const panes = tabs($(el, '.wes-side'), [['dir', '💬 Director'], ['content', '✏️ Words'], ['style', '🎨 Style & photo']]);
    chatBox(panes.dir, {
      placeholder: 'e.g. “neon style, title is "Mum’s 60th", venue: The Ivy”',
      chips: ['Surprise me', 'Make it look like a magazine cover', 'Different colours', 'Use my brand colours', 'Make it a story', 'Add balloons and a gift', 'Remove the character', 'Remix'],
      intro: 'Describe the poster you want, or tell me what to change. Everything here starts blank — only what you type ends up on the poster.',
      onSend: async (text) => {
        const draft = Object.assign({}, P); const r = WE.Director.poster(text, draft);
        if (r.undo) { doUndo(); return r.reply; } if (r.redo) { doRedo(); return r.reply; }
        if (r.changed) { if (draft.photoImg === null) photoImg = null; hist.push(P); P = draft; save(); afterChange(); return r.reply; }
        if (r.unknown && cfg.aiEndpoint) { const ai = await askAI(cfg, 'poster', text, { poster: Object.assign({}, P, { photoImg: undefined }) }); if (ai && Array.isArray(ai.ops)) { const d2 = WE.clone(Object.assign({}, P, { photoImg: undefined })); if (WE.Director.applyOps(d2, ai.ops)) { hist.push(P); P = d2; save(); afterChange(); return (ai.reply || 'Done.') + ' ✅'; } } }
        return r.reply;
      },
    });
    const renderContent = () => {
      if (panes.content.contains(document.activeElement)) return; // don't clobber typing
      const s = samples.find((x) => x.template === P.template) || samples[0] || {};
      const F = (k, label, ph, ta) => field(label, ta ? `<textarea class="wes-input" rows="2" data-pk="${k}" placeholder="${esc(ph || '')}">${esc(P[k] || '')}</textarea>` : `<input class="wes-input" data-pk="${k}" value="${esc(P[k] || '')}" placeholder="${esc(ph || '')}">`);
      panes.content.innerHTML = `<div class="wes-form">
        <div class="wes-row2">${samples.length ? `<select class="wes-select" data-sample><option value="">Fill from a sample…</option>${samples.map((x, i) => `<option value="${i}">${esc(x.label || x.headline)}</option>`).join('')}</select>` : ''}<button type="button" class="wes-btn" data-act="clear">Clear all words</button></div>
        ${F('kicker', 'Top line', s.kicker)}${F('headline', 'Title', s.headline, true)}${F('sub', 'Tagline', s.sub)}
        <div class="wes-row3">${F('date', 'Date', s.date)}${F('time', 'Time', s.time)}${F('badge', 'Badge', s.badge)}</div>
        ${F('venue', 'Venue / location', s.venue)}
        ${field('Details', `<input class="wes-input" data-pk="details" value="${esc((P.details || []).join(' | '))}" placeholder="${esc((s.details || []).join(' | '))}">`, 'Separate with |')}
        <div class="wes-row2">${F('cta', 'Button text', s.cta)}${F('url', 'Link (QR code)', s.url)}</div>
        ${P.template === 'score' ? `<div class="wes-row2">${F('score', 'Score', '942')}${F('scoreMax', 'Out of', '1000')}</div>` : ''}
        <label class="wes-check"><input type="checkbox" data-qr ${P.qr !== false ? 'checked' : ''}> Scannable QR code (when a link is set)</label>
        <small class="wes-hint">Grey text is only a suggestion — empty fields are left off the poster entirely.</small>
      </div>`;
      $$(panes.content, '[data-pk]').forEach((i) => { let tm; i.addEventListener('input', () => { clearTimeout(tm); tm = setTimeout(() => commit(() => { P[i.dataset.pk] = i.dataset.pk === 'details' ? i.value.split('|').map((x) => x.trim()).filter(Boolean) : i.value; }, true), 250); }); });
      $(panes.content, '[data-qr]').addEventListener('change', (e) => commit(() => (P.qr = e.target.checked), true));
      const ss = $(panes.content, '[data-sample]'); if (ss) ss.addEventListener('change', () => { const x = samples[+ss.value]; if (!x) return; sample = x; commit(() => { ['kicker', 'headline', 'sub', 'date', 'time', 'venue', 'cta', 'url', 'badge', 'score', 'scoreMax'].forEach((k) => (P[k] = x[k] || '')); P.details = (x.details || []).slice(); if (x.props) P.props = x.props.slice(); if (x.template) P.template = x.template; }); toast('Sample loaded — remember to make it yours.'); });
      $(panes.content, '[data-act="clear"]').addEventListener('click', () => commit(() => { ['kicker', 'headline', 'sub', 'date', 'time', 'venue', 'cta', 'badge'].forEach((k) => (P[k] = '')); P.details = []; sample = null; }));
    };
    const renderStyle = () => {
      const tpl = WE.Poster.TEMPLATES[P.template] || WE.Poster.TEMPLATES.riso, ch = P.character || {};
      panes.style.innerHTML = `<div class="wes-form">
        <div class="wes-field"><span>Palette</span><div class="wes-palettes">${tpl.palettes.map((p, i) => `<button type="button" class="wes-pal ${P.palette === i ? 'on' : ''}" data-pal="${i}">${['bg', 'a', 'b', 'c', 'ink'].filter((k) => p[k]).map((k) => `<i style="background:${p[k]}"></i>`).join('')}</button>`).join('')}<button type="button" class="wes-pal ${P.palette === 'brand' ? 'on' : ''}" data-pal="brand"><i style="background:${esc(P.brand.a || '#888')}"></i><i style="background:${esc(P.brand.b || '#888')}"></i><i style="background:${esc(P.brand.c || '#888')}"></i><small>Brand</small></button></div></div>
        <div class="wes-row3">${['bg', 'a', 'ink'].map((k, i) => field(['Background', 'Accent', 'Text'][i], `<input type="color" class="wes-color" data-pc="${k}" value="${esc((P.colors || {})[k] || (tpl.palettes[P.palette | 0] || tpl.palettes[0])[k] || '#888888')}">`)).join('')}</div>
        <button type="button" class="wes-link" data-act="resetcol">Reset colours</button>
        <div class="wes-subhead">Photo</div>
        <label class="wes-btn wes-file">📷 ${photoImg ? 'Replace photo' : 'Add a photo'}<input type="file" accept="image/*" data-photo hidden></label>
        ${photoImg ? `<div class="wes-row3">${field('Shape', `<select class="wes-select" data-ps="photoShape">${options({ rect: 'Full', rounded: 'Rounded', circle: 'Circle', arch: 'Arch', blob: 'Blob' }, P.photoShape || 'rect')}</select>`)}${field('Effect', `<select class="wes-select" data-ps="photoFx">${options({ none: 'Natural', duotone: 'Duotone', mono: 'Black & white', halftone: 'Halftone' }, P.photoFx || 'none')}</select>`)}${field('Zoom', `<input type="range" min="0.8" max="2.5" step="0.05" data-ps="photoZoom" value="${P.photoZoom || 1}">`)}</div>${field(`Photo background transparency <output>${Math.round((+P.photoBgFade || 0) * 100)}%</output>`, `<input type="range" min="0" max="100" step="5" data-photo-transparency value="${Math.round((+P.photoBgFade || 0) * 100)}">`, 'Fades the room behind the person in your photo so the poster shows through · 100% removes it')}<button type="button" class="wes-link" data-act="nophoto">Remove photo</button>` : '<small class="wes-hint">No photo? The cartoon character stars instead.</small>'}
        <div class="wes-subhead">Character</div>
        <label class="wes-check"><input type="checkbox" data-showch ${P.showCharacter !== false ? 'checked' : ''}> Show character</label>
        <div class="wes-row3">${field('Skin', `<select class="wes-select" data-pch="skin">${options(Object.fromEntries(Object.keys(WE.Cast.SKINS).map((x) => [x, x])), ch.skin)}</select>`)}${field('Hair', `<select class="wes-select" data-pch="hairStyle">${options(Object.fromEntries(WE.Cast.HAIR_STYLES.map((x) => [x, x])), ch.hairStyle)}</select>`)}${field('Mood', `<select class="wes-select" data-pmood>${options(Object.fromEntries(WE.Cast.MOODS.map((x) => [x, x])), P.mood || 'excited')}</select>`)}</div>
        <div class="wes-field"><span>Stickers &amp; props</span><div class="wes-props">${WE.Cast.PROPS.map((p) => `<button type="button" class="wes-prop ${(P.props || []).includes(p) ? 'on' : ''}" data-prop="${p}" title="${p}"><canvas width="44" height="44" data-draw="${p}"></canvas></button>`).join('')}</div></div>
      </div>`;
      $$(panes.style, 'canvas[data-draw]').forEach((c) => WE.Cast.drawProp(c.getContext('2d'), c.dataset.draw, 22, 22, 38, { mode: 'cartoon', pal: { ink: '#1d1b26', a: P.brand.a || '#ff3d7f', b: P.brand.b || '#4f7cff', c: P.brand.c || '#ffd23f', d: '#2b2d42', l: '#fff' }, lw: 1.6 }));
      $$(panes.style, '[data-pal]').forEach((b) => b.addEventListener('click', () => commit(() => { P.palette = b.dataset.pal === 'brand' ? 'brand' : +b.dataset.pal; delete P.colors; })));
      $$(panes.style, '[data-pc]').forEach((i) => i.addEventListener('change', () => commit(() => (P.colors = Object.assign({}, P.colors, { [i.dataset.pc]: i.value })))));
      $(panes.style, '[data-act="resetcol"]').addEventListener('click', () => commit(() => delete P.colors));
      $(panes.style, '[data-photo]').addEventListener('change', (e) => { const f = e.target.files[0]; if (!f) return; if (f.size > 20e6) return toast('Please use a photo under 20 MB.'); const img = new Image(); img.onload = () => { photoImg = img; idb.put(KEY + ':photo', f); commit(() => { P.photoShape = P.photoShape || 'rounded'; }); renderGallery(); }; img.src = URL.createObjectURL(f); });
      const np = $(panes.style, '[data-act="nophoto"]'); if (np) np.addEventListener('click', () => { photoImg = null; idb.del(KEY + ':photo'); commit(() => {}); renderGallery(); });
      // Sliders update the preview live while dragging, then save one undo step on release
      // (committing on every 'input' re-rendered the panel and broke the drag).
      $$(panes.style, '[data-ps]').forEach((i) => {
        if (i.type === 'range') {
          i.addEventListener('input', () => { P[i.dataset.ps] = +i.value; if (P.motion === false) draw(10, true); });
          i.addEventListener('change', () => commit(() => (P[i.dataset.ps] = +i.value), true));
        } else i.addEventListener('change', () => commit(() => (P[i.dataset.ps] = i.value)));
      });
      const pt = $(panes.style, '[data-photo-transparency]');
      if (pt) {
        // Label updates while dragging; the background analysis (~0.1s) runs once on release.
        pt.addEventListener('input', () => { pt.closest('label').querySelector('output').textContent = pt.value + '%'; });
        pt.addEventListener('change', () => commit(() => { P.photoBgFade = pt.value / 100; delete P.photoOpacity; }, true));
      }
      $(panes.style, '[data-showch]').addEventListener('change', (e) => commit(() => (P.showCharacter = e.target.checked)));
      $$(panes.style, '[data-pch]').forEach((i) => i.addEventListener('change', () => commit(() => (P.character = Object.assign({}, P.character, { [i.dataset.pch]: i.value })))));
      $(panes.style, '[data-pmood]').addEventListener('change', (e) => commit(() => (P.mood = e.target.value)));
      $$(panes.style, '.wes-prop').forEach((b) => b.addEventListener('click', () => commit(() => { const p = b.dataset.prop; P.props = (P.props || []).includes(p) ? P.props.filter((x) => x !== p) : (P.props || []).concat(p).slice(-6); })));
    };

    const exportName = () => (String(P.headline || P.template).replace(/\*/g, '').replace(/\W+/g, '-').toLowerCase().slice(0, 40) || 'poster');
    const confirmSamples = () => { const w = warn(); return !w.length || root.confirm(`These still contain sample text: ${w.join(', ')}.\nExport anyway?`); };
    $(el, '[data-act="png"]').addEventListener('click', () => { if (!confirmSamples()) return; cancelAnimationFrame(raf); const s = sizeOf(); const scale = P.size === 'a4' ? 2 : 1; const c = document.createElement('canvas'); c.width = s.w * scale; c.height = s.h * scale; const g = c.getContext('2d'); g.scale(scale, scale); WE.Poster.render(g, Object.assign({}, P, { photoImg }), s.w, s.h, 10, { still: true }); c.toBlob((b) => { WE.download(b, exportName() + '.png'); toast('Poster saved ✅'); start(); }, 'image/png'); });
    $(el, '[data-act="video"]').addEventListener('click', async () => {
      if (!confirmSamples()) return;
      const type = WE.pickRecorderType(); if (!type) return toast('This browser can’t record video.');
      cancelAnimationFrame(raf); const s = sizeOf(); canvas.width = s.w; canvas.height = s.h;
      const stream = canvas.captureStream(30), rec = new MediaRecorder(stream, { mimeType: type, videoBitsPerSecond: 8e6 }), chunks = [];
      rec.ondataavailable = (e) => e.data.size && chunks.push(e.data);
      const done = new Promise((r) => (rec.onstop = r));
      toast('Recording an 8-second motion poster…', 8000);
      rec.start(250); const st = performance.now();
      await new Promise((res) => { const f = () => { const t = (performance.now() - st) / 1000; draw(t, false); if (t < 8) requestAnimationFrame(f); else res(); }; f(); });
      rec.stop(); await done; stream.getTracks().forEach((t) => t.stop());
      WE.download(new Blob(chunks, { type: type.split(';')[0] }), exportName() + (type.includes('mp4') ? '.mp4' : '.webm'));
      toast('Motion poster saved ✅'); start();
    });
    $(el, '[data-act="share"]').addEventListener('click', () => { draw(10, true); canvas.toBlob(async (b) => { const file = new File([b], exportName() + '.png', { type: 'image/png' }); try { if (navigator.canShare && navigator.canShare({ files: [file] })) await navigator.share({ files: [file], title: String(P.headline || '').replace(/\*/g, '') }); else { WE.download(b, file.name); toast('Sharing isn’t available here, so it was downloaded instead.'); } } catch (e) { /* cancelled */ } start(); }); });
    $(el, '[data-act="remix"]').addEventListener('click', () => commit(() => (P.seed = Math.floor(Math.random() * 1e6))));
    $(el, '[data-act="surprise"]').addEventListener('click', () => commit(() => { const ks = Object.keys(WE.Poster.TEMPLATES); P.template = ks[Math.floor(Math.random() * ks.length)]; P.palette = Math.floor(Math.random() * WE.Poster.TEMPLATES[P.template].palettes.length); P.seed = Math.floor(Math.random() * 1e6); }));
    $(el, '.wes-sizes').addEventListener('change', (e) => commit(() => (P.size = e.target.value)));
    $(el, '[data-motion]').addEventListener('change', (e) => commit(() => (P.motion = e.target.checked)));
    const doUndo = () => { const s = hist.undo(P); if (s) { P = s; save(); afterChange(); } };
    const doRedo = () => { const s = hist.redo(P); if (s) { P = s; save(); afterChange(); } };
    $(el, '[data-act="undo"]').addEventListener('click', doUndo);
    $(el, '[data-act="redo"]').addEventListener('click', doRedo);
    idb.get(KEY + ':photo').then((f) => { if (f) { const img = new Image(); img.onload = () => { photoImg = img; renderStyle(); renderGallery(); }; img.src = URL.createObjectURL(f); } });
    renderGallery(); afterChange();
    return { redraw: () => { renderGallery(); start(); }, destroy: () => cancelAnimationFrame(raf) };
  };

  WE.Studio = { mount };
})(typeof window !== 'undefined' ? window : globalThis);
