/* ==========================================================================
 * ATG World Engine — 02 Cast
 * Rigged cartoon characters (moods, poses, blink, lip-flap, accessories),
 * a library of props, and the whiteboard artist's drawing hand.
 * All geometry is emitted as WE shapes so every style (whiteboard, cartoon,
 * neon, paper…) can render the same cast its own way.
 * ========================================================================== */
(function (root) {
  'use strict';
  const WE = root.WorldEngine;
  const { circlePts, rrectPts, parsePath, thickPts } = WE.Geo;
  const { clamp } = WE.M;

  const SKINS = { porcelain: '#fde3d0', peach: '#f6c9a8', honey: '#e0a878', caramel: '#c68650', umber: '#9a5a32', espresso: '#6b3b20', ebony: '#4a2814' };
  const HAIR = { black: '#1c1714', brown: '#5a3622', auburn: '#9a3b1f', blonde: '#e9c46a', ginger: '#d9622b', grey: '#b8b8b8', pink: '#ff6fb5', blue: '#4f7cff', purple: '#8e5cf7' };
  const HAIR_STYLES = ['short', 'long', 'curly', 'afro', 'bun', 'ponytail', 'braids', 'bald', 'mohawk'];
  const MOODS = ['neutral', 'happy', 'excited', 'stressed', 'shocked', 'sad', 'angry', 'thinking', 'proud', 'wink'];
  const POSES = ['idle', 'wave', 'point', 'pointLeft', 'stressed', 'celebrate', 'thumbsUp', 'shrug', 'think', 'hold', 'present'];
  const ACCESSORIES = ['none', 'glasses', 'cap', 'headphones', 'bow', 'crown'];

  const shape = (pts, fill, extra) => Object.assign({ pts, closed: true, fill, stroke: 'ink' }, extra || {});
  const line = (d, w, fill, extra) => Object.assign({ pts: thickPts(parsePath(d)[0].pts, w), closed: true, fill, stroke: 'ink' }, extra || {});
  const stroke = (d, extra) => Object.assign({ pts: parsePath(d)[0].pts, closed: false, fill: null, stroke: 'ink', lw: 1 }, extra || {});

  // arm angles: [upper, lower] measured from "straight down", positive = outward
  const POSE_ARMS = {
    idle: [[0.18, 0.06], [0.18, 0.06]],
    wave: [[0.18, 0.06], [2.35, 2.85]],
    point: [[0.18, 0.06], [1.5, 1.58]],
    pointLeft: [[1.5, 1.58], [0.18, 0.06]],
    stressed: [[2.44, -2.68], [2.44, -2.68]],
    celebrate: [[2.65, 2.95], [2.65, 2.95]],
    thumbsUp: [[0.18, 0.06], [0.55, 2.7]],
    shrug: [[0.95, 2.1], [0.95, 2.1]],
    think: [[0.18, 0.06], [0.1, -2.78]],
    hold: [[0.22, -1.99], [0.22, -1.99]],
    present: [[1.15, 1.75], [1.15, 1.75]],
  };

  /**
   * Build a character as shapes. Origin = between the feet; height ≈ 300 units.
   * o: { skin, hair, hairStyle, top, bottom, shoes, acc, mood, pose, t, talking, seed }
   */
  const character = (o) => {
    const t = o.t || 0, mood = o.mood || 'neutral', pose = o.pose || 'idle';
    const skin = SKINS[o.skin] || o.skin || SKINS.honey, hair = HAIR[o.hair] || o.hair || HAIR.black;
    const top = o.top || '#4f7cff', bottom = o.bottom || '#27315a', shoes = o.shoes || '#222831';
    const seed = o.seed || 1;
    const out = [];
    let bob = Math.sin(t * 2.2 + seed) * 2.5, sx = 0;
    if (mood === 'excited' || pose === 'celebrate') bob = -Math.abs(Math.sin(t * 7)) * 16;
    if (mood === 'stressed') sx = Math.sin(t * 38) * 1.6;
    if (mood === 'shocked') bob = -Math.max(0, Math.sin(Math.min(t, 0.5) * Math.PI * 2)) * 18;
    const B = (x, y) => [x + sx, y + bob];
    const P = (arr) => { const r = []; for (let i = 0; i < arr.length; i += 2) { const p = B(arr[i], arr[i + 1]); r.push(p[0], p[1]); } return r; };
    const pth = (d) => P(parsePath(d)[0].pts);

    // legs + shoes (legs don't bob fully — feet stay planted)
    const hipY = -96 + bob * 0.7;
    out.push({ pts: thickPts([-15 + sx, hipY, -19, -10], 27), closed: true, fill: bottom, stroke: 'ink' });
    out.push({ pts: thickPts([15 + sx, hipY, 19, -10], 27), closed: true, fill: bottom, stroke: 'ink' });
    out.push(shape(circlePts(-24, -6, 18, 9, 24), shoes));
    out.push(shape(circlePts(24, -6, 18, 9, 24), shoes));

    // hair behind head
    const hx = 0, hy = -244;
    if (o.hairStyle === 'long' || o.hairStyle === 'braids') out.push(shape(P(rrectPts(-58, -292, 116, 118, 46)), hair));
    if (o.hairStyle === 'afro') out.push(shape(P(circlePts(0, -258, 76, 70, 48)), hair));
    if (o.hairStyle === 'ponytail') out.push(shape(P(circlePts(56, -232, 18, 38, 24)), hair));
    if (o.hairStyle === 'braids') { out.push(line('M-50 -200 L-54 -140', 16, hair)); out.push(line('M50 -200 L54 -140', 16, hair)); }

    // arms (computed before torso so the "back" arm sits behind)
    const arms = POSE_ARMS[pose] || POSE_ARMS.idle;
    const armShapes = [];
    [-1, 1].forEach((s, idx) => {
      let [a1, a2] = arms[idx];
      if (pose === 'wave' && s === 1) a2 += Math.sin(t * 11) * 0.38;
      if (pose === 'celebrate') { a1 += Math.sin(t * 7 + idx) * 0.12; a2 += Math.sin(t * 7 + idx) * 0.18; }
      if (pose === 'idle') { a1 += Math.sin(t * 2 + idx * 2) * 0.04; }
      if (pose === 'present') { a2 += Math.sin(t * 3) * 0.08; }
      const shx = 36 * s, shy = -168;
      const ex = shx + s * Math.sin(a1) * 44, ey = shy + Math.cos(a1) * 44;
      const hxp = ex + s * Math.sin(a2) * 42, hyp = ey + Math.cos(a2) * 42;
      armShapes.push({ pts: thickPts(P([shx, shy, ex, ey]), 23), closed: true, fill: top, stroke: 'ink' });
      armShapes.push({ pts: thickPts(P([ex, ey, hxp, hyp]), 17), closed: true, fill: skin, stroke: 'ink' });
      armShapes.push(shape(P(circlePts(hxp, hyp, 11, 11, 20)), skin));
      if (pose === 'thumbsUp' && s === 1) { // fist + angled thumb on the inner edge, reads clearly as a thumbs-up
        armShapes.pop();
        armShapes.push(shape(P(rrectPts(hxp - 12, hyp - 10, 24, 20, 8)), skin));
        const th = []; const ang = -0.45 * s, cx0 = hxp - s * 9, cy0 = hyp - 8;
        [[-4.5, 0], [4.5, 0], [4.5, -15], [0, -19], [-4.5, -15]].forEach(([px, py]) => { th.push(cx0 + px * Math.cos(ang) - py * Math.sin(ang), cy0 + px * Math.sin(ang) + py * Math.cos(ang)); });
        armShapes.push(shape(P(th), skin));
        armShapes.push(stroke(`M${hxp + 2 + sx} ${hyp - 4 + bob} L${hxp + 10 + sx} ${hyp - 4 + bob} M${hxp + 2 + sx} ${hyp + 2 + bob} L${hxp + 10 + sx} ${hyp + 2 + bob}`, { lw: 0.7 }));
      }
      if ((pose === 'point' && s === 1) || (pose === 'pointLeft' && s === -1)) armShapes.push(shape(P(rrectPts(s === 1 ? hxp : hxp - 22, hyp - 4, 22, 8, 4)), skin));
    });
    // torso
    out.push(shape(pth('M-40 -176 Q-50 -110 -38 -88 L38 -88 Q50 -110 40 -176 Q0 -194 -40 -176 Z'), top));
    out.push(shape(pth('M-12 -178 L0 -160 L12 -178 Z'), skin, { nostroke: true }));
    out.push(...armShapes);
    // neck + head + ears
    out.push(shape(P(rrectPts(-10, -198, 20, 24, 6)), skin));
    out.push(shape(P(circlePts(-50, -240, 10, 12, 16)), skin));
    out.push(shape(P(circlePts(50, -240, 10, 12, 16)), skin));
    out.push(shape(P(circlePts(hx, hy, 52, 54, 44)), skin));

    // hair on top
    const hs = o.hairStyle || 'short';
    if (hs === 'short') out.push(shape(pth('M-54 -246 Q-58 -304 0 -302 Q58 -304 54 -246 Q46 -276 14 -276 Q-6 -262 -20 -276 Q-44 -276 -54 -246 Z'), hair));
    if (hs === 'long' || hs === 'braids' || hs === 'ponytail') out.push(shape(pth('M-56 -236 Q-62 -306 0 -304 Q62 -306 56 -236 Q50 -282 0 -280 Q-50 -282 -56 -236 Z'), hair));
    if (hs === 'bun') { out.push(shape(pth('M-54 -246 Q-58 -304 0 -302 Q58 -304 54 -246 Q40 -280 0 -280 Q-40 -280 -54 -246 Z'), hair)); out.push(shape(P(circlePts(0, -312, 22, 20, 24)), hair)); }
    if (hs === 'curly') { for (let i = 0; i < 9; i++) { const a = Math.PI + (i / 8) * Math.PI; out.push(shape(P(circlePts(Math.cos(a) * 46, -250 + Math.sin(a) * 46, 16, 16, 16)), hair)); } }
    if (hs === 'afro') { for (let i = 0; i < 7; i++) { const a = Math.PI * 1.08 + (i / 6) * Math.PI * 0.84; out.push(shape(P(circlePts(Math.cos(a) * 52, -256 + Math.sin(a) * 50, 22, 22, 18)), hair)); } }
    if (hs === 'mohawk') out.push(shape(pth('M-12 -290 L-6 -330 L0 -296 L6 -334 L12 -296 L18 -326 L18 -288 Q0 -300 -12 -290 Z'), hair));
    if (hs === 'bald') out.push(shape(P(circlePts(-20, -278, 10, 5, 12)), '#ffffff', { nostroke: true, stroke: false }));

    // face
    const blinkPhase = (t + seed * 0.37) % 3.6;
    const blink = !o.still && blinkPhase > 3.45 && mood !== 'happy';
    const ink = '#1b1b1f';
    const eyeY = -246;
    const eye = (x, kind) => {
      if (blink && kind !== 'closed') { out.push(stroke(`M${x - 7 + sx} ${eyeY + bob} L${x + 7 + sx} ${eyeY + bob}`, { lw: 1.2 })); return; }
      if (kind === 'arc') out.push(stroke(`M${x - 9 + sx} ${eyeY + 4 + bob} Q${x + sx} ${eyeY - 8 + bob} ${x + 9 + sx} ${eyeY + 4 + bob}`, { lw: 1.4 }));
      else if (kind === 'big') { out.push(shape(P(circlePts(x, eyeY, 13, 14, 20)), '#ffffff')); out.push(shape(P(circlePts(x, eyeY + 1, 5.5, 6, 14)), ink, { nostroke: true })); }
      else if (kind === 'up') { out.push(shape(P(circlePts(x, eyeY, 9, 11, 18)), '#ffffff')); out.push(shape(P(circlePts(x + 2, eyeY - 5, 4.5, 5, 12)), ink, { nostroke: true })); }
      else if (kind === 'lid') { out.push(shape(P(circlePts(x, eyeY + 2, 6, 6, 14)), ink, { nostroke: true })); out.push(stroke(`M${x - 9 + sx} ${eyeY - 2 + bob} L${x + 9 + sx} ${eyeY - 1 + bob}`, { lw: 1.3 })); }
      else if (kind === 'star') { const pts = []; for (let i = 0; i < 10; i++) { const a = -Math.PI / 2 + (i * Math.PI) / 5, r = i % 2 ? 4 : 10; pts.push(x + Math.cos(a) * r, eyeY + Math.sin(a) * r); } out.push(shape(P(pts), '#ffc83d')); }
      else out.push(shape(P(circlePts(x, eyeY, 6.5, 8.5, 16)), ink, { nostroke: true }));
    };
    const E = { happy: ['arc', 'arc'], excited: ['star', 'star'], shocked: ['big', 'big'], thinking: ['up', 'up'], proud: ['lid', 'lid'], wink: ['dot', 'arc'], stressed: ['dot', 'dot'], sad: ['dot', 'dot'], angry: ['dot', 'dot'], neutral: ['dot', 'dot'] }[mood] || ['dot', 'dot'];
    eye(-19, E[0]); eye(19, E[1]);
    // brows
    const brow = { stressed: [[-30, -268, -10, -274], [10, -274, 30, -268]], sad: [[-30, -266, -10, -274], [10, -274, 30, -266]], angry: [[-30, -276, -9, -266], [9, -266, 30, -276]], shocked: [[-30, -280, -10, -284], [10, -284, 30, -280]], thinking: [[-30, -270, -10, -272], [10, -278, 30, -274]] }[mood];
    if (brow) brow.forEach((b) => out.push(stroke(`M${b[0] + sx} ${b[1] + bob} L${b[2] + sx} ${b[3] + bob}`, { lw: 1.6 })));
    // blush
    if (['happy', 'proud', 'excited', 'wink'].includes(mood)) { out.push(shape(P(circlePts(-33, -224, 9, 5, 14)), 'rgba(255,110,130,0.45)', { nostroke: true, stroke: false })); out.push(shape(P(circlePts(33, -224, 9, 5, 14)), 'rgba(255,110,130,0.45)', { nostroke: true, stroke: false })); }
    // mouth
    const my = -214;
    const talk = o.talking ? clamp(0.25 + 0.75 * Math.abs(Math.sin(t * 13) * Math.sin(t * 7.3 + 1))) : 0;
    const mouthCol = '#8a2433';
    if (talk > 0.05 && mood !== 'shocked') {
      out.push(shape(P(circlePts(0, my + 2, 11 + talk * 3, 3 + talk * 10, 20)), mouthCol));
      out.push(shape(P(circlePts(0, my + 4 + talk * 6, 6, 2 + talk * 3, 12)), '#ff7b8a', { nostroke: true }));
    } else if (mood === 'happy' || mood === 'excited' || mood === 'wink') {
      out.push(shape(pth(`M-18 ${my - 4} Q0 ${my + 22} 18 ${my - 4} Z`), mouthCol));
      out.push(shape(pth(`M-12 ${my - 2} L12 ${my - 2} L10 ${my + 3} L-10 ${my + 3} Z`), '#ffffff', { nostroke: true }));
    } else if (mood === 'proud') out.push(stroke(`M-14 ${my + bob * 0} Q4 ${my + 10} 18 ${my - 6}`, { lw: 1.4 }));
    else if (mood === 'shocked') out.push(shape(P(circlePts(0, my + 4, 10, 14, 20)), mouthCol));
    else if (mood === 'sad') out.push(stroke(`M-14 ${my + 8} Q0 ${my - 6} 14 ${my + 8}`, { lw: 1.4 }));
    else if (mood === 'stressed') out.push(stroke(`M-16 ${my + 2} Q-10 ${my - 4} -5 ${my + 2} Q0 ${my + 7} 5 ${my + 2} Q10 ${my - 4} 16 ${my + 2}`, { lw: 1.3 }));
    else if (mood === 'angry') { out.push(shape(P(rrectPts(-15, my - 4, 30, 10, 3)), '#ffffff')); out.push(stroke(`M-15 ${my + 1} L15 ${my + 1}`, { lw: 0.8 })); }
    else if (mood === 'thinking') out.push(stroke(`M-6 ${my + 2} L12 ${my - 2}`, { lw: 1.4 }));
    else out.push(stroke(`M-12 ${my} Q0 ${my + 8} 12 ${my}`, { lw: 1.4 }));

    // mood extras
    if (mood === 'stressed') { const dy = ((t * 50) % 40); out.push(shape(pth(`M58 ${-282 + dy} Q66 ${-268 + dy} 58 ${-262 + dy} Q50 ${-268 + dy} 58 ${-282 + dy} Z`), '#7cc8ff')); }
    if (mood === 'sad') { const dy = ((t * 40) % 50); out.push(shape(pth(`M-22 ${-236 + dy} Q-16 ${-226 + dy} -22 ${-220 + dy} Q-28 ${-226 + dy} -22 ${-236 + dy} Z`), '#7cc8ff')); }
    if (mood === 'angry') out.push(stroke(`M34 -292 L44 -282 M44 -292 L34 -282`, { lw: 1.5, stroke: '#e63946' }));

    // accessories
    const acc = o.acc || 'none';
    if (acc === 'glasses') { out.push({ pts: P(circlePts(-19, -246, 15, 13, 24)), closed: true, fill: 'rgba(255,255,255,0.18)', stroke: 'ink' }); out.push({ pts: P(circlePts(19, -246, 15, 13, 24)), closed: true, fill: 'rgba(255,255,255,0.18)', stroke: 'ink' }); out.push(stroke(`M-4 ${-247 + bob} L4 ${-247 + bob}`)); }
    if (acc === 'cap') { out.push(shape(pth('M-54 -258 Q-52 -312 0 -310 Q52 -312 54 -258 Z'), o.accColor || '#e63946')); out.push(shape(pth('M-6 -262 L84 -262 Q86 -252 70 -250 L-6 -252 Z'), o.accColor || '#e63946')); }
    if (acc === 'headphones') { out.push(stroke('M-56 -240 Q-60 -318 0 -318 Q60 -318 56 -240', { lw: 2.6 })); out.push(shape(P(rrectPts(-66, -262, 18, 36, 8)), o.accColor || '#ff3d7f')); out.push(shape(P(rrectPts(48, -262, 18, 36, 8)), o.accColor || '#ff3d7f')); }
    if (acc === 'bow') { out.push(shape(pth('M20 -300 L46 -318 L46 -282 Z'), o.accColor || '#ff3d7f')); out.push(shape(pth('M20 -300 L-6 -318 L-6 -282 Z'), o.accColor || '#ff3d7f')); out.push(shape(P(circlePts(20, -300, 7, 7, 12)), o.accColor || '#ff3d7f')); }
    if (acc === 'crown') out.push(shape(pth('M-34 -292 L-38 -330 L-18 -312 L0 -340 L18 -312 L38 -330 L34 -292 Z'), '#ffc83d'));

    return { shapes: out, head: B(0, -244), hand: null, bob };
  };

  /** Render a character at a position. x,y = feet in canvas px; h = on-screen height in px. */
  const drawCharacter = (ctx, x, y, h, o, render) => {
    const c = character(o);
    const sc = h / 330;
    ctx.save(); ctx.translate(x, y); ctx.scale(sc * (o.flip ? -1 : 1), sc);
    const tip = WE.renderShapes(ctx, c.shapes, Object.assign({ scale: sc }, render));
    ctx.restore();
    return { tip, head: [x + c.head[0] * sc * (o.flip ? -1 : 1), y + c.head[1] * sc], sc };
  };

  /* ---------------------------------------------------------------- props */
  const star = (cx, cy, r1, r2, n = 5) => { let d = ''; for (let i = 0; i < n * 2; i++) { const a = -Math.PI / 2 + (i * Math.PI) / n, r = i % 2 ? r2 : r1; d += (i ? 'L' : 'M') + (cx + Math.cos(a) * r).toFixed(1) + ' ' + (cy + Math.sin(a) * r).toFixed(1) + ' '; } return d + 'Z'; };
  const PROP_DEFS = {
    phone: [['rr', 28, 6, 44, 88, 10, 'd'], ['rr', 32, 14, 36, 68, 4, 'l'], ['rr', 36, 20, 28, 9, 3, 'a'], ['rr', 36, 33, 22, 7, 3, 'b'], ['rr', 36, 44, 26, 7, 3, 'c'], ['rr', 36, 55, 18, 7, 3, 'b'], ['c', 50, 88, 3, 'l']],
    laptop: [['rr', 18, 20, 64, 44, 5, 'd'], ['rr', 22, 24, 56, 36, 2, 'l'], ['p', 'M8 66 H92 L84 78 H16 Z', 'b'], ['rr', 26, 28, 24, 7, 2, 'a'], ['rr', 26, 39, 42, 5, 2, 'c'], ['rr', 26, 48, 32, 5, 2, 'c']],
    chat: [['p', 'M12 14 H66 Q74 14 74 22 V46 Q74 54 66 54 H36 L22 66 V54 H12 Q4 54 4 46 V22 Q4 14 12 14 Z', 'a'], ['c', 24, 34, 4.5, 'l'], ['c', 39, 34, 4.5, 'l'], ['c', 54, 34, 4.5, 'l'], ['p', 'M52 58 H88 Q96 58 96 66 V80 Q96 88 88 88 H86 V96 L76 88 H52 Q44 88 44 80 V66 Q44 58 52 58 Z', 'b']],
    money: [['rr', 22, 66, 56, 16, 8, 'g'], ['rr', 22, 54, 56, 16, 8, 'g'], ['rr', 22, 42, 56, 16, 8, 'g'], ['e', 50, 46, 28, 8, 'y'], ['p', 'M54 10 H94 V36 H54 Z', 'gr'], ['c', 74, 23, 7, 'l'], ['t', '£', 50, 47, 13, 'd']],
    cake: [['rr', 12, 84, 76, 7, 3, 'd'], ['rr', 20, 50, 60, 36, 7, 'a'], ['p', 'M20 50 H80 V60 Q74 70 68 60 Q62 70 56 60 Q50 70 44 60 Q38 70 32 60 Q26 70 20 60 Z', 'l'], ['rr', 46, 30, 8, 20, 3, 'b'], ['p', 'M50 12 Q60 24 50 30 Q40 24 50 12 Z', 'g']],
    balloon: [['e', 50, 36, 23, 28, 'a'], ['p', 'M45 63 H55 L50 70 Z', 'a'], ['pl', 'M50 70 Q42 80 50 88 Q58 95 50 100'], ['e', 42, 26, 5, 8, 'l']],
    balloons: [['e', 34, 34, 18, 22, 'a'], ['e', 64, 28, 18, 22, 'b'], ['e', 52, 46, 16, 20, 'c'], ['pl', 'M34 56 Q40 76 50 96'], ['pl', 'M64 50 Q56 74 50 96'], ['pl', 'M52 66 L50 96']],
    calendar: [['rr', 14, 20, 72, 66, 8, 'l'], ['rr', 14, 20, 72, 20, 8, 'a'], ['rr', 28, 12, 7, 16, 3, 'd'], ['rr', 65, 12, 7, 16, 3, 'd'], ['rr', 24, 48, 12, 10, 2, 'c'], ['rr', 44, 48, 12, 10, 2, 'c'], ['rr', 64, 48, 12, 10, 2, 'b'], ['rr', 24, 66, 12, 10, 2, 'c'], ['rr', 44, 66, 12, 10, 2, 'c']],
    clock: [['c', 50, 52, 38, 'l'], ['c', 50, 52, 30, 'w'], ['l', 50, 52, 50, 30, 'ink', 1.6], ['l', 50, 52, 66, 60, 'ink', 1.6], ['c', 50, 52, 4, 'd'], ['rr', 44, 6, 12, 8, 3, 'a']],
    chart: [['rr', 10, 12, 80, 76, 8, 'l'], ['rr', 22, 58, 12, 22, 3, 'c'], ['rr', 40, 46, 12, 34, 3, 'b'], ['rr', 58, 30, 12, 50, 3, 'a'], ['pl', 'M20 48 L38 36 L56 40 L78 18', 'gr', 1.4], ['p', 'M80 16 L70 16 L78 26 Z', 'gr']],
    rocket: [['p', 'M50 4 Q74 22 68 66 H32 Q26 22 50 4 Z', 'l'], ['c', 50, 34, 9, 'a'], ['c', 50, 34, 4, 'w'], ['p', 'M32 48 L16 72 L32 66 Z', 'a'], ['p', 'M68 48 L84 72 L68 66 Z', 'a'], ['p', 'M38 66 Q50 104 62 66 Z', 'g'], ['p', 'M44 66 Q50 86 56 66 Z', 'r']],
    house: [['rr', 64, 18, 10, 22, 0, 'd'], ['p', 'M10 50 L50 14 L90 50 Z', 'a'], ['rr', 20, 48, 60, 42, 0, 'l'], ['rr', 42, 64, 16, 26, 2, 'b'], ['rr', 25, 56, 12, 12, 2, 'c'], ['rr', 63, 56, 12, 12, 2, 'c']],
    key: [['c', 30, 50, 17, 'g'], ['c', 30, 50, 6, 'w'], ['rr', 44, 46, 46, 9, 4, 'g'], ['rr', 76, 54, 7, 12, 2, 'g'], ['rr', 64, 54, 6, 9, 2, 'g']],
    shield: [['p', 'M50 6 L86 18 V48 Q86 78 50 94 Q14 78 14 48 V18 Z', 'a'], ['p', 'M50 16 L76 25 V48 Q76 70 50 83 Z', 'b'], ['pl', 'M33 50 L46 63 L69 37', 'w', 2.6]],
    doc: [['p', 'M22 6 H62 L80 24 V94 H22 Z', 'l'], ['p', 'M62 6 V24 H80 Z', 'c'], ['rr', 30, 36, 40, 5, 2, 'c'], ['rr', 30, 48, 40, 5, 2, 'c'], ['rr', 30, 60, 28, 5, 2, 'c'], ['c', 64, 78, 9, 'r']],
    magnifier: [['p', 'M56 62 L84 90 L76 98 L48 70 Z', 'd'], ['c', 40, 42, 28, 'a'], ['c', 40, 42, 20, 'w'], ['e', 33, 35, 6, 4, 'l']],
    bulb: [['c', 50, 40, 27, 'g'], ['rr', 38, 64, 24, 18, 4, 'c'], ['rr', 42, 82, 16, 6, 3, 'd'], ['pl', 'M42 52 L46 38 L50 48 L54 38 L58 52', 'd'], ['l', 50, 4, 50, -6, 'g', 1.4], ['l', 14, 22, 6, 16, 'g', 1.4], ['l', 86, 22, 94, 16, 'g', 1.4]],
    heart: [['p', 'M50 86 C20 66 6 46 12 30 C18 14 40 12 50 28 C60 12 82 14 88 30 C94 46 80 66 50 86 Z', 'r'], ['e', 30, 32, 6, 4, 'l']],
    star: [['p', star(50, 52, 44, 19), 'g']],
    sparkle: [['p', star(50, 50, 44, 9, 4), 'g']],
    tree: [['rr', 42, 56, 16, 38, 4, 'brown'], ['c', 50, 38, 30, 'gr'], ['c', 26, 50, 18, 'gr'], ['c', 74, 50, 18, 'gr'], ['c', 36, 30, 7, 'a'], ['c', 62, 34, 7, 'b'], ['c', 50, 52, 7, 'c'], ['c', 26, 52, 6, 'r']],
    clipboard: [['rr', 18, 12, 64, 82, 7, 'b'], ['rr', 25, 22, 50, 66, 3, 'l'], ['rr', 36, 6, 28, 12, 4, 'd'], ['pl', 'M31 36 L36 41 L44 31', 'gr', 1.6], ['rr', 50, 34, 20, 5, 2, 'c'], ['pl', 'M31 53 L36 58 L44 48', 'gr', 1.6], ['rr', 50, 51, 20, 5, 2, 'c'], ['rr', 31, 68, 13, 9, 2, 'c'], ['rr', 50, 69, 20, 5, 2, 'c']],
    scales: [['rr', 30, 86, 40, 7, 3, 'd'], ['rr', 47, 18, 6, 70, 2, 'd'], ['rr', 14, 26, 72, 6, 3, 'd'], ['l', 20, 30, 12, 54], ['l', 20, 30, 28, 54], ['l', 80, 30, 72, 54], ['l', 80, 30, 88, 54], ['p', 'M6 54 H34 Q20 68 6 54 Z', 'a'], ['p', 'M66 54 H94 Q80 68 66 54 Z', 'b'], ['c', 50, 16, 6, 'g']],
    dice: [['rr', 16, 16, 68, 68, 14, 'l'], ['c', 33, 33, 6, 'a'], ['c', 67, 33, 6, 'a'], ['c', 50, 50, 6, 'a'], ['c', 33, 67, 6, 'a'], ['c', 67, 67, 6, 'a']],
    bank: [['p', 'M8 36 L50 10 L92 36 Z', 'a'], ['rr', 12, 36, 76, 7, 0, 'd'], ['rr', 20, 46, 9, 32, 0, 'l'], ['rr', 37, 46, 9, 32, 0, 'l'], ['rr', 54, 46, 9, 32, 0, 'l'], ['rr', 71, 46, 9, 32, 0, 'l'], ['rr', 8, 80, 84, 9, 0, 'd'], ['c', 50, 26, 5, 'g']],
    envelope: [['rr', 10, 24, 80, 54, 6, 'l'], ['pl', 'M10 28 L50 56 L90 28'], ['c', 50, 56, 8, 'r']],
    trophy: [['pl', 'M28 22 Q8 22 12 38 Q16 50 32 48', 'g', 2.2], ['pl', 'M72 22 Q92 22 88 38 Q84 50 68 48', 'g', 2.2], ['p', 'M26 12 H74 V34 Q74 58 50 60 Q26 58 26 34 Z', 'g'], ['rr', 44, 58, 12, 14, 0, 'g'], ['rr', 30, 72, 40, 14, 3, 'd'], ['p', star(50, 34, 10, 4), 'l']],
    bell: [['p', 'M50 10 Q26 14 26 46 V60 L16 72 H84 L74 60 V46 Q74 14 50 10 Z', 'g'], ['c', 50, 80, 7, 'g'], ['c', 76, 18, 12, 'r'], ['t', '!', 76, 19, 16, 'l']],
    warning: [['p', 'M50 8 L94 88 H6 Z', 'g'], ['rr', 45, 34, 10, 30, 4, 'd'], ['c', 50, 74, 6, 'd']],
    check: [['c', 50, 50, 42, 'gr'], ['pl', 'M30 52 L44 66 L72 36', 'w', 3]],
    cross: [['c', 50, 50, 42, 'r'], ['pl', 'M34 34 L66 66', 'w', 3], ['pl', 'M66 34 L34 66', 'w', 3]],
    gift: [['rr', 16, 42, 68, 48, 4, 'a'], ['rr', 11, 30, 78, 16, 4, 'b'], ['rr', 44, 30, 12, 60, 0, 'g'], ['p', 'M50 30 Q30 4 22 20 Q20 30 50 30 Z', 'g'], ['p', 'M50 30 Q70 4 78 20 Q80 30 50 30 Z', 'g']],
    brain: [['p', 'M28 76 C8 72 6 46 22 38 C20 18 42 10 52 22 C64 8 90 18 84 40 C98 50 88 76 70 74 C64 88 36 90 28 76 Z', 'pk'], ['pl', 'M50 24 Q44 44 52 56 Q60 68 50 84', 'd', 1.1], ['pl', 'M26 50 Q36 44 44 52', 'd', 1.1], ['pl', 'M62 40 Q72 46 80 40', 'd', 1.1]],
    briefcase: [['p', 'M38 30 V20 Q38 14 44 14 H56 Q62 14 62 20 V30', null], ['rr', 10, 30, 80, 54, 7, 'd'], ['rr', 10, 50, 80, 6, 0, 'c'], ['rr', 44, 46, 12, 12, 2, 'g']],
    receipt: [['p', 'M24 6 H76 V94 L70 88 L63 94 L56 88 L50 94 L44 88 L37 94 L30 88 L24 94 Z', 'l'], ['rr', 32, 18, 36, 5, 2, 'c'], ['rr', 32, 32, 24, 4, 2, 'c'], ['rr', 60, 32, 8, 4, 2, 'c'], ['rr', 32, 44, 24, 4, 2, 'c'], ['rr', 60, 44, 8, 4, 2, 'c'], ['rr', 32, 62, 36, 7, 2, 'a']],
    lock: [['pl', 'M33 46 V32 Q33 12 50 12 Q67 12 67 32 V46', 'd', 3], ['rr', 22, 44, 56, 44, 9, 'g'], ['c', 50, 62, 6, 'd'], ['rr', 47, 62, 6, 14, 3, 'd']],
    people: [['c', 26, 40, 11, 'a'], ['rr', 12, 54, 28, 34, 12, 'a'], ['c', 74, 40, 11, 'b'], ['rr', 60, 54, 28, 34, 12, 'b'], ['c', 50, 30, 13, 'c'], ['rr', 34, 46, 32, 44, 14, 'c']],
    megaphone: [['p', 'M22 38 L70 14 V86 L22 62 Z', 'a'], ['rr', 10, 38, 14, 24, 4, 'd'], ['rr', 28, 62, 10, 22, 3, 'd'], ['arc', 70, 50, 16, -0.9, 0.9, 'ink', 1.2], ['arc', 70, 50, 26, -0.8, 0.8, 'ink', 1.2]],
    target: [['c', 50, 50, 42, 'r'], ['c', 50, 50, 30, 'w'], ['c', 50, 50, 18, 'r'], ['c', 50, 50, 7, 'w'], ['l', 50, 50, 92, 8, 'd', 1.6], ['p', 'M92 8 L80 10 L90 20 Z', 'a']],
    medical: [['rr', 36, 12, 28, 76, 6, 'r'], ['rr', 12, 36, 76, 28, 6, 'r']],
    crown: [['p', 'M12 72 L18 28 L36 50 L50 18 L64 50 L82 28 L88 72 Z', 'g'], ['rr', 12, 72, 76, 12, 3, 'g'], ['c', 50, 60, 6, 'r'], ['c', 30, 62, 4, 'a'], ['c', 70, 62, 4, 'b']],
    card: [['rr', 8, 22, 84, 56, 9, 'a'], ['rr', 8, 34, 84, 11, 0, 'd'], ['rr', 18, 56, 18, 12, 3, 'g'], ['rr', 44, 60, 36, 5, 2, 'l']],
    pin: [['p', 'M50 94 C38 74 20 60 20 40 C20 22 34 8 50 8 C66 8 80 22 80 40 C80 60 62 74 50 94 Z', 'r'], ['c', 50, 40, 12, 'w']],
    music: [['p', 'M34 74 V22 L80 12 V64', null], ['e', 26, 76, 12, 9, 'a'], ['e', 72, 66, 12, 9, 'b'], ['rr', 34, 16, 46, 10, 2, 'd']],
    pound: [['c', 50, 50, 42, 'g'], ['c', 50, 50, 34, 'y'], ['t', '£', 50, 54, 52, 'd']],
    percent: [['c', 50, 50, 42, 'a'], ['t', '%', 50, 54, 50, 'w']],
    thumbs: [['rr', 14, 46, 18, 42, 4, 'b'], ['p', 'M34 46 L52 14 Q62 12 62 26 L58 40 H82 Q92 42 88 54 L80 82 Q78 88 70 88 H34 Z', 'sk']],
    plane: [['p', 'M6 52 L94 14 L60 90 L48 60 Z', 'l'], ['p', 'M48 60 L94 14 L40 56 Z', 'c']],
    sofa: [['rr', 10, 44, 80, 34, 10, 'a'], ['rr', 18, 28, 64, 26, 9, 'b'], ['rr', 6, 46, 16, 32, 7, 'a'], ['rr', 78, 46, 16, 32, 7, 'a'], ['rr', 14, 78, 8, 10, 2, 'd'], ['rr', 78, 78, 8, 10, 2, 'd']],
    stopwatch: [['c', 50, 56, 36, 'l'], ['rr', 44, 8, 12, 12, 3, 'd'], ['p', 'M50 56 L50 26 Q72 30 78 52 Z', 'a'], ['c', 50, 56, 4, 'd']],
    pills: [['rr', 14, 38, 50, 22, 11, 'r'], ['rr', 14, 38, 25, 22, 11, 'l'], ['c', 70, 62, 16, 'b'], ['l', 60, 56, 80, 68, 'w', 1.2]],
    lightning: [['p', 'M58 4 L20 56 H46 L38 96 L80 40 H54 Z', 'g']],
    globe: [['c', 50, 50, 40, 'bl'], ['p', 'M30 22 Q42 34 34 44 Q24 50 30 62 Q36 70 28 80 Q14 68 12 52 Q14 30 30 22 Z', 'gr'], ['p', 'M62 14 Q80 22 86 40 Q76 48 66 40 Q58 30 62 14 Z', 'gr'], ['p', 'M60 62 Q74 58 80 70 Q70 84 58 86 Q52 72 60 62 Z', 'gr']],
  };
  const PROP_PAL = { g: '#ffc83d', y: '#ffe08a', r: '#ff4d5e', gr: '#2ecc71', w: '#ffffff', pk: '#ff9ec7', brown: '#8b5a2b', sk: '#f2b48a', bl: '#4cc9f0' };
  const propCache = {};
  const prop = (name) => propCache[name] || (propCache[name] = WE.buildShapes(PROP_DEFS[name] || PROP_DEFS.star));
  /** Draw a prop centred at x,y with box size `size` (px). */
  const drawProp = (ctx, name, x, y, size, render, rot = 0) => {
    const sc = size / 100;
    ctx.save(); ctx.translate(x, y); if (rot) ctx.rotate(rot); ctx.scale(sc, sc); ctx.translate(-50, -50);
    const pal = Object.assign({}, PROP_PAL, render.pal || {});
    const tip = WE.renderShapes(ctx, prop(name), Object.assign({}, render, { pal, scale: sc }));
    ctx.restore();
    return tip;
  };

  /** The whiteboard artist's hand + marker, tip at (x, y). */
  const drawHand = (ctx, x, y, size, skin = '#e8b48a', marker = '#1a1a1a', sleeve = '#2b2d42') => {
    const s = size / 100;
    ctx.save(); ctx.translate(x, y); ctx.scale(s, s); ctx.rotate(-0.15);
    ctx.lineJoin = 'round'; ctx.lineCap = 'round';
    ctx.fillStyle = 'rgba(0,0,0,0.12)'; ctx.beginPath(); ctx.ellipse(70, 70, 60, 34, 0.6, 0, 7); ctx.fill();
    // marker
    ctx.save(); ctx.rotate(0.62);
    ctx.fillStyle = '#f3f3f3'; WE.roundRect(ctx, 6, -9, 112, 18, 6); ctx.fill(); ctx.strokeStyle = '#222'; ctx.lineWidth = 2.5; ctx.stroke();
    ctx.fillStyle = marker; WE.roundRect(ctx, 86, -10, 34, 20, 5); ctx.fill(); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(6, -6); ctx.lineTo(-3, 0); ctx.lineTo(6, 6); ctx.closePath(); ctx.fillStyle = marker; ctx.fill(); ctx.stroke();
    ctx.restore();
    // fingers & palm
    ctx.fillStyle = skin; ctx.strokeStyle = '#3a2418'; ctx.lineWidth = 3;
    const blob = (cx, cy, rx, ry, r) => { ctx.beginPath(); ctx.ellipse(cx, cy, rx, ry, r, 0, 7); ctx.fill(); ctx.stroke(); };
    blob(52, 52, 20, 12, 0.7); blob(38, 40, 16, 10, 0.5);
    blob(84, 80, 42, 34, 0.5);
    blob(46, 64, 18, 11, 1.1);
    // sleeve
    ctx.fillStyle = sleeve; ctx.beginPath(); ctx.moveTo(98, 96); ctx.lineTo(140, 60); ctx.lineTo(220, 150); ctx.lineTo(160, 210); ctx.closePath(); ctx.fill(); ctx.stroke();
    ctx.fillStyle = WE.Color.shade(sleeve, 0.25); ctx.beginPath(); ctx.moveTo(98, 96); ctx.lineTo(140, 60); ctx.lineTo(150, 72); ctx.lineTo(108, 110); ctx.closePath(); ctx.fill(); ctx.stroke();
    ctx.restore();
  };

  WE.Cast = { SKINS, HAIR, HAIR_STYLES, MOODS, POSES, ACCESSORIES, PROPS: Object.keys(PROP_DEFS), PROP_PAL, character, drawCharacter, prop, drawProp, drawHand };
})(typeof window !== 'undefined' ? window : globalThis);
