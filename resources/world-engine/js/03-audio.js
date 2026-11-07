/* ==========================================================================
 * ATG World Engine — 03 Audio
 * • Procedural music beds (bouncy pop, lo-fi, cinematic, chiptune, tropical)
 * • Comedy & UI sound-effects kit (boing, sad trombone, rimshot, ka-ching…)
 * • Voice Director: expressive TTS with character voices, *emphasis*,
 *   [pause], [sfx:boing] and [voice:mascot] inline cues, music ducking
 * • Microphone recording + real-time voice changer (chipmunk, robot, radio…)
 * Every sound except browser TTS is routed into a MediaStream for export.
 * ========================================================================== */
(function (root) {
  'use strict';
  const WE = root.WorldEngine;
  const mtof = (m) => 440 * Math.pow(2, (m - 69) / 12);

  class AudioEngine {
    constructor() { this.ctx = null; this.musicVol = 0.55; this.sfxVol = 0.8; this.genre = 'none'; this.timer = null; this.clipCache = new Map(); }
    ensure() {
      if (this.ctx) { if (this.ctx.state === 'suspended') this.ctx.resume(); return this.ctx; }
      const AC = root.AudioContext || root.webkitAudioContext;
      if (!AC) return null;
      const ctx = (this.ctx = new AC());
      this.master = ctx.createGain(); this.master.gain.value = 0.9;
      const comp = ctx.createDynamicsCompressor(); comp.threshold.value = -14; comp.ratio.value = 4; comp.attack.value = 0.004; comp.release.value = 0.2;
      this.master.connect(comp); comp.connect(ctx.destination);
      this.recDest = ctx.createMediaStreamDestination(); comp.connect(this.recDest);
      this.musicBus = ctx.createGain(); this.musicBus.gain.value = this.musicVol * 0.55;
      this.duck = ctx.createGain(); this.musicBus.connect(this.duck); this.duck.connect(this.master);
      this.sfxBus = ctx.createGain(); this.sfxBus.gain.value = this.sfxVol; this.sfxBus.connect(this.master);
      this.voiceBus = ctx.createGain(); this.voiceBus.connect(this.master);
      const len = ctx.sampleRate * 2; this.noise = ctx.createBuffer(1, len, ctx.sampleRate);
      const d = this.noise.getChannelData(0); for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
      return ctx;
    }
    get now() { return this.ctx ? this.ctx.currentTime : 0; }
    setMusicVolume(v) { this.musicVol = v; if (this.musicBus) this.musicBus.gain.setTargetAtTime(v * 0.55, this.now, 0.05); }
    setSfxVolume(v) { this.sfxVol = v; if (this.sfxBus) this.sfxBus.gain.setTargetAtTime(v, this.now, 0.05); }
    duckMusic(on) { if (this.duck) this.duck.gain.setTargetAtTime(on ? 0.3 : 1, this.now, on ? 0.08 : 0.4); }

    /* ---------- instrument primitives (all scheduled at absolute time t) */
    _env(g, t, a, peak, dec, sus = 0.0001) { g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(peak, t + a); g.gain.exponentialRampToValueAtTime(Math.max(sus, 0.0001), t + a + dec); }
    _osc(type, f, t, dur, peak, out, a = 0.005, detune = 0) {
      const c = this.ctx, o = c.createOscillator(), g = c.createGain();
      o.type = type; o.frequency.setValueAtTime(f, t); o.detune.value = detune;
      this._env(g, t, a, peak, dur); o.connect(g); g.connect(out); o.start(t); o.stop(t + a + dur + 0.05);
      return o;
    }
    _noise(t, dur, peak, out, ftype, freq, q = 1) {
      const c = this.ctx, s = c.createBufferSource(), f = c.createBiquadFilter(), g = c.createGain();
      s.buffer = this.noise; f.type = ftype; f.frequency.value = freq; f.Q.value = q;
      this._env(g, t, 0.002, peak, dur); s.connect(f); f.connect(g); g.connect(out);
      s.start(t, Math.random()); s.stop(t + dur + 0.05);
      return f;
    }
    kick(t, v = 1, out = this.musicBus) { const c = this.ctx, o = c.createOscillator(), g = c.createGain(); o.frequency.setValueAtTime(150, t); o.frequency.exponentialRampToValueAtTime(42, t + 0.14); this._env(g, t, 0.002, v, 0.32); o.connect(g); g.connect(out); o.start(t); o.stop(t + 0.4); }
    clap(t, v = 0.5, out = this.musicBus) { for (let i = 0; i < 3; i++) this._noise(t + i * 0.011, 0.09 + i * 0.03, v, out, 'bandpass', 1400, 0.9); }
    snare(t, v = 0.45, out = this.musicBus) { this._noise(t, 0.16, v, out, 'highpass', 1200); this._osc('triangle', 190, t, 0.08, v * 0.6, out); }
    hat(t, v = 0.16, open = false, out = this.musicBus) { this._noise(t, open ? 0.22 : 0.04, v, out, 'highpass', 7600); }
    rim(t, v = 0.3, out = this.musicBus) { this._osc('square', 1700, t, 0.03, v * 0.4, out); this._noise(t, 0.03, v * 0.5, out, 'bandpass', 3000, 3); }
    bass(t, m, dur, v = 0.35, type = 'triangle') {
      const c = this.ctx, o = c.createOscillator(), f = c.createBiquadFilter(), g = c.createGain();
      o.type = type; o.frequency.value = mtof(m); f.type = 'lowpass'; f.frequency.setValueAtTime(900, t); f.frequency.exponentialRampToValueAtTime(220, t + dur);
      this._env(g, t, 0.01, v, dur); o.connect(f); f.connect(g); g.connect(this.musicBus); o.start(t); o.stop(t + dur + 0.1);
    }
    pluck(t, m, v = 0.16, type = 'triangle', dur = 0.32) {
      const c = this.ctx, o = c.createOscillator(), f = c.createBiquadFilter(), g = c.createGain();
      o.type = type; o.frequency.value = mtof(m); f.type = 'lowpass'; f.frequency.setValueAtTime(5200, t); f.frequency.exponentialRampToValueAtTime(600, t + dur);
      this._env(g, t, 0.004, v, dur); o.connect(f); f.connect(g); g.connect(this.musicBus); o.start(t); o.stop(t + dur + 0.1);
    }
    marimba(t, m, v = 0.22) { this._osc('sine', mtof(m), t, 0.45, v, this.musicBus); this._osc('sine', mtof(m) * 4, t, 0.07, v * 0.35, this.musicBus); }
    keys(t, notes, dur, v = 0.07) { notes.forEach((m) => { this._osc('sine', mtof(m), t, dur, v, this.musicBus, 0.02); this._osc('triangle', mtof(m) * 2, t, dur * 0.5, v * 0.25, this.musicBus, 0.02, 4); }); }
    pad(t, notes, dur, v = 0.05) {
      const c = this.ctx, f = c.createBiquadFilter(), g = c.createGain();
      f.type = 'lowpass'; f.frequency.value = 1100; g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(v, t + dur * 0.35); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
      f.connect(g); g.connect(this.musicBus);
      notes.forEach((m) => [-7, 7].forEach((dt) => { const o = c.createOscillator(); o.type = 'sawtooth'; o.frequency.value = mtof(m); o.detune.value = dt; o.connect(f); o.start(t); o.stop(t + dur + 0.1); }));
    }

    /* ---------------------------------------------------------- music bed */
    startMusic(genre, seed = 1) {
      this.stopMusic();
      this.genre = genre;
      if (!genre || genre === 'none' || !GENRES[genre] || !this.ensure()) return;
      this.rnd = WE.rng(seed);
      this.step = 0; this.nextTime = this.now + 0.08;
      this.timer = setInterval(() => this._tick(), 25);
    }
    stopMusic() { if (this.timer) clearInterval(this.timer); this.timer = null; }
    _tick() {
      const G = GENRES[this.genre]; if (!G || !this.ctx) return;
      const sd = 60 / G.bpm / 4;
      while (this.nextTime < this.now + 0.14) {
        const s = this.step % 16, swing = s % 2 === 1 ? sd * (G.swing || 0) : 0;
        try { G.play(this, this.step, this.nextTime + swing); } catch (e) { /* never let music crash playback */ }
        this.nextTime += sd; this.step++;
      }
    }

    /* ---------------------------------------------------------------- sfx */
    sfx(name, when = 0) {
      if (!this.ensure()) return;
      const t = this.now + when + 0.01, o = this.sfxBus, fn = SFX[name];
      if (fn) { try { fn(this, t, o); } catch (e) { /* ignore */ } }
    }

    /* ------------------------------------------------- recorded voice clips */
    async decode(blob) {
      if (this.clipCache.has(blob)) return this.clipCache.get(blob);
      this.ensure();
      const buf = await this.ctx.decodeAudioData(await blob.arrayBuffer());
      this.clipCache.set(blob, buf);
      return buf;
    }
    /** Play a recorded/uploaded clip through a voice-changer effect. Resolves when finished. */
    async playClip(blob, fx = 'none') {
      const buf = await this.decode(blob);
      const c = this.ctx, src = c.createBufferSource(); src.buffer = buf;
      let head = src, tail;
      const chain = (node) => { head.connect(node); head = node; };
      if (fx === 'chipmunk') src.playbackRate.value = 1.38;
      if (fx === 'deep') src.playbackRate.value = 0.78;
      if (fx === 'robot') { const ring = c.createGain(); ring.gain.value = 0; const lfo = c.createOscillator(); lfo.frequency.value = 55; lfo.connect(ring.gain); lfo.start(); chain(ring); src.onended = () => lfo.stop(); }
      if (fx === 'radio') { const hp = c.createBiquadFilter(); hp.type = 'highpass'; hp.frequency.value = 500; const lp = c.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 3000; const ws = c.createWaveShaper(); const cv = new Float32Array(256); for (let i = 0; i < 256; i++) { const x = i / 128 - 1; cv[i] = Math.tanh(x * 3); } ws.curve = cv; chain(hp); chain(lp); chain(ws); }
      tail = head;
      const out = c.createGain(); out.gain.value = 1.1; tail.connect(out); out.connect(this.voiceBus);
      if (fx === 'echo' || fx === 'stadium') {
        const d = c.createDelay(1); d.delayTime.value = fx === 'echo' ? 0.26 : 0.09; const fb = c.createGain(); fb.gain.value = fx === 'echo' ? 0.38 : 0.55;
        const lp = c.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = fx === 'echo' ? 2600 : 1800;
        tail.connect(d); d.connect(lp); lp.connect(fb); fb.connect(d); const wet = c.createGain(); wet.gain.value = 0.45; lp.connect(wet); wet.connect(this.voiceBus);
      }
      this.duckMusic(true);
      src.start();
      this._clipSrc = src;
      const dur = buf.duration / src.playbackRate.value;
      return new Promise((res) => { src.onended = ((prev) => () => { if (prev) prev(); this.duckMusic(false); res(dur); })(src.onended); });
    }
    stopClip() { try { if (this._clipSrc) this._clipSrc.stop(); } catch (e) { /* ignore */ } this._clipSrc = null; }
  }

  /* ------------------------------------------------------------- genres */
  const prog = (bar, p) => p[bar % p.length];
  const GENRES = {
    bouncy: { label: '🎈 Bouncy Pop', bpm: 112, play(e, step, t) {
      const s = step % 16, bar = Math.floor(step / 16), ch = prog(bar, [[60, 64, 67], [55, 59, 62], [57, 60, 64], [53, 57, 60]]);
      if (s === 0 || s === 8 || (s === 10 && bar % 2)) e.kick(t, 0.9);
      if (s === 4 || s === 12) e.clap(t, 0.32);
      if (s % 2 === 1) e.hat(t, 0.07);
      if ([0, 3, 6, 8, 11, 14].includes(s)) e.bass(t, ch[0] - 24 + (s === 6 || s === 14 ? 12 : 0), 0.18, 0.32);
      if (s % 2 === 0) e.pluck(t, ch[[0, 1, 2, 1, 0, 2, 1, 2][s / 2]] + 12, 0.11, 'triangle');
      if (s === 15 && e.rnd() < 0.5) e._osc('sine', mtof(ch[2] + 24), t, 0.5, 0.05, e.musicBus);
    } },
    lofi: { label: '☕ Lo-Fi Chill', bpm: 78, swing: 0.22, play(e, step, t) {
      const s = step % 16, bar = Math.floor(step / 16), ch = prog(bar, [[62, 65, 69, 72], [55, 59, 62, 65], [60, 64, 67, 71], [57, 60, 64, 67]]);
      if (s === 0) e.keys(t, ch, 1.6, 0.06);
      if (s === 10) e.keys(t, ch.slice(1), 0.6, 0.04);
      if (s === 0 || s === 7 || s === 10) e.kick(t, 0.6);
      if (s === 4 || s === 12) e.rim(t, 0.28);
      if (s % 2 === 0) e.hat(t, 0.05);
      if (s === 0 || s === 10) e.bass(t, ch[0] - 24, 0.5, 0.28, 'sine');
      if (e.rnd() < 0.35) e._noise(t + e.rnd() * 0.1, 0.01, 0.05, e.musicBus, 'highpass', 3000);
    } },
    cinematic: { label: '🎻 Cinematic', bpm: 84, play(e, step, t) {
      const s = step % 16, bar = Math.floor(step / 16), ch = prog(bar, [[57, 60, 64], [53, 57, 60], [60, 64, 67], [55, 59, 62]]);
      if (s === 0) e.pad(t, ch.concat(ch[0] - 12), 60 / 84 * 4 + 0.4, 0.045);
      if (s === 0 || s === 6 || s === 8) { e.kick(t, 0.75); e._osc('sine', 70, t, 0.6, 0.25, e.musicBus); }
      if (s % 2 === 0) e.pluck(t, ch[0] + 12 + (s % 4 === 2 ? 7 : 0), 0.06, 'sawtooth', 0.18);
      if (bar % 4 === 3 && s === 8) { const f = e._noise(t, 1.3, 0.12, e.musicBus, 'bandpass', 400, 2); f.frequency.exponentialRampToValueAtTime(6000, t + 1.3); }
    } },
    chiptune: { label: '👾 Chiptune', bpm: 140, play(e, step, t) {
      const s = step % 16, bar = Math.floor(step / 16), ch = prog(bar, [[60, 64, 67], [57, 60, 64], [53, 57, 60], [55, 59, 62]]);
      e.pluck(t, ch[[0, 1, 2, 1][s % 4]] + 12, 0.045, 'square', 0.1);
      if (s % 4 === 0) e.bass(t, ch[0] - 12, 0.2, 0.22, 'triangle');
      if (s === 0 || s === 8) { const o = e._osc('square', 180, t, 0.08, 0.18, e.musicBus); o.frequency.exponentialRampToValueAtTime(40, t + 0.08); }
      if (s % 2 === 1) e._noise(t, 0.03, 0.05, e.musicBus, 'highpass', 6000);
      if (s % 4 === 2 && e.rnd() < 0.7) { const scale = [0, 2, 4, 7, 9, 12]; e._osc('square', mtof(ch[0] + 24 + scale[Math.floor(e.rnd() * scale.length)]), t, 0.12, 0.035, e.musicBus); }
    } },
    tropical: { label: '🌴 Tropical', bpm: 100, play(e, step, t) {
      const s = step % 16, bar = Math.floor(step / 16), ch = prog(bar, [[57, 60, 64], [53, 57, 60], [60, 64, 67], [55, 59, 62]]);
      if (s === 0 || s === 8) e.kick(t, 0.85);
      if ([3, 6, 11, 14].includes(s)) e.rim(t, 0.3);
      e.hat(t, s % 2 ? 0.035 : 0.06);
      if ([0, 3, 6, 10, 12].includes(s)) e.marimba(t, ch[[0, 2, 1, 2, 0][[0, 3, 6, 10, 12].indexOf(s)]] + 12, 0.16);
      if ([0, 3, 8, 11].includes(s)) e.bass(t, ch[0] - 24, 0.22, 0.3, 'sine');
    } },
  };

  /* ---------------------------------------------------------------- sfx */
  const SFX = {
    pop: (e, t, o) => { const x = e._osc('sine', 500, t, 0.08, 0.5, o); x.frequency.exponentialRampToValueAtTime(1100, t + 0.05); },
    click: (e, t, o) => e._osc('square', 1800, t, 0.015, 0.15, o),
    whoosh: (e, t, o) => { const f = e._noise(t, 0.45, 0.35, o, 'bandpass', 300, 1.2); f.frequency.exponentialRampToValueAtTime(3200, t + 0.25); f.frequency.exponentialRampToValueAtTime(500, t + 0.45); },
    swoosh: (e, t, o) => { const f = e._noise(t, 0.3, 0.3, o, 'highpass', 4000); f.frequency.exponentialRampToValueAtTime(800, t + 0.3); },
    boing: (e, t, o) => { const x = e._osc('sine', 180, t, 0.5, 0.5, o); x.frequency.exponentialRampToValueAtTime(520, t + 0.08); x.frequency.exponentialRampToValueAtTime(160, t + 0.2); x.frequency.exponentialRampToValueAtTime(420, t + 0.3); x.frequency.exponentialRampToValueAtTime(200, t + 0.45); },
    ding: (e, t, o) => { e._osc('sine', 1318.5, t, 0.9, 0.35, o); e._osc('sine', 2637, t, 0.4, 0.1, o); },
    sparkle: (e, t, o) => [1568, 2093, 2637, 3136].forEach((f, i) => e._osc('sine', f, t + i * 0.05, 0.35, 0.14, o)),
    kaching: (e, t, o) => { e._noise(t, 0.12, 0.3, o, 'bandpass', 4000, 2); [2093, 2637].forEach((f, i) => e._osc('triangle', f, t + 0.08 + i * 0.09, 0.7, 0.25, o)); },
    tada: (e, t, o) => { [[60, 64, 67], [65, 69, 72]].forEach((c, i) => c.forEach((m) => { const x = e._osc('sawtooth', mtof(m + 12), t + i * 0.16, i ? 0.9 : 0.14, 0.08, o, 0.01); x.detune.value = 6; })); },
    sadTrombone: (e, t, o) => { [[62, 0.45], [61, 0.45], [60, 0.45], [59, 1.3]].reduce((acc, [m, d]) => { const x = e._osc('sawtooth', mtof(m - 12), t + acc, d, 0.16, o, 0.03); if (d > 1) { const l = e.ctx.createOscillator(), g = e.ctx.createGain(); l.frequency.value = 5.5; g.gain.value = 9; l.connect(g); g.connect(x.frequency); l.start(t + acc); l.stop(t + acc + d); } return acc + d; }, 0); },
    rimshot: (e, t, o) => { e._osc('sine', 220, t, 0.12, 0.4, o); e._osc('sine', 160, t + 0.14, 0.14, 0.4, o); e._noise(t + 0.3, 0.7, 0.25, o, 'highpass', 6000); },
    scratch: (e, t, o) => { const f = e._noise(t, 0.35, 0.4, o, 'bandpass', 900, 4); f.frequency.setValueAtTime(400, t); f.frequency.linearRampToValueAtTime(2400, t + 0.1); f.frequency.linearRampToValueAtTime(300, t + 0.22); f.frequency.linearRampToValueAtTime(1800, t + 0.33); },
    boom: (e, t, o) => { const x = e._osc('sine', 120, t, 1.1, 0.8, o); x.frequency.exponentialRampToValueAtTime(30, t + 1); e._noise(t, 0.5, 0.25, o, 'lowpass', 400); },
    alert: (e, t, o) => [0, 0.18].forEach((d) => e._osc('square', d ? 660 : 880, t + d, 0.14, 0.12, o)),
    drumroll: (e, t, o) => { for (let i = 0; i < 26; i++) e._noise(t + i * 0.045, 0.05, 0.08 + i * 0.006, o, 'bandpass', 1800, 0.8); e._noise(t + 1.2, 0.6, 0.3, o, 'highpass', 5000); },
    slideUp: (e, t, o) => { const x = e._osc('sine', 300, t, 0.5, 0.3, o, 0.02); x.frequency.exponentialRampToValueAtTime(1400, t + 0.45); },
    slideDown: (e, t, o) => { const x = e._osc('sine', 1400, t, 0.5, 0.3, o, 0.02); x.frequency.exponentialRampToValueAtTime(260, t + 0.45); },
    bubble: (e, t, o) => { const x = e._osc('sine', 400, t, 0.12, 0.35, o); x.frequency.exponentialRampToValueAtTime(1600, t + 0.1); },
    typing: (e, t, o) => { for (let i = 0; i < 8; i++) e._noise(t + i * 0.07 + Math.random() * 0.03, 0.02, 0.18, o, 'bandpass', 2500, 2); },
    scribble: (e, t, o) => { for (let i = 0; i < 6; i++) { const f = e._noise(t + i * 0.09, 0.08, 0.06, o, 'bandpass', 2600 + Math.random() * 1600, 6); f.frequency.linearRampToValueAtTime(1800, t + i * 0.09 + 0.08); } },
    glitch: (e, t, o) => { for (let i = 0; i < 5; i++) e._osc('square', 200 + Math.random() * 1800, t + i * 0.035, 0.03, 0.1, o); },
    heartbeat: (e, t, o) => [0, 0.22].forEach((d) => e._osc('sine', 60, t + d, 0.15, 0.6, o)),
    cheer: (e, t, o) => { for (let i = 0; i < 18; i++) e._noise(t + Math.random() * 0.6, 0.6 + Math.random() * 0.6, 0.05, o, 'bandpass', 900 + Math.random() * 1600, 1.5); e._noise(t, 1.6, 0.12, o, 'bandpass', 1200, 0.5); },
    camera: (e, t, o) => { e._noise(t, 0.04, 0.4, o, 'highpass', 3000); e._noise(t + 0.08, 0.06, 0.3, o, 'highpass', 2000); },
  };
  const SFX_LABELS = { pop: 'Pop', click: 'Click', whoosh: 'Whoosh', swoosh: 'Swoosh', boing: 'Boing!', ding: 'Ding', sparkle: 'Sparkle', kaching: 'Ka-ching', tada: 'Ta-da!', sadTrombone: 'Sad trombone', rimshot: 'Ba-dum-tss', scratch: 'Record scratch', boom: 'Boom', alert: 'Alert', drumroll: 'Drum roll', slideUp: 'Slide whistle ↑', slideDown: 'Slide whistle ↓', bubble: 'Bubble', typing: 'Typing', scribble: 'Marker scribble', glitch: 'Glitch', heartbeat: 'Heartbeat', cheer: 'Crowd cheer', camera: 'Camera' };

  /* ------------------------------------------------------- voice director */
  const NOVELTY = /^(Albert|Bad News|Bahh|Bells|Boing|Bubbles|Cellos|Good News|Jester|Organ|Superstar|Trinoids|Whisper|Wobble|Zarvox|Deranged|Hysterical|Pipe Organ)\b/i;
  const PRESETS = {
    narrator: { label: 'The Narrator', emoji: '🎙️', blurb: 'Warm, trustworthy British storyteller', lang: 'en-GB', prefer: ['Daniel', 'Arthur', 'Oliver', 'Google UK English Male', 'Microsoft Ryan', 'Microsoft George'], pitch: 1, rate: 1, energy: 0.6 },
    cheeky: { label: 'Cheeky Chappy', emoji: '😜', blurb: 'Fast, playful, winks at the camera', lang: 'en-GB', prefer: ['Arthur', 'Daniel', 'Google UK English Male', 'Microsoft Ryan'], pitch: 1.14, rate: 1.1, energy: 1.15 },
    bestie: { label: 'Bubbly Bestie', emoji: '💅', blurb: 'Bright, chatty, best-friend energy', lang: 'en-GB', prefer: ['Serena', 'Kate', 'Stephanie', 'Martha', 'Google UK English Female', 'Microsoft Sonia', 'Microsoft Libby', 'Microsoft Hazel'], pitch: 1.12, rate: 1.08, energy: 1.1 },
    posh: { label: 'Posh Butler', emoji: '🎩', blurb: 'Dry, impeccably mannered wit', lang: 'en-GB', prefer: ['Oliver', 'Daniel', 'Arthur', 'Microsoft George', 'Google UK English Male'], pitch: 0.82, rate: 0.9, energy: 0.35 },
    trailer: { label: 'Movie Trailer', emoji: '🎬', blurb: '"In a world…" — big, booming, epic', lang: 'en', prefer: ['Reed', 'Rocko', 'Daniel', 'Google UK English Male', 'Alex', 'Fred'], pitch: 0.5, rate: 0.84, energy: 0.15 },
    hype: { label: 'Hype Host', emoji: '📣', blurb: 'Game-show energy, all exclamation marks', lang: 'en', prefer: ['Samantha', 'Google US English', 'Aaron', 'Nicky', 'Microsoft Guy', 'Microsoft Aria'], pitch: 1.25, rate: 1.2, energy: 1.4 },
    storyteller: { label: 'Bedtime Storyteller', emoji: '📖', blurb: 'Soft, cosy, perfect for families', lang: 'en-GB', prefer: ['Martha', 'Serena', 'Kate', 'Moira', 'Google UK English Female', 'Microsoft Libby'], pitch: 1.02, rate: 0.86, energy: 0.5 },
    scottish: { label: 'Scottish Charmer', emoji: '🏴󠁧󠁢󠁳󠁣󠁴󠁿', blurb: 'Lilting Scottish warmth', lang: 'en-GB', prefer: ['Fiona'], pitch: 1.04, rate: 1.02, energy: 0.8 },
    irish: { label: 'Irish Storyteller', emoji: '☘️', blurb: 'Twinkly Irish charm', lang: 'en-IE', prefer: ['Moira'], pitch: 1.04, rate: 1.02, energy: 0.8 },
    aussie: { label: 'Aussie Mate', emoji: '🦘', blurb: 'Laid-back, sunny, no worries', lang: 'en-AU', prefer: ['Karen', 'Lee', 'Catherine', 'Gordon'], pitch: 1.08, rate: 1.1, energy: 1 },
    robot: { label: 'Robo-Pal', emoji: '🤖', blurb: 'Beep boop. Deadpan android sidekick', lang: 'en', prefer: ['Zarvox', 'Trinoids', 'Ralph', 'Fred'], pitch: 0.7, rate: 0.95, energy: 0, novelty: true },
    mascot: { label: 'Squeaky Mascot', emoji: '🐿️', blurb: 'Tiny, high, over-excited sidekick', lang: 'en', prefer: ['Junior', 'Samantha', 'Google US English', 'Serena'], pitch: 1.85, rate: 1.18, energy: 1.2 },
    whisper: { label: 'ASMR Whisper', emoji: '🤫', blurb: 'Hushed, intimate, oddly compelling', lang: 'en', prefer: ['Whisper'], pitch: 0.95, rate: 0.82, energy: 0.2, volume: 0.75, novelty: true },
    jester: { label: 'Singing Jester', emoji: '🃏', blurb: 'Sings every line (macOS voice)', lang: 'en', prefer: ['Good News', 'Jester', 'Superstar', 'Cellos'], pitch: 1, rate: 1, energy: 0, novelty: true },
  };

  const Voice = {
    PRESETS,
    voices: [],
    supported: typeof root.speechSynthesis !== 'undefined',
    load() {
      if (!this.supported) return Promise.resolve([]);
      return new Promise((res) => {
        const grab = () => { this.voices = root.speechSynthesis.getVoices() || []; return this.voices.length; };
        if (grab()) return res(this.voices);
        root.speechSynthesis.onvoiceschanged = () => { grab(); res(this.voices); };
        setTimeout(() => res(this.voices), 1500);
      });
    },
    /** Choose the best installed voice for a preset (prefers premium/enhanced/neural voices). */
    pick(presetId, override) {
      if (override) { const v = this.voices.find((x) => x.voiceURI === override || x.name === override); if (v) return v; }
      const P = PRESETS[presetId] || PRESETS.narrator;
      let best = null, bestScore = -1e9;
      for (const v of this.voices) {
        let s = 0;
        const lang = (v.lang || '').replace('_', '-');
        if (!/^en/i.test(lang)) continue;
        if (P.lang && lang.toLowerCase() === P.lang.toLowerCase()) s += 50; else if (P.lang && lang.toLowerCase().startsWith(P.lang.toLowerCase())) s += 40; else s += 5;
        const pi = P.prefer.findIndex((n) => v.name.toLowerCase().includes(n.toLowerCase()));
        if (pi >= 0) s += 120 - pi * 6;
        if (/premium|enhanced|neural|natural|online/i.test(v.name)) s += 35;
        if (NOVELTY.test(v.name)) s += P.novelty && pi >= 0 ? 60 : -400;
        if (s > bestScore) { bestScore = s; best = v; }
      }
      return best;
    },
    /** Parse narration markup into speakable segments. */
    parse(text, presetId, tune = {}) {
      const segs = [];
      let preset = presetId;
      const parts = String(text || '').split(/(\[[^\]]+\]|\*[^*]+\*|\.\.\.|…)/);
      for (const part of parts) {
        if (!part) continue;
        const cue = part.match(/^\[(\w+)(?::\s*([\w.-]+))?\]$/);
        if (cue) {
          const k = cue[1].toLowerCase(), v = cue[2];
          if (k === 'pause') segs.push({ type: 'pause', ms: v ? Math.min(3000, parseInt(v, 10) || 450) : 450 });
          else if (k === 'sfx' && v) segs.push({ type: 'sfx', name: v });
          else if (k === 'voice' && v) preset = PRESETS[v] ? v : presetId;
          continue;
        }
        if (part === '...' || part === '…') { segs.push({ type: 'pause', ms: 380 }); continue; }
        const emph = /^\*[^*]+\*$/.test(part);
        const body = emph ? part.slice(1, -1) : part;
        const sentences = body.match(/[^.!?]+[.!?]*["')\]]*\s*/g) || [body];
        sentences.forEach((sen) => { const s = sen.trim(); if (s) segs.push({ type: 'say', text: s, emph, preset }); });
      }
      return segs;
    },
    estimate(text, presetId, tune = {}) {
      const P = PRESETS[presetId] || PRESETS.narrator;
      const rate = (P.rate || 1) * (tune.rate || 1);
      const segs = this.parse(text, presetId);
      let sec = 0;
      segs.forEach((s) => { if (s.type === 'say') sec += s.text.split(/\s+/).length / (2.65 * rate) + 0.12; if (s.type === 'pause') sec += s.ms / 1000; });
      return sec;
    },
    cancel() { this._token = (this._token || 0) + 1; if (this.supported) root.speechSynthesis.cancel(); if (this.audio) this.audio.duckMusic(false); this.speaking = false; },
    /**
     * Speak with expressive prosody. opts: { preset, tune:{pitch,rate,energy}, voiceURI, audio, onDone, onSeg }
     * Returns a promise resolved when finished (or cancelled).
     */
    speak(text, opts = {}) {
      this.cancel();
      const token = this._token;
      const segs = this.parse(text, opts.preset || 'narrator');
      const audio = (this.audio = opts.audio || this.audio);
      const tune = opts.tune || {};
      const rnd = WE.rng(WE.hash(String(text)));
      this.speaking = true;
      if (audio) audio.duckMusic(true);
      return new Promise((resolve) => {
        let i = 0;
        const finish = () => { if (token !== this._token) return resolve(false); this.speaking = false; if (audio) audio.duckMusic(false); resolve(true); };
        const next = () => {
          if (token !== this._token) return resolve(false);
          if (i >= segs.length) return finish();
          const s = segs[i++];
          if (opts.onSeg) opts.onSeg(s, i - 1, segs.length);
          if (s.type === 'pause') return setTimeout(next, s.ms);
          if (s.type === 'sfx') { if (audio) audio.sfx(s.name); return setTimeout(next, 120); }
          if (!this.supported) return setTimeout(next, s.text.split(/\s+/).length * 380);
          const P = PRESETS[s.preset] || PRESETS.narrator;
          const u = new SpeechSynthesisUtterance(s.text);
          const v = this.pick(s.preset, s.preset === (opts.preset || 'narrator') ? opts.voiceURI : null);
          if (v) { u.voice = v; u.lang = v.lang; }
          const energy = (P.energy || 0) * (tune.energy == null ? 1 : tune.energy);
          let pitch = P.pitch * (tune.pitch || 1) + (rnd() - 0.5) * 0.14 * energy;
          let rate = P.rate * (tune.rate || 1) + (rnd() - 0.5) * 0.06 * energy;
          if (s.emph) { pitch += 0.12 + 0.08 * energy; rate *= 0.9; }
          if (/\?\W*$/.test(s.text)) pitch += 0.08 * (energy + 0.5);
          if (/!\W*$/.test(s.text)) { pitch += 0.05 * energy; rate *= 1 + 0.04 * energy; }
          u.pitch = Math.max(0.1, Math.min(2, pitch)); u.rate = Math.max(0.5, Math.min(1.8, rate)); u.volume = P.volume || 1;
          let done = false;
          const go = () => { if (done) return; done = true; clearTimeout(wd); next(); };
          const wd = setTimeout(go, (s.text.split(/\s+/).length / (2.2 * u.rate)) * 1000 + 2500);
          u.onend = go; u.onerror = go;
          root.speechSynthesis.speak(u);
        };
        next();
      });
    },
  };

  /* ----------------------------------------------------------- mic recorder */
  const Mic = {
    async start() {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
      const type = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/webm'].find((t) => MediaRecorder.isTypeSupported(t)) || '';
      const rec = new MediaRecorder(stream, type ? { mimeType: type } : undefined);
      const chunks = [];
      rec.ondataavailable = (e) => e.data.size && chunks.push(e.data);
      rec.start();
      return { stop: () => new Promise((res) => { rec.onstop = () => { stream.getTracks().forEach((t) => t.stop()); res(new Blob(chunks, { type: rec.mimeType || 'audio/webm' })); }; rec.stop(); }) };
    },
  };
  const VOICE_FX = { none: 'Natural', chipmunk: 'Chipmunk', deep: 'Deep & dramatic', robot: 'Robot', radio: 'Old radio', echo: 'Echo', stadium: 'Stadium' };

  WE.Audio = new AudioEngine();
  WE.Voice = Voice;
  Voice.audio = WE.Audio;
  WE.Mic = Mic;
  WE.GENRES = Object.fromEntries(Object.entries(GENRES).map(([k, v]) => [k, v.label]));
  WE.SFX = SFX_LABELS;
  WE.VOICE_FX = VOICE_FX;
})(typeof window !== 'undefined' ? window : globalThis);
