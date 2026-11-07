/* ==========================================================================
 * ATG World Engine — 06 Director
 * Talk to your explainer or poster in plain English:
 *   "make scene 2 funnier", "switch to whiteboard", "use the movie trailer voice",
 *   "add a rocket to the last scene", "make the background pink", "vertical please"
 * Runs fully in the browser. Anything it can't parse locally can optionally be
 * sent to the server AI bridge, which replies with whitelisted set-operations.
 * ========================================================================== */
(function (root) {
  'use strict';
  const WE = root.WorldEngine;
  const low = (s) => String(s || '').toLowerCase();
  const pick = (a) => a[Math.floor(Math.random() * a.length)];

  const COLOURS = { red: '#ef233c', crimson: '#d00000', pink: '#ff3d7f', 'hot pink': '#ff1f8f', magenta: '#ff00c8', orange: '#ff7a00', peach: '#ffb38a', yellow: '#ffd23f', gold: '#f5b700', lime: '#a3e635', green: '#22c55e', mint: '#5eead4', teal: '#14b8a6', turquoise: '#06d6a0', cyan: '#22d3ee', sky: '#7dd3fc', blue: '#3a86ff', navy: '#1d3557', indigo: '#4f46e5', purple: '#8b5cf6', violet: '#7c3aed', lilac: '#c8b6ff', lavender: '#e0d4ff', brown: '#8b5a2b', beige: '#efe3cc', cream: '#fff6e0', white: '#ffffff', black: '#111111', grey: '#9ca3af', gray: '#9ca3af', silver: '#cbd5e1', coral: '#ff6f61', maroon: '#7f1d1d', emerald: '#10b981', royal: '#2541b2' };
  const colourIn = (t) => {
    const hex = t.match(/#([0-9a-f]{6}|[0-9a-f]{3})\b/i);
    if (hex) return '#' + hex[1];
    const names = Object.keys(COLOURS).sort((a, b) => b.length - a.length);
    for (const n of names) if (new RegExp('\\b' + n + '\\b').test(t)) return COLOURS[n];
    return null;
  };
  const quoted = (raw) => { const m = String(raw).match(/["“”'‘’]([^"“”‘’]{1,400})["“”'‘’]/); if (m) return m[1].trim(); const m2 = String(raw).match(/\b(?:to say|to read|to|say|says|reads|:)\s+(.{2,400})$/i); return m2 ? m2[1].replace(/^["'“‘]|["'”’]$/g, '').trim() : null; };
  const ORD = { first: 0, second: 1, third: 2, fourth: 3, fifth: 4, sixth: 5, seventh: 6, eighth: 7 };
  const sceneRefs = (t, project, current) => {
    const n = project.scenes.length, out = new Set();
    if (/\b(all|every|each)\s+(the\s+)?scenes?\b|\bwhole (video|thing)\b|\beverywhere\b/.test(t)) return [...Array(n).keys()];
    const re = /\bscenes?\s+((?:\d+(?:\s*(?:,|and|&|-|to)\s*)?)+)/g; let m;
    while ((m = re.exec(t))) { const nums = m[1].match(/\d+/g).map(Number); if (/-|to/.test(m[1]) && nums.length === 2) for (let i = nums[0]; i <= nums[1]; i++) out.add(i - 1); else nums.forEach((x) => out.add(x - 1)); }
    Object.entries(ORD).forEach(([w, i]) => { if (new RegExp('\\b' + w + '\\s+scene\\b').test(t)) out.add(i); });
    if (/\b(last|final|ending|end)\s+scene\b|\bthe end(ing)?\b/.test(t)) out.add(n - 1);
    const stageMap = { problem: 'problem', solution: 'solution', hook: 'hook', opening: 'hook', intro: 'hook', proof: 'proof', testimonial: 'proof', 'call to action': 'cta', cta: 'cta' };
    Object.entries(stageMap).forEach(([w, st]) => { if (new RegExp('\\b(the\\s+)?' + w + '\\s+(scene|part|bit|section)\\b').test(t)) project.scenes.forEach((s, i) => { if (s.stage === st) out.add(i); }); });
    if (/\b(this|current|selected)\s+scene\b/.test(t)) out.add(current);
    return [...out].filter((i) => i >= 0 && i < n);
  };

  const STYLE_WORDS = { whiteboard: 'whiteboard', sketch: 'whiteboard', 'hand drawn': 'whiteboard', 'hand-drawn': 'whiteboard', doodle: 'whiteboard', cartoon: 'cartoon', animated: 'cartoon', '2d': 'cartoon', motion: 'motion', kinetic: 'motion', slick: 'motion', corporate: 'motion', collage: 'collage', paper: 'collage', scrapbook: 'collage', 'mixed media': 'collage', comic: 'comic', 'comic book': 'comic', superhero: 'comic', neon: 'neon', synthwave: 'neon', arcade: 'neon', retro: 'neon', '80s': 'neon' };
  const VOICE_WORDS = { narrator: 'narrator', cheeky: 'cheeky', 'chappy': 'cheeky', cockney: 'cheeky', bestie: 'bestie', bubbly: 'bestie', posh: 'posh', butler: 'posh', trailer: 'trailer', 'movie': 'trailer', 'in a world': 'trailer', epic: 'trailer', hype: 'hype', 'game show': 'hype', 'gameshow': 'hype', storyteller: 'storyteller', bedtime: 'storyteller', scottish: 'scottish', irish: 'irish', aussie: 'aussie', australian: 'aussie', robot: 'robot', robotic: 'robot', mascot: 'mascot', squeaky: 'mascot', chipmunk: 'mascot', whisper: 'whisper', asmr: 'whisper', sing: 'jester', singing: 'jester', jester: 'jester', female: 'bestie', woman: 'bestie', girl: 'bestie', male: 'narrator', man: 'narrator', guy: 'narrator' };
  const GENRE_WORDS = { bouncy: 'bouncy', upbeat: 'bouncy', pop: 'bouncy', happy: 'bouncy', lofi: 'lofi', 'lo-fi': 'lofi', chill: 'lofi', relaxed: 'lofi', cinematic: 'cinematic', epic: 'cinematic', dramatic: 'cinematic', orchestral: 'cinematic', chiptune: 'chiptune', '8-bit': 'chiptune', '8 bit': 'chiptune', 'video game': 'chiptune', tropical: 'tropical', afro: 'tropical', beach: 'tropical', summer: 'tropical', amapiano: 'tropical' };
  const SHOT_WORDS = { 'before and after': 'split', 'before & after': 'split', split: 'split', comparison: 'split', 'big number': 'stat', stat: 'stat', statistic: 'stat', number: 'stat', counter: 'stat', steps: 'steps', 'how it works': 'steps', list: 'steps', reveal: 'reveal', device: 'reveal', phone: 'reveal', product: 'reveal', demo: 'reveal', testimonial: 'proof', quote: 'proof', review: 'proof', proof: 'proof', chaos: 'chaos', chaotic: 'chaos', overwhelm: 'chaos', question: 'hook', hook: 'hook', 'speech bubble': 'talk', talking: 'talk', 'talking head': 'talk', 'call to action': 'cta', cta: 'cta', ending: 'cta' };
  const PROP_ALIAS = { mobile: 'phone', smartphone: 'phone', computer: 'laptop', message: 'chat', messages: 'chat', whatsapp: 'chat', cash: 'money', coins: 'money', pounds: 'pound', birthday: 'cake', party: 'balloons', present: 'gift', presents: 'gift', calender: 'calendar', diary: 'calendar', time: 'clock', graph: 'chart', growth: 'chart', home: 'house', property: 'house', security: 'shield', safe: 'shield', document: 'doc', paperwork: 'doc', search: 'magnifier', idea: 'bulb', love: 'heart', family: 'tree', checklist: 'clipboard', decision: 'scales', choice: 'scales', luck: 'dice', mail: 'envelope', email: 'envelope', award: 'trophy', notification: 'bell', alert: 'warning', tick: 'check', thinking: 'brain', work: 'briefcase', invoice: 'receipt', lock: 'lock', team: 'people', announce: 'megaphone', goal: 'target', health: 'medical', hospital: 'medical', medicine: 'pills', location: 'pin', map: 'pin', song: 'music', travel: 'plane', fast: 'lightning', world: 'globe', speed: 'stopwatch', credit: 'card' };
  const findProps = (t) => { const out = []; WE.Cast.PROPS.forEach((p) => { if (new RegExp('\\b' + p + 's?\\b').test(t)) out.push(p); }); Object.entries(PROP_ALIAS).forEach(([k, v]) => { if (new RegExp('\\b' + k + '\\b').test(t) && !out.includes(v)) out.push(v); }); return out; };
  const firstKey = (t, map) => { const ks = Object.keys(map).sort((a, b) => b.length - a.length); for (const k of ks) if (new RegExp('\\b' + k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b').test(t)) return map[k]; return null; };

  /** Apply a tone rewrite to one scene: blueprint alternates first, generic fallbacks second. */
  const toneScene = (s, tone) => {
    if (s.alt && s.alt[tone]) { if (!s.alt.base) s.alt.base = { narration: s.narration, headline: s.headline }; const a = s.alt[tone]; if (typeof a === 'string') s.narration = a; else Object.assign(s, a); return true; }
    const n = String(s.narration || '');
    if (tone === 'funny') { s.narration = n.replace(/\.(\s|$)/, '. [sfx:boing] ') + (s.stage === 'problem' ? ' [pause] Honestly? Chaos. [sfx:sadTrombone]' : s.stage === 'cta' ? ' Go on. You know you want to. [sfx:rimshot]' : ''); return true; }
    if (tone === 'dramatic') { s.narration = (s.stage === 'hook' || s.stage === 'problem' ? 'In a world... ' : '') + n.replace(/\!/g, '.'); return true; }
    if (tone === 'short') { const sentences = n.match(/[^.!?]+[.!?]+/g) || [n]; s.narration = sentences.slice(0, 2).join(' ').trim(); return true; }
    if (tone === 'pro') { s.narration = n.replace(/\[sfx:[^\]]+\]/g, '').replace(/\s+/g, ' ').trim(); return true; }
    return false;
  };

  const HELP = 'Try things like: “make it funnier”, “switch to whiteboard”, “use the movie trailer voice”, “make scene 2 a before and after”, “add a rocket to the last scene”, “change the headline of scene 1 to "Party planning, sorted"”, “make the background pink”, “vertical please”, “lo-fi music”, “give the character curly hair and glasses”, “add a scene after scene 3 about free invites”, “delete scene 4”, or “surprise me”.';

  /* --------------------------------------------------------- explainer */
  const explainer = (raw, project, current = 0) => {
    const t = low(raw).replace(/[’]/g, "'"), P = project, done = [];
    if (/^\s*(undo|go back|revert)\b/.test(t)) return { undo: true, reply: 'Undone.' };
    if (/^\s*redo\b/.test(t)) return { redo: true, reply: 'Redone.' };
    if (/^\s*(help|what can you do|\?)/.test(t)) return { reply: HELP };
    let refs = sceneRefs(t, P, current);
    const sceneTargets = refs.length ? refs : null;
    const each = (fn) => (sceneTargets || [current]).forEach((i) => P.scenes[i] && fn(P.scenes[i], i));

    // structure
    let m;
    if ((m = t.match(/\b(delete|remove|cut|drop)\s+scenes?\s+(\d+)/))) { const i = +m[2] - 1; if (P.scenes[i] && P.scenes.length > 1) { P.scenes.splice(i, 1); return { changed: true, select: Math.max(0, i - 1), reply: `Scene ${i + 1} is gone. ${P.scenes.length} scenes left.` }; } return { reply: "I couldn't find that scene." }; }
    if ((m = t.match(/\bswap\s+scenes?\s+(\d+)\s+(?:and|with|&)\s+(?:scene\s+)?(\d+)/))) { const a = +m[1] - 1, b = +m[2] - 1; if (P.scenes[a] && P.scenes[b]) { [P.scenes[a], P.scenes[b]] = [P.scenes[b], P.scenes[a]]; return { changed: true, reply: `Swapped scenes ${a + 1} and ${b + 1}.` }; } }
    if ((m = t.match(/\bmove\s+scene\s+(\d+)\s+(to|before|after)\s+(?:the\s+)?(?:scene\s+|position\s+)?(\d+|start|beginning|end)/))) { const from = +m[1] - 1; let to = /start|beginning/.test(m[3]) ? 0 : m[3] === 'end' ? P.scenes.length - 1 : +m[3] - 1; if (m[2] === 'after' && /\d/.test(m[3])) to += from < to ? 0 : 1; else if (m[2] === 'before' && from < to) to -= 1; const [s] = P.scenes.splice(from, 1); P.scenes.splice(Math.max(0, Math.min(P.scenes.length, to)), 0, s); return { changed: true, select: to, reply: `Moved it — that scene is now number ${to + 1}.` }; }
    if ((m = t.match(/\bduplicate\s+scene\s+(\d+)/))) { const i = +m[1] - 1; if (P.scenes[i]) { const c = WE.clone(P.scenes[i]); c.id = 's' + Date.now().toString(36); P.scenes.splice(i + 1, 0, c); return { changed: true, select: i + 1, reply: `Duplicated scene ${i + 1}.` }; } }
    if (/\b(add|insert|new|another)\b.*\bscene\b/.test(t) && !/\bto (the )?(scene|last|first)/.test(t.replace(/scene \d+$/, ''))) {
      const after = (m = t.match(/\b(?:after|following)\s+scene\s+(\d+)/)) ? +m[1] : (m = t.match(/\bbefore\s+scene\s+(\d+)/)) ? +m[1] - 1 : /\b(start|beginning)\b/.test(t) ? 0 : P.scenes.length - 1;
      const about = quoted(raw) || ((m = String(raw).match(/\babout\s+(.{3,200})$/i)) ? m[1] : 'Here’s the best bit');
      const shot = firstKey(t, SHOT_WORDS) || 'talk';
      const sc = { id: 's' + Date.now().toString(36), stage: 'solution', shot, duration: 6, headline: about.charAt(0).toUpperCase() + about.slice(1), sub: '', narration: about.charAt(0).toUpperCase() + about.slice(1) + '.', props: findProps(t).slice(0, 3), mood: 'happy', bullets: [] };
      const at = Math.max(0, Math.min(P.scenes.length, after));
      P.scenes.splice(at, 0, sc);
      return { changed: true, select: at, reply: `Added a new “${SHOT_WORDS[Object.keys(SHOT_WORDS).find((k) => SHOT_WORDS[k] === shot)] ? WE.Explainer.SHOTS[shot] : shot}” scene at position ${at + 1}. Tweak its words in the Scene tab or just tell me.` };
    }

    // surprise
    if (/\bsurprise me\b|\bremix\b|\brandomi[sz]e\b|\bshake it up\b/.test(t)) {
      P.style = pick(Object.keys(WE.Explainer.STYLES)); P.voice = Object.assign({}, P.voice, { preset: pick(['cheeky', 'bestie', 'hype', 'trailer', 'posh', 'aussie', 'scottish', 'irish']) });
      P.music = Object.assign({}, P.music, { genre: pick(Object.keys(WE.GENRES)) });
      P.character = Object.assign({}, P.character, { hairStyle: pick(WE.Cast.HAIR_STYLES.filter((h) => h !== 'bald')), hair: pick(Object.keys(WE.Cast.HAIR)), top: pick(Object.values(COLOURS).slice(0, 20)), acc: pick(WE.Cast.ACCESSORIES) });
      return { changed: true, reply: `Remixed! ${WE.Explainer.STYLES[P.style].label} world, ${WE.Voice.PRESETS[P.voice.preset].label} on the mic, ${WE.GENRES[P.music.genre]} in the background. Say “undo” if it’s too much.` };
    }

    // tone
    const tone = /\b(funn(y|ier)|comed(y|ic)|jokes?|hilarious|silly|cheeky|playful|laugh)/.test(t) ? 'funny' : /\b(dramatic|epic|intense|cinematic feel|suspense)/.test(t) && !/\bmusic\b/.test(t) ? 'dramatic' : /\b(shorter|punchier|concise|snappier|tighter|cut it down)\b/.test(t) && !/\bseconds?\b/.test(t) ? 'short' : /\b(professional|serious|corporate|formal|sensible)\b/.test(t) && !STYLE_WORDS[t.trim()] ? 'pro' : null;
    if (tone) {
      let n = 0; (sceneTargets || [...P.scenes.keys()]).forEach((i) => { if (toneScene(P.scenes[i], tone)) n++; });
      if (!sceneTargets) {
        if (tone === 'funny') { P.voice = Object.assign({}, P.voice, { preset: P.voice && ['cheeky', 'bestie', 'hype', 'mascot'].includes(P.voice.preset) ? P.voice.preset : 'cheeky' }); if (!P.music || P.music.genre === 'none' || P.music.genre === 'cinematic') P.music = Object.assign({}, P.music, { genre: 'bouncy' }); P.sfx = true; }
        if (tone === 'dramatic') { P.voice = Object.assign({}, P.voice, { preset: 'trailer' }); P.music = Object.assign({}, P.music, { genre: 'cinematic' }); }
        if (tone === 'pro') { P.voice = Object.assign({}, P.voice, { preset: 'narrator' }); P.music = Object.assign({}, P.music, { genre: 'lofi' }); }
      }
      const msg = { funny: 'Comedy mode on 🎭 — punchier jokes, a cheekier voice and well-timed boings.', dramatic: 'Gone full blockbuster 🎬 — trailer voice, cinematic score, maximum gravitas.', short: 'Trimmed the fat ✂️ — every line is now tighter.', pro: 'Suited and booted 👔 — calmer narrator, cleaner script.' }[tone];
      return { changed: true, reply: `${msg} (${n} scene${n === 1 ? '' : 's'} rewritten.)` };
    }

    // style + aspect
    const style = firstKey(t, STYLE_WORDS);
    if (style && /\b(style|look|switch|change|make|use|turn|go|try|into|as|world|version|format)\b|^\s*\w+\s*$/.test(t) && !/\bvoice\b/.test(t)) { P.style = style; done.push(`switched to the ${WE.Explainer.STYLES[style].emoji} ${WE.Explainer.STYLES[style].label} world`); }
    if (/\b(vertical|portrait|9:16|tiktok|reels?|shorts|story)\b/.test(t)) { P.aspect = '9:16'; done.push('made it vertical (9:16) for Reels/TikTok'); }
    else if (/\b(square|1:1)\b/.test(t)) { P.aspect = '1:1'; done.push('made it square (1:1)'); }
    else if (/\b(widescreen|landscape|horizontal|16:9|youtube)\b/.test(t)) { P.aspect = '16:9'; done.push('made it widescreen (16:9)'); }

    // voice
    if (/\b(voice|narrat|speak|sound like|accent|read by|voiceover)/.test(t) || /\b(robot|trailer|mascot|whisper|aussie|scottish|irish|posh)\b/.test(t)) {
      const v = firstKey(t, VOICE_WORDS);
      if (/\b(mute|no|turn off|disable|remove|silence)\b.*\b(voice|narration|voiceover)\b/.test(t)) { P.voice = Object.assign({}, P.voice, { enabled: false }); done.push('muted the narration'); }
      else if (/\b(turn on|enable|unmute|bring back)\b.*\b(voice|narration)/.test(t)) { P.voice = Object.assign({}, P.voice, { enabled: true }); done.push('narration is back on'); }
      else if (v) { if (sceneTargets) each((s) => (s.voice = v)); else P.voice = Object.assign({}, P.voice, { preset: v, enabled: true }); done.push(`${WE.Voice.PRESETS[v].emoji} ${WE.Voice.PRESETS[v].label} is now narrating${sceneTargets ? ' scene ' + sceneTargets.map((i) => i + 1).join(', ') : ''}`); }
      const tune = Object.assign({ pitch: 1, rate: 1, energy: 1 }, (P.voice || {}).tune);
      if (/\b(deeper|lower)\b/.test(t)) { tune.pitch = Math.max(0.5, tune.pitch * 0.86); done.push('dropped the pitch'); }
      if (/\b(higher|squeakier|lighter)\b/.test(t)) { tune.pitch = Math.min(1.6, tune.pitch * 1.14); done.push('raised the pitch'); }
      if (/\b(faster|quicker|speed (it )?up)\b/.test(t)) { tune.rate = Math.min(1.5, tune.rate * 1.12); done.push('sped up the delivery'); }
      if (/\b(slower|slow (it )?down)\b/.test(t)) { tune.rate = Math.max(0.6, tune.rate * 0.88); done.push('slowed the delivery'); }
      if (/\b(more (energy|energetic|expressive|excited|animated|lively)|livelier)\b/.test(t)) { tune.energy = Math.min(2, tune.energy * 1.4); done.push('cranked up the energy'); }
      if (/\b(calmer|flatter|less excited|tone it down)\b/.test(t)) { tune.energy = tune.energy * 0.6; done.push('calmed the delivery'); }
      P.voice = Object.assign({}, P.voice, { tune });
    }
    // music
    if (/\b(music|soundtrack|song|beat|track|tune)\b/.test(t)) {
      const g = firstKey(t, GENRE_WORDS);
      if (/\b(no|mute|turn off|remove|kill|without|stop)\b/.test(t)) { P.music = Object.assign({}, P.music, { genre: 'none' }); done.push('killed the music'); }
      else if (g) { P.music = Object.assign({}, P.music, { genre: g }); done.push(`${WE.GENRES[g]} it is`); }
      if (/\blouder\b/.test(t)) { P.music = Object.assign({}, P.music, { volume: Math.min(1, ((P.music || {}).volume || 0.55) + 0.2) }); done.push('turned the music up'); }
      if (/\b(quieter|softer|lower|down)\b/.test(t)) { P.music = Object.assign({}, P.music, { volume: Math.max(0.05, ((P.music || {}).volume || 0.55) - 0.2) }); done.push('turned the music down'); }
    }
    if (/\b(captions?|subtitles?)\b/.test(t)) { P.captions = !/\b(off|no|hide|remove|disable)\b/.test(t); done.push(P.captions ? 'captions on' : 'captions off'); }
    if (/\b(sound effects?|sfx)\b/.test(t) && !(m = t.match(/\badd\b/))) { P.sfx = !/\b(off|no|mute|remove|disable)\b/.test(t); done.push(P.sfx ? 'sound effects on' : 'sound effects off'); }
    // sfx into a scene
    const sfxName = Object.keys(WE.SFX).find((k) => t.includes(k.toLowerCase()) || t.includes(low(WE.SFX[k]).replace(/[^a-z ]/g, '').trim()));
    if (sfxName && /\b(add|play|put|with|insert)\b/.test(t)) { each((s) => (s.sfx = sfxName)); done.push(`added a “${WE.SFX[sfxName]}” to scene ${(sceneTargets || [current]).map((i) => i + 1).join(', ')}`); }

    // duration
    if ((m = t.match(/\b(?:make|cut|keep)?\s*(?:it|the video|whole thing|video)?\s*(?:under|about|around|to|=)?\s*(\d{1,3})\s*(?:s|sec|secs|seconds)\b/)) && !sceneTargets) { const want = +m[1], total = P.scenes.reduce((a, s) => a + (s.duration || 6), 0); const k = want / total; P.scenes.forEach((s) => (s.duration = Math.max(2.5, +(s.duration * k).toFixed(1)))); done.push(`retimed the scenes to about ${want}s (narration may stretch longer scenes)`); }
    else if ((m = t.match(/(\d{1,2}(?:\.\d)?)\s*(?:s|sec|secs|seconds)\b/)) && sceneTargets) { each((s) => (s.duration = +m[1])); done.push(`set the duration to ${m[1]}s`); }
    else if (/\blonger\b/.test(t) && !/\bvoice\b/.test(t)) { each((s) => (s.duration = +(s.duration * 1.3).toFixed(1))); done.push('gave it more breathing room'); }
    else if (/\bshorter\b/.test(t) && sceneTargets) { each((s) => (s.duration = Math.max(2.5, +(s.duration * 0.75).toFixed(1)))); done.push('tightened the timing'); }

    // text edits
    const q = quoted(raw);
    const fieldEdit = (re, key, label) => { if (re.test(t) && q && /\b(change|set|make|update|rename|replace|use|put|write|should|to)\b/.test(t)) { each((s) => (s[key] = q)); done.push(`${label} → “${q}”`); return true; } return false; };
    fieldEdit(/\b(headline|title|heading|main text|big text)\b/, 'headline', 'headline') ||
      fieldEdit(/\b(narration|voiceover|voice over|script|line|says?)\b/, 'narration', 'narration') ||
      fieldEdit(/\b(subtitle|sub ?line|subheading|tagline|small text)\b/, 'sub', 'subline') ||
      fieldEdit(/\b(button|cta text)\b/, 'cta', 'button') ||
      fieldEdit(/\b(quote|testimonial|review)\b/, 'quote', 'quote');
    if (/\b(url|link|website|web address)\b/.test(t) && (m = String(raw).match(/([a-z0-9-]+\.[a-z.]{2,}(?:\/[^\s"']*)?)/i))) { P.brand = Object.assign({}, P.brand, { url: m[1] }); P.scenes.forEach((s) => { if (s.stage === 'cta') s.url = m[1]; }); done.push(`link set to ${m[1]}`); }
    if (/\b(brand|app|company|product) name\b/.test(t) && q) { P.brand = Object.assign({}, P.brand, { name: q }); done.push(`brand name → ${q}`); }
    if (/\b(bullets?|steps?|points?|cards?)\b/.test(t) && q && /,|;/.test(q)) { each((s) => (s.bullets = q.split(/\s*[,;]\s*/).filter(Boolean).slice(0, 4))); done.push('updated the list items'); }
    if (/\b(number|stat|figure|percentage|count)\b/.test(t) && (m = t.match(/(£|\$|€)?\s*(\d[\d,.]*)\s*(%|k|m|x|×|\+)?/))) { each((s) => { s.stat = Object.assign({}, s.stat, { value: m[2].replace(/,/g, ''), prefix: m[1] || (s.stat || {}).prefix || '', suffix: m[3] ? m[3] : (s.stat || {}).suffix || '' }); }); done.push(`the big number is now ${m[1] || ''}${m[2]}${m[3] || ''}`); }

    // shot type
    const shot = /\b(make|turn|change|switch|convert)\b.*\b(into|a|an|to)\b/.test(t) ? firstKey(t, SHOT_WORDS) : null;
    if (shot && !/\bmusic\b/.test(t)) { each((s) => (s.shot = shot)); done.push(`scene ${(sceneTargets || [current]).map((i) => i + 1).join(', ')} is now a “${WE.Explainer.SHOTS[shot]}” shot`); }

    // props
    const props = findProps(t);
    if (props.length) {
      if ((m = t.match(/\b(replace|swap)\b/)) && props.length >= 2) { each((s) => { s.props = (s.props || []).map((p) => (p === props[0] ? props[1] : p)); if (!s.props.includes(props[1])) s.props.push(props[1]); }); done.push(`swapped the ${props[0]} for a ${props[1]}`); }
      else if (/\b(remove|delete|take away|get rid|without|no)\b/.test(t)) { each((s) => (s.props = (s.props || []).filter((p) => !props.includes(p)))); done.push(`removed the ${props.join(', ')}`); }
      else if (/\b(add|put|include|show|give|with|throw in)\b/.test(t)) { each((s) => { s.props = Array.from(new Set([...(s.props || []), ...props])).slice(-5); }); done.push(`added ${props.map((p) => 'a ' + p).join(' and ')}`); }
    }
    if (/\b(no|remove all|clear) props\b/.test(t)) { each((s) => (s.props = [])); done.push('cleared the props'); }

    // character
    const ch = Object.assign({}, P.character);
    const charTalk = /\b(character|person|guy|girl|man|woman|host|mascot|presenter|he|she|they|them|hair|skin|outfit|shirt|top|hoodie|glasses|cap|hat|headphones|crown|bow)\b/.test(t);
    if (charTalk) {
      const mood = WE.Cast.MOODS.find((x) => new RegExp('\\b' + x + '\\b').test(t)) || (/\b(scared|surprised)\b/.test(t) ? 'shocked' : /\b(cross|mad|furious)\b/.test(t) ? 'angry' : /\b(smil|cheerful|joy)/.test(t) ? 'happy' : null);
      const pose = /\bwav/.test(t) ? 'wave' : /\bpoint/.test(t) ? 'point' : /\b(celebrat|dance|jump|cheer)/.test(t) ? 'celebrate' : /\bthumbs?\b/.test(t) ? 'thumbsUp' : /\bshrug/.test(t) ? 'shrug' : /\bthink/.test(t) ? 'think' : null;
      if (mood && /\b(make|look|feel|be|more|seem)\b/.test(t)) { each((s) => (s.mood = mood)); done.push(`the character looks ${mood}`); }
      if (pose) { each((s) => (s.pose = pose)); done.push(`pose → ${pose}`); }
      const hs = WE.Cast.HAIR_STYLES.find((h) => new RegExp('\\b' + h + '\\b').test(t)) || (/\b(dreads|locs|twists)\b/.test(t) ? 'braids' : /\b(bald|shaved)\b/.test(t) ? 'bald' : null);
      if (hs && /\bhair|bald|afro|bun|ponytail|braids|mohawk\b/.test(t)) { ch.hairStyle = hs; done.push(`${hs} hair`); }
      const hc = Object.keys(WE.Cast.HAIR).find((h) => new RegExp('\\b' + h + '\\b').test(t));
      if (hc && /\bhair\b/.test(t)) { ch.hair = hc; done.push(`${hc} hair colour`); }
      const sk = Object.keys(WE.Cast.SKINS).find((s) => new RegExp('\\b' + s + '\\b').test(t));
      if (sk) { ch.skin = sk; done.push(`${sk} skin tone`); }
      else if (/\b(darker|deeper) skin\b/.test(t)) { const ks = Object.keys(WE.Cast.SKINS); ch.skin = ks[Math.min(ks.length - 1, ks.indexOf(ch.skin || 'honey') + 1)]; done.push('deeper skin tone'); }
      else if (/\blighter skin\b/.test(t)) { const ks = Object.keys(WE.Cast.SKINS); ch.skin = ks[Math.max(0, ks.indexOf(ch.skin || 'honey') - 1)]; done.push('lighter skin tone'); }
      const acc = WE.Cast.ACCESSORIES.find((a) => a !== 'none' && new RegExp('\\b' + a + '(es)?\\b').test(t)) || (/\bhat\b/.test(t) ? 'cap' : null);
      if (acc) { ch.acc = /\b(remove|no|take off|without)\b/.test(t) ? 'none' : acc; done.push(ch.acc === 'none' ? 'removed the accessory' : `added ${acc}`); }
      if (/\b(woman|female|girl|lady)\b/.test(t) && !/\bvoice\b/.test(t) && !hs) { ch.hairStyle = pick(['long', 'bun', 'ponytail', 'curly', 'braids', 'afro']); done.push('new look for the character'); }
      if (/\b(man|male|guy|boy|bloke)\b/.test(t) && !/\bvoice\b/.test(t) && !hs) { ch.hairStyle = pick(['short', 'mohawk', 'bald', 'curly']); done.push('new look for the character'); }
      const col = colourIn(t);
      if (col && /\b(shirt|top|hoodie|jumper|outfit|clothes|t-shirt|tee)\b/.test(t)) { ch.top = col; done.push('new outfit colour'); }
      else if (col && /\b(trousers|jeans|pants|bottoms|skirt)\b/.test(t)) { ch.bottom = col; done.push('new trousers'); }
      if (/\b(hide|remove|no|get rid of)\b.*\b(character|person|mascot|presenter)\b/.test(t)) { ch.hidden = true; done.push('hid the character'); }
      if (/\b(show|bring back|add)\b.*\b(character|person|mascot|presenter)\b/.test(t)) { ch.hidden = false; done.push('the character is back'); }
      P.character = ch;
    }
    // colours
    const col = colourIn(t);
    if (col && !(charTalk && /\b(shirt|top|hoodie|outfit|trousers|jeans|hair|skin)\b/.test(t))) {
      if (/\b(background|backdrop|bg|sky)\b/.test(t)) { each((s) => (s.bg = col)); done.push(`background → ${col}`); }
      else if (/\b(secondary|accent|second)\b/.test(t)) { P.brand = Object.assign({}, P.brand, { b: col }); done.push(`accent colour → ${col}`); }
      else if (/\b(highlight|third)\b/.test(t)) { P.brand = Object.assign({}, P.brand, { c: col }); done.push(`highlight colour → ${col}`); }
      else if (/\b(colou?r|brand|primary|make it|more)\b/.test(t)) { P.brand = Object.assign({}, P.brand, { a: col }); done.push(`brand colour → ${col}`); }
    }
    if (/\b(reset|default)\b.*\bbackground/.test(t)) { each((s) => delete s.bg); done.push('background reset'); }

    if (!done.length) return { unknown: true, reply: `I didn't quite catch that. ${HELP}` };
    return { changed: true, reply: done.map((d, i) => (i ? d : d.charAt(0).toUpperCase() + d.slice(1))).join(', ') + '. ✅' };
  };

  /* ------------------------------------------------------------ poster */
  const TPL_WORDS = { riso: 'riso', risograph: 'riso', swiss: 'swiss', minimal: 'swiss', minimalist: 'swiss', grid: 'swiss', groovy: 'groovy', '70s': 'groovy', seventies: 'groovy', retro: 'groovy', chrome: 'chrome', y2k: 'chrome', holographic: 'chrome', memphis: 'memphis', '90s': 'memphis', magazine: 'editorial', editorial: 'editorial', cover: 'editorial', aurora: 'aurora', glass: 'aurora', gradient: 'aurora', modern: 'aurora', paper: 'papercut', papercut: 'papercut', 'paper cut': 'papercut', neon: 'neon', nightclub: 'neon', comic: 'comic', bauhaus: 'bauhaus', geometric: 'bauhaus', brutal: 'brutal', brutalist: 'brutal', bold: 'brutal', blueprint: 'blueprint', technical: 'blueprint', ticket: 'ticket', golden: 'ticket', chalk: 'chalk', chalkboard: 'chalk', blackboard: 'chalk', kinetic: 'kinetic', typography: 'kinetic', sticker: 'sticker', stickers: 'sticker', score: 'score', gauge: 'score', scrapbook: 'scrapbook', polaroid: 'scrapbook', movie: 'cinema', cinema: 'cinema', film: 'cinema', blockbuster: 'cinema' };
  const SIZE_WORDS = { story: 'story', stories: 'story', vertical: 'story', tiktok: 'story', reel: 'story', portrait: 'portrait', instagram: 'portrait', '4:5': 'portrait', square: 'square', '1:1': 'square', landscape: 'landscape', widescreen: 'landscape', banner: 'landscape', twitter: 'landscape', facebook: 'landscape', a4: 'a4', print: 'a4', flyer: 'a4' };
  const POSTER_HELP = 'Try: “switch to neon”, “make it look like a magazine cover”, “change the title to "Mum’s 60th"”, “date is Saturday 14 June”, “venue: The Ivy, Chelsea”, “use my brand colours”, “different colours”, “remix”, “make it a story”, “remove the character”, “add balloons and a gift”, “photo in a circle, black and white”, “no QR code”, or “surprise me”.';
  const poster = (raw, P) => {
    const t = low(raw).replace(/[’]/g, "'"), done = [];
    if (/^\s*(undo|go back)\b/.test(t)) return { undo: true, reply: 'Undone.' };
    if (/^\s*redo\b/.test(t)) return { redo: true, reply: 'Redone.' };
    if (/^\s*(help|what can you do|\?)/.test(t)) return { reply: POSTER_HELP };
    if (/\bsurprise me\b|\brandom\b/.test(t)) { P.template = pick(Object.keys(WE.Poster.TEMPLATES)); P.palette = Math.floor(Math.random() * 3); P.seed = Math.floor(Math.random() * 1e6); return { changed: true, reply: `Ta-da — ${WE.Poster.TEMPLATES[P.template].label}. Say “remix” for another take on it.` }; }
    if (/\b(remix|another (version|take)|shuffle|re-?roll|again)\b/.test(t)) { P.seed = Math.floor(Math.random() * 1e6); done.push('new remix'); }
    const tp = firstKey(t, TPL_WORDS);
    if (tp && !/\b(colou?rs?|palette)\b/.test(t)) { P.template = tp; P.palette = 0; done.push(`switched to ${WE.Poster.TEMPLATES[tp].label}`); }
    const sz = firstKey(t, SIZE_WORDS);
    if (sz && /\b(make|size|format|resize|as|for|into|switch|change)\b|^\s*\w+\s*$/.test(t)) { P.size = sz; done.push(`size → ${WE.Poster.SIZES[sz].label}`); }
    if (/\b(different|other|new|change the|swap|next) (colou?rs?|palette)\b/.test(t)) { P.palette = ((P.palette | 0) + 1) % WE.Poster.TEMPLATES[P.template].palettes.length; done.push('new colour palette'); }
    if (/\bbrand colou?rs?\b/.test(t)) { P.palette = 'brand'; done.push('using your brand colours'); }
    const col = colourIn(t);
    if (col) { P.colors = Object.assign({}, P.colors); if (/\b(background|bg|backdrop)\b/.test(t)) P.colors.bg = col; else if (/\b(text|ink|font colou?r)\b/.test(t)) P.colors.ink = col; else if (/\b(accent|secondary)\b/.test(t)) P.colors.b = col; else P.colors.a = col; done.push(`colour → ${col}`); }
    if (/\breset colou?rs?\b/.test(t)) { delete P.colors; done.push('colours reset'); }
    const q = quoted(raw);
    const FIELDS = [[/\b(title|headline|event name|name of the event|heading)\b/, 'headline'], [/\b(kicker|presenter|presented by|hosted by|top line|small heading)\b/, 'kicker'], [/\b(subtitle|tagline|sub ?line|description)\b/, 'sub'], [/\b(date|day)\b/, 'date'], [/\b(time|starts? at|doors)\b/, 'time'], [/\b(venue|location|place|address|where)\b/, 'venue'], [/\b(button|cta|call to action)\b/, 'cta'], [/\b(badge|sticker text|price|entry)\b/, 'badge'], [/\b(score)\b/, 'score']];
    for (const [re, key] of FIELDS) {
      if (!re.test(t)) continue;
      if (/\b(remove|delete|clear|no|without|hide)\b/.test(t)) { P[key] = ''; done.push(`cleared the ${key}`); break; }
      const val = q || ((/:\s*(.+)$/.exec(raw) || /\b(?:is|are|=)\s+(.+)$/i.exec(raw) || [])[1]);
      if (val) { P[key] = val.trim(); done.push(`${key} → “${P[key]}”`); break; }
    }
    if (/\b(details|perks|extras|bullet points?|highlights)\b/.test(t) && q) { P.details = q.split(/\s*[,;•]\s*/).filter(Boolean).slice(0, 5); done.push('updated the details'); }
    if (/\b(url|link|website|rsvp link)\b/.test(t)) { const m = String(raw).match(/((?:https?:\/\/)?[a-z0-9-]+\.[a-z.]{2,}(?:\/[^\s"']*)?)/i); if (m) { P.url = m[1]; P.qr = true; done.push(`QR now points to ${m[1]}`); } }
    if (/\bqr\b/.test(t)) { P.qr = !/\b(no|remove|hide|without|off)\b/.test(t); done.push(P.qr ? 'QR code on' : 'QR code off'); }
    if (/\b(character|person|mascot|cartoon)\b/.test(t)) { if (/\b(remove|hide|no|without)\b/.test(t)) { P.showCharacter = false; done.push('character hidden'); } else if (/\b(add|show|bring back|with)\b/.test(t)) { P.showCharacter = true; done.push('character added'); } }
    const mood = WE.Cast.MOODS.find((x) => new RegExp('\\b' + x + '\\b').test(t)); if (mood && /\b(look|make|character|mood)\b/.test(t)) { P.mood = mood; done.push(`mood → ${mood}`); }
    if (/\b(circle|round)\b/.test(t) && /\bphoto|picture|image\b/.test(t)) { P.photoShape = 'circle'; done.push('photo in a circle'); }
    if (/\barch\b/.test(t)) { P.photoShape = 'arch'; done.push('arched photo'); }
    if (/\bblob\b/.test(t)) { P.photoShape = 'blob'; done.push('blob-shaped photo'); }
    if (/\b(black and white|b&w|mono|greyscale|grayscale)\b/.test(t)) { P.photoFx = 'mono'; done.push('black & white photo'); }
    if (/\bduotone\b/.test(t)) { P.photoFx = 'duotone'; done.push('duotone photo'); }
    if (/\bhalftone\b/.test(t)) { P.photoFx = 'halftone'; done.push('halftone photo'); }
    if (/\b(natural|normal|original) (photo|colou?rs?)\b/.test(t)) { P.photoFx = 'none'; done.push('natural photo colours'); }
    // Photo transparency: "make the photo more transparent", "photo 40% transparent", "solid photo"
    if (/\b(photo|picture|image)\b/.test(t) && (/\b(transparen\w*|opacity|opaque|fade[ds]?|fainter|see.?through|solid|subtle)\b/.test(t) || /\b(remove|cut ?out|no) (the )?(photo'?s? )?background\b/.test(t))) {
      // Fades the photo's own background (the person stays solid). 0 = untouched, 1 = removed.
      const cur = +P.photoBgFade || 0;
      const pct = t.match(/(\d{1,3})\s*%/);
      if (pct) P.photoBgFade = /\b(opacity|opaque)\b/.test(t) && !/transparen/.test(t) ? 1 - +pct[1] / 100 : +pct[1] / 100;
      else if (/\b(fully )?(solid|opaque)\b/.test(t) && !/\bmore\b/.test(t)) P.photoBgFade = 0;
      else if (/\b(less transparent|more solid|more opaque|stronger)\b/.test(t)) P.photoBgFade = cur - 0.25;
      else if (/\b(remove|cut ?out|no) (the )?(photo'?s? )?background\b/.test(t)) P.photoBgFade = 1;
      else P.photoBgFade = cur + 0.25;
      P.photoBgFade = Math.min(1, Math.max(0, +P.photoBgFade.toFixed(2)));
      delete P.photoOpacity;
      done.push(`photo background transparency → ${Math.round(P.photoBgFade * 100)}%`);
    }
    if (/\b(remove|delete) (the )?(photo|picture|image)\b(?!'?s?\s*background)/.test(t)) { P.photoImg = null; P.photo = null; done.push('photo removed'); }
    const props = findProps(t).filter((p) => !['phone', 'calendar', 'clock', 'pin'].includes(p) || /\b(add|remove)\b/.test(t));
    if (props.length && /\b(add|with|include|throw in|put)\b/.test(t)) { P.props = Array.from(new Set([...(P.props || []), ...props])).slice(-6); done.push(`added ${props.join(', ')}`); }
    if (props.length && /\b(remove|without|no)\b/.test(t)) { P.props = (P.props || []).filter((p) => !props.includes(p)); done.push(`removed ${props.join(', ')}`); }
    if (/\b(motion|animate|animation|moving)\b/.test(t)) { P.motion = !/\b(off|no|stop|still|static)\b/.test(t); done.push(P.motion ? 'motion on' : 'motion off'); }
    if (!done.length) return { unknown: true, reply: `Not sure what to change there. ${POSTER_HELP}` };
    return { changed: true, reply: done.map((d, i) => (i ? d : d.charAt(0).toUpperCase() + d.slice(1))).join(', ') + '. ✅' };
  };

  /** Apply whitelisted set-operations returned by the server AI bridge. */
  const SAFE_PATH = /^(style|aspect|captions|sfx|template|palette|size|headline|kicker|sub|date|time|venue|cta|badge|url|qr|showCharacter|mood|details(\.\d)?|props(\.\d)?|brand\.(name|url|a|b|c)|colors\.(bg|ink|a|b|c)|voice\.(preset|enabled|tune\.(pitch|rate|energy))|music\.(genre|volume)|character\.(skin|hair|hairStyle|top|bottom|acc|hidden)|scenes\.\d{1,2}\.(headline|sub|narration|shot|stage|mood|pose|duration|bg|cta|url|quote|author|voice|sfx|props|bullets|stat\.(value|prefix|suffix|label)))$/;
  const applyOps = (target, ops) => {
    let n = 0;
    (Array.isArray(ops) ? ops : []).slice(0, 40).forEach((op) => {
      if (!op || op.op !== 'set' || typeof op.path !== 'string' || !SAFE_PATH.test(op.path)) return;
      const v = op.value; if (v != null && typeof v === 'object' && !Array.isArray(v)) return;
      if (typeof v === 'string' && v.length > 600) return;
      const keys = op.path.split('.'); let o = target;
      for (let i = 0; i < keys.length - 1; i++) { const k = /^\d+$/.test(keys[i]) ? +keys[i] : keys[i]; if (o[k] == null) { if (Array.isArray(o)) return; o[k] = {}; } o = o[k]; }
      o[keys[keys.length - 1]] = Array.isArray(v) ? v.map((x) => String(x).slice(0, 120)).slice(0, 6) : v; n++;
    });
    return n;
  };

  /** Split compound instructions ("neon, title is "X", venue: Y and use the robot voice") into clauses, keeping quotes intact. */
  const VERB = '(?:use|make|add|change|switch|set|give|remove|delete|turn|put|swap|replace|title|headline|venue|location|date|time|tagline|subtitle|kicker|badge|link|url|music|voice|captions|background|colou?r|vertical|square|then)';
  const SPLIT = new RegExp('^(?:\\s*(?:,(?!\\d)|;)\\s*|\\s+and\\s+(?=(?:also\\s+)?(?:the\\s+)?' + VERB + '\\b)|\\s+then\\s+)', 'i');
  const clauses = (raw) => {
    const parts = []; let buf = '', q = null;
    const str = String(raw);
    for (let i = 0; i < str.length; i++) {
      const ch = str[i];
      if (q) { buf += ch; if ((q === '"' && /["”]/.test(ch)) || (q === "'" && /['’]/.test(ch) && !/\w/.test(str[i + 1] || ''))) q = null; continue; }
      if (/["“]/.test(ch)) { q = '"'; buf += ch; continue; }
      if (/['‘]/.test(ch) && !/\w/.test(str[i - 1] || '')) { q = "'"; buf += ch; continue; }
      const m = str.slice(i).match(SPLIT);
      if (m) { if (buf.trim()) parts.push(buf.trim()); buf = ''; i += m[0].length - 1; continue; }
      buf += ch;
    }
    if (buf.trim()) parts.push(buf.trim());
    return parts.length ? parts : [str];
  };
  const multi = (fn, isEx) => (raw, target, cur) => {
    const cs = clauses(raw);
    if (cs.length < 2) return fn(raw, target, cur);
    const out = { reply: '', changed: false }, replies = [];
    let unknown = 0, lastScene = '';
    for (const c of cs) {
      const sc = c.match(/\b(scenes?\s+\d+(?:\s*(?:,|and|&)\s*\d+)*|(?:first|second|third|fourth|fifth|last|final)\s+scene)\b/i);
      if (sc) lastScene = sc[0];
      const clause = !sc && lastScene && isEx ? c + ' in ' + lastScene : c;
      const r = fn(clause, target, cur);
      if (r.undo || r.redo) return r;
      if (r.changed) { out.changed = true; replies.push(r.reply.replace(/\s*✅$/, '')); if (r.select != null) out.select = r.select; }
      else if (r.unknown) unknown++;
      else if (r.reply) replies.push(r.reply);
    }
    if (!out.changed && unknown === cs.length) return fn(raw, target, cur);
    out.reply = replies.join(' · ') + (out.changed ? ' ✅' : '');
    return out;
  };

  WE.Director = { explainer: multi(explainer, true), poster: multi(poster, false), applyOps, clauses, COLOURS, HELP, POSTER_HELP };
})(typeof window !== 'undefined' ? window : globalThis);
