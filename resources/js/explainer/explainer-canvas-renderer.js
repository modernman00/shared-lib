/**
 * ExplainerCanvasRenderer — Deterministic 60fps HTML5 Canvas + Web Audio Studio
 *
 * Renders compiled Sovereign Explainer timelines + Style Atlas tokens + word-level
 * transcripts frame-by-frame onto HTML5 <canvas>, with procedural Web Audio SFX,
 * optional voiceover sync, live style switching, and WebM video export.
 */

(function (global) {
    'use strict';

    class ExplainerCanvasRenderer {
        constructor(canvas, manifest, options = {}) {
            this.canvas = canvas;
            this.ctx = canvas ? canvas.getContext('2d') : null;
            this.manifest = manifest || {};
            this.timeline = this.manifest.timeline || { fps: 60, total_duration: 10, scenes: [], dimensions: { width: 1280, height: 720 }, motion_tracks: {} };
            this.style = this.manifest.style_preset || options.style || this.defaultStyle();
            this.wordTimeline = Array.isArray(this.manifest.word_timeline) ? this.manifest.word_timeline : [];
            this.wordsByScene = {};
            this.indexWordsByScene();
            this.sfx = options.sfx || (global.ExplainerSfx ? new global.ExplainerSfx() : null);

            this.fps = this.timeline.fps || 60;
            this.totalDuration = Math.max(1, Number(this.timeline.total_duration) || 10);
            this.width = (this.timeline.dimensions && this.timeline.dimensions.width) || 1280;
            this.height = (this.timeline.dimensions && this.timeline.dimensions.height) || 720;
            this.dpr = typeof window !== 'undefined' ? (window.devicePixelRatio || 1) : 1;

            if (this.canvas) {
                this.canvas.width = this.width * this.dpr;
                this.canvas.height = this.height * this.dpr;
            }

            this.currentTime = 0;
            this.isPlaying = false;
            this.rafId = null;
            this.lastWallTime = 0;
            this.lastSceneIndex = -1;
            this.triggeredClicks = new Set();
            this.triggeredEmphasis = new Set();
            this.audioElement = null;
            this.onTimeUpdate = typeof options.onTimeUpdate === 'function' ? options.onTimeUpdate : null;
            this.onComplete = typeof options.onComplete === 'function' ? options.onComplete : null;
        }

        defaultStyle() {
            return {
                id: 'glassmorphic-dark',
                name: 'Glassmorphic Dark',
                palette: {
                    bg: '#0b1120',
                    surface: '#131c31',
                    primary: '#6366f1',
                    secondary: '#38bdf8',
                    accent: '#22d3ee',
                    text: '#f8fafc',
                    muted: '#94a3b8',
                },
                typography: {
                    headingFont: 'Inter, system-ui, sans-serif',
                    bodyFont: 'Inter, system-ui, sans-serif',
                    monoFont: 'ui-monospace, monospace',
                    headingWeight: '800',
                },
                motion: {
                    transition: 'zoom',
                    motif: 'grid',
                },
                sfxProfile: {
                    clickFreq: 620,
                    whooshDuration: 0.28,
                    chimeNotes: [523.25, 659.25, 783.99],
                },
            };
        }

        indexWordsByScene() {
            this.wordsByScene = {};
            this.wordTimeline.forEach((w) => {
                const idx = typeof w.scene_index === 'number' ? w.scene_index : 0;
                if (!this.wordsByScene[idx]) {
                    this.wordsByScene[idx] = [];
                }
                this.wordsByScene[idx].push(w);
            });
        }

        setStyle(stylePreset) {
            if (!stylePreset || !stylePreset.palette) return;
            this.style = stylePreset;
            this.renderAtTime(this.currentTime, false);
        }

        attachVoiceoverAudio(audioUrl) {
            if (typeof Audio === 'undefined' || !audioUrl) return;
            this.audioElement = new Audio(audioUrl);
            this.audioElement.crossOrigin = 'anonymous';
        }

        play() {
            if (this.isPlaying) return;
            if (this.currentTime >= this.totalDuration - 0.05) {
                this.seek(0);
            }
            this.isPlaying = true;
            this.lastWallTime = performance.now();
            if (this.sfx) this.sfx.ensureContext();

            if (this.audioElement) {
                this.audioElement.currentTime = this.currentTime;
                this.audioElement.play().catch(() => {});
            }

            const tick = (now) => {
                if (!this.isPlaying) return;

                if (this.audioElement && !this.audioElement.paused) {
                    this.currentTime = this.audioElement.currentTime;
                } else {
                    const dt = Math.min(0.5, Math.max(0.001, (now - this.lastWallTime) / 1000));
                    this.currentTime += dt;
                }
                this.lastWallTime = now;

                if (this.currentTime >= this.totalDuration) {
                    this.currentTime = this.totalDuration;
                    this.renderAtTime(this.currentTime, true);
                    this.pause();
                    if (this.sfx && this.style.sfxProfile) {
                        this.sfx.playChime(this.style.sfxProfile.chimeNotes);
                    }
                    if (this.onComplete) this.onComplete();
                    return;
                }

                this.renderAtTime(this.currentTime, true);
                if (this.onTimeUpdate) this.onTimeUpdate(this.currentTime, this.totalDuration);
                this.rafId = requestAnimationFrame(tick);
            };

            this.rafId = requestAnimationFrame(tick);
        }

        pause() {
            this.isPlaying = false;
            if (this.rafId !== null) {
                cancelAnimationFrame(this.rafId);
                this.rafId = null;
            }
            if (this.audioElement) {
                this.audioElement.pause();
            }
        }

        seek(seconds) {
            this.currentTime = Math.max(0, Math.min(this.totalDuration, Number(seconds) || 0));
            this.lastSceneIndex = -1;
            this.triggeredClicks.clear();
            this.triggeredEmphasis.clear();
            if (this.audioElement) {
                this.audioElement.currentTime = this.currentTime;
            }
            this.renderAtTime(this.currentTime, false);
            if (this.onTimeUpdate) this.onTimeUpdate(this.currentTime, this.totalDuration);
        }

        easeOutCubic(t) {
            const x = Math.max(0, Math.min(1, t));
            return 1 - Math.pow(1 - x, 3);
        }

        findSceneAtTime(t) {
            const scenes = this.timeline.scenes || [];
            for (let i = 0; i < scenes.length; i++) {
                if (t >= scenes[i].start_time && t < scenes[i].end_time) {
                    return { scene: scenes[i], index: i };
                }
            }
            if (scenes.length > 0) {
                return { scene: scenes[scenes.length - 1], index: scenes.length - 1 };
            }
            return { scene: null, index: -1 };
        }

        renderAtTime(t, triggerAudio = false) {
            if (!this.ctx) return;
            const ctx = this.ctx;
            const pal = this.style.palette;
            const typo = this.style.typography;
            const motion = this.style.motion || { transition: 'zoom', motif: 'grid' };
            const sfxProf = this.style.sfxProfile || { clickFreq: 620, whooshDuration: 0.28 };

            const { scene, index: sceneIdx } = this.findSceneAtTime(t);
            const storyboardScenes = (this.manifest.storyboard && this.manifest.storyboard.scenes) || [];
            const rawScene = storyboardScenes[sceneIdx] || {};

            if (triggerAudio && this.sfx && sceneIdx !== -1 && sceneIdx !== this.lastSceneIndex) {
                this.sfx.playWhoosh(sfxProf.whooshDuration || 0.28);
                this.lastSceneIndex = sceneIdx;
            }

            // 1. Background fill
            ctx.save();
            if (this.dpr && this.dpr !== 1) {
                ctx.scale(this.dpr, this.dpr);
            }
            ctx.fillStyle = pal.bg;
            ctx.fillRect(0, 0, this.width, this.height);

            // 2. Animated background motif
            this.drawMotif(ctx, motion.motif, pal, t);

            if (!scene) {
                ctx.restore();
                return;
            }

            const sceneElapsed = Math.max(0, t - scene.start_time);
            const sceneDur = Math.max(0.5, scene.duration || (scene.end_time - scene.start_time));
            const progress = Math.max(0, Math.min(1, sceneElapsed / sceneDur));
            const enterT = this.easeOutCubic(Math.min(1, sceneElapsed / 0.45));

            // 3. Apply scene transition transform
            ctx.save();
            this.applyTransitionTransform(ctx, motion.transition, enterT);

            // 4. Stage pill & scene counter
            const stage = rawScene.stage || (sceneIdx === 0 ? 'problem' : (sceneIdx === storyboardScenes.length - 1 ? 'call_to_action' : 'solution'));
            const stageLabel = stage === 'problem' ? 'THE PROBLEM' : (stage === 'solution' ? 'THE SOLUTION' : 'NEXT STEP');
            const padX = Math.round(this.width * 0.08);
            const topY = Math.round(this.height * 0.14);

            this.drawPill(ctx, padX, topY, `${stageLabel}  ·  0${sceneIdx + 1}`, pal.primary, pal.text);

            // 5. Scene Title (kinetic reveal)
            const titleText = scene.title || rawScene.title || 'Explainer Scene';
            const titleSize = Math.round(this.width * 0.042);
            ctx.font = `${typo.headingWeight || '800'} ${titleSize}px ${typo.headingFont}`;
            ctx.fillStyle = pal.text;
            ctx.textBaseline = 'top';
            this.drawWrappedText(ctx, titleText, padX, topY + 56, this.width - padX * 2, titleSize * 1.2);

            // 6. Central Surface Card with Kinetic Progress Visual
            const cardX = padX;
            const cardY = Math.round(this.height * 0.36);
            const cardW = this.width - padX * 2;
            const cardH = Math.round(this.height * 0.34);

            this.drawRoundedRect(ctx, cardX, cardY, cardW, cardH, 20, pal.surface, pal.primary + '55', 2);

            // Inner animated data bars / waveform visual
            this.drawSceneVisualBars(ctx, cardX + 36, cardY + 34, cardW - 72, cardH - 68, pal, progress, t, stage);

            // 7. Word-Level Synchronised Kinetic Captions
            this.drawWordCaptions(ctx, sceneIdx, t, padX, Math.round(this.height * 0.75), cardW, pal, typo, triggerAudio);

            ctx.restore(); // end transition transform

            // 8. Virtual Cursor & Click Ripple
            this.drawCursorOverlay(ctx, sceneIdx, t, pal, sfxProf, triggerAudio);

            // 9. Bottom Timeline Progress Bar
            const barH = 6;
            ctx.fillStyle = pal.surface;
            ctx.fillRect(0, this.height - barH, this.width, barH);
            ctx.fillStyle = pal.accent;
            ctx.fillRect(0, this.height - barH, this.width * (t / this.totalDuration), barH);

            ctx.restore();
        }

        applyTransitionTransform(ctx, transition, enterT) {
            const cx = this.width / 2;
            const cy = this.height / 2;
            if (transition === 'zoom') {
                const scale = 0.94 + 0.06 * enterT;
                ctx.translate(cx, cy);
                ctx.scale(scale, scale);
                ctx.translate(-cx, -cy);
                ctx.globalAlpha = enterT;
            } else if (transition === 'slide') {
                ctx.translate((1 - enterT) * 70, 0);
                ctx.globalAlpha = enterT;
            } else if (transition === 'kinetic-pop') {
                const scale = 0.88 + 0.12 * enterT;
                ctx.translate(cx, cy);
                ctx.scale(scale, scale);
                ctx.translate(-cx, -cy);
            } else if (transition === 'wipe') {
                ctx.beginPath();
                ctx.rect(0, 0, this.width * enterT, this.height);
                ctx.clip();
            } else {
                ctx.globalAlpha = enterT;
            }
        }

        drawMotif(ctx, motif, pal, t) {
            ctx.save();
            ctx.globalAlpha = 0.14;
            if (motif === 'grid' || motif === 'blueprint') {
                ctx.strokeStyle = motif === 'blueprint' ? pal.secondary : pal.muted;
                ctx.lineWidth = 1;
                const step = 48;
                const offset = (t * 12) % step;
                for (let x = offset; x < this.width; x += step) {
                    ctx.beginPath();
                    ctx.moveTo(x, 0);
                    ctx.lineTo(x, this.height);
                    ctx.stroke();
                }
                for (let y = offset; y < this.height; y += step) {
                    ctx.beginPath();
                    ctx.moveTo(0, y);
                    ctx.lineTo(this.width, y);
                    ctx.stroke();
                }
            } else if (motif === 'dots') {
                ctx.fillStyle = pal.muted;
                const step = 36;
                for (let x = 24; x < this.width; x += step) {
                    for (let y = 24; y < this.height; y += step) {
                        ctx.beginPath();
                        ctx.arc(x, y, 1.5, 0, Math.PI * 2);
                        ctx.fill();
                    }
                }
            } else if (motif === 'waves') {
                ctx.strokeStyle = pal.primary;
                ctx.lineWidth = 2;
                for (let w = 0; w < 3; w++) {
                    ctx.beginPath();
                    for (let x = 0; x <= this.width; x += 24) {
                        const y = this.height * (0.25 + w * 0.25) + Math.sin(x * 0.008 + t * 1.6 + w) * 18;
                        if (x === 0) ctx.moveTo(x, y);
                        else ctx.lineTo(x, y);
                    }
                    ctx.stroke();
                }
            } else if (motif === 'particles') {
                ctx.fillStyle = pal.primary;
                for (let i = 0; i < 24; i++) {
                    const px = ((i * 197 + t * 28) % this.width);
                    const py = ((i * 113 + Math.sin(t + i) * 30) % this.height);
                    ctx.beginPath();
                    ctx.arc(px, Math.abs(py), 2 + (i % 3), 0, Math.PI * 2);
                    ctx.fill();
                }
            }
            ctx.restore();
        }

        drawSceneVisualBars(ctx, x, y, w, h, pal, progress, t, stage) {
            const barCount = 12;
            const gap = 14;
            const barW = (w - gap * (barCount - 1)) / barCount;

            for (let i = 0; i < barCount; i++) {
                const wave = 0.35 + 0.65 * Math.abs(Math.sin(t * 2.4 + i * 0.55));
                const reveal = Math.max(0.08, Math.min(1, progress * 1.4 - i * 0.05));
                const bh = h * wave * reveal;
                const bx = x + i * (barW + gap);
                const by = y + h - bh;

                ctx.fillStyle = i % 3 === 0 ? pal.accent : (stage === 'problem' ? pal.secondary : pal.primary);
                this.drawRoundedRect(ctx, bx, by, barW, bh, 8, ctx.fillStyle, null, 0);
            }
        }

        drawWordCaptions(ctx, sceneIdx, t, x, y, maxW, pal, typo, triggerAudio) {
            const sceneWords = this.wordsByScene[sceneIdx] || [];
            if (sceneWords.length === 0) return;

            const fontSize = Math.round(this.width * 0.023);
            ctx.font = `600 ${fontSize}px ${typo.bodyFont}`;
            ctx.textBaseline = 'top';

            let cursorX = x;
            let cursorY = y;
            const lineH = fontSize * 1.45;
            const maxY = this.height - 24;

            sceneWords.forEach((entry, idx) => {
                const wordStr = entry.word + ' ';
                const wWidth = ctx.measureText(wordStr).width;
                if (cursorX + wWidth > x + maxW) {
                    cursorX = x;
                    cursorY += lineH;
                }

                if (cursorY + lineH > maxY) {
                    return; // Avoid overflowing bottom progress bar
                }

                const isSpoken = t >= entry.start;
                const isActive = t >= entry.start && t <= entry.end;

                if (triggerAudio && isActive && entry.is_emphasis && this.sfx) {
                    const key = `${sceneIdx}_${idx}`;
                    if (!this.triggeredEmphasis.has(key)) {
                        this.triggeredEmphasis.add(key);
                        this.sfx.playTick(920);
                    }
                }

                if (isActive) {
                    ctx.fillStyle = pal.accent;
                } else if (isSpoken) {
                    ctx.fillStyle = entry.is_emphasis ? pal.secondary : pal.text;
                } else {
                    ctx.fillStyle = pal.muted;
                }

                ctx.fillText(wordStr, cursorX, cursorY);
                cursorX += wWidth;
            });
        }

        drawCursorOverlay(ctx, sceneIdx, t, pal, sfxProf, triggerAudio) {
            const tracks = (this.timeline.motion_tracks && this.timeline.motion_tracks.cursor) || [];
            const cursorTrack = tracks[sceneIdx];
            if (!cursorTrack) return;

            const startT = cursorTrack.start_frame / this.fps;
            const actionT = cursorTrack.action_frame / this.fps;
            const endT = cursorTrack.end_frame / this.fps;
            if (t < startT || t > endT) return;

            const moveProgress = this.easeOutCubic((t - startT) / Math.max(0.2, actionT - startT));
            const targetX = this.width * (cursorTrack.target_x || 0.5);
            const targetY = this.height * (cursorTrack.target_y || 0.5);
            const cx = this.width * 0.2 + (targetX - this.width * 0.2) * moveProgress;
            const cy = this.height * 0.8 + (targetY - this.height * 0.8) * moveProgress;

            // Click ripple
            if (t >= actionT && t <= actionT + 0.45) {
                if (triggerAudio && this.sfx && !this.triggeredClicks.has(sceneIdx)) {
                    this.triggeredClicks.add(sceneIdx);
                    this.sfx.playClick(sfxProf.clickFreq || 620);
                }
                const rp = (t - actionT) / 0.45;
                ctx.save();
                ctx.beginPath();
                ctx.arc(targetX, targetY, 8 + rp * (cursorTrack.ripple_radius_px || 32), 0, Math.PI * 2);
                ctx.strokeStyle = pal.accent;
                ctx.globalAlpha = 1 - rp;
                ctx.lineWidth = 3;
                ctx.stroke();
                ctx.restore();
            }

            // Cursor pointer
            ctx.save();
            ctx.translate(cx, cy);
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(0, 22);
            ctx.lineTo(6, 17);
            ctx.lineTo(13, 25);
            ctx.lineTo(16, 22);
            ctx.lineTo(10, 14);
            ctx.lineTo(18, 13);
            ctx.closePath();
            ctx.fillStyle = pal.text;
            ctx.fill();
            ctx.strokeStyle = pal.bg;
            ctx.lineWidth = 2;
            ctx.stroke();
            ctx.restore();
        }

        drawPill(ctx, x, y, text, bgColor, textColor) {
            ctx.save();
            ctx.font = '700 14px Inter, system-ui, sans-serif';
            const tw = ctx.measureText(text).width;
            const pw = tw + 28;
            const ph = 32;
            this.drawRoundedRect(ctx, x, y, pw, ph, 16, bgColor, null, 0);
            ctx.fillStyle = textColor;
            ctx.textBaseline = 'middle';
            ctx.fillText(text, x + 14, y + ph / 2);
            ctx.restore();
        }

        drawRoundedRect(ctx, x, y, w, h, r, fill, stroke, lineWidth) {
            ctx.save();
            ctx.beginPath();
            ctx.moveTo(x + r, y);
            ctx.lineTo(x + w - r, y);
            ctx.quadraticCurveTo(x + w, y, x + w, y + r);
            ctx.lineTo(x + w, y + h - r);
            ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
            ctx.lineTo(x + r, y + h);
            ctx.quadraticCurveTo(x, y + h, x, y + h - r);
            ctx.lineTo(x, y + r);
            ctx.quadraticCurveTo(x, y, x + r, y);
            ctx.closePath();
            if (fill) {
                ctx.fillStyle = fill;
                ctx.fill();
            }
            if (stroke && lineWidth > 0) {
                ctx.strokeStyle = stroke;
                ctx.lineWidth = lineWidth;
                ctx.stroke();
            }
            ctx.restore();
        }

        drawWrappedText(ctx, text, x, y, maxW, lineHeight) {
            const words = String(text).split(/\s+/);
            let line = '';
            let currY = y;
            for (let i = 0; i < words.length; i++) {
                const test = line + words[i] + ' ';
                if (ctx.measureText(test).width > maxW && i > 0) {
                    ctx.fillText(line.trim(), x, currY);
                    line = words[i] + ' ';
                    currY += lineHeight;
                } else {
                    line = test;
                }
            }
            if (line.trim() !== '') {
                ctx.fillText(line.trim(), x, currY);
            }
        }

        /**
         * Exports the explainer to a downloadable video (Canvas + Web Audio SFX + Voiceover).
         * Supports optional target duration override (in seconds) or options object.
         */
        async exportWebM(options = {}) {
            if (!this.canvas || typeof MediaRecorder === 'undefined') {
                throw new Error('MediaRecorder is not supported in this browser.');
            }

            const targetDuration = typeof options === 'number'
                ? Math.min(this.totalDuration, Math.max(0.5, options))
                : (options && typeof options.duration === 'number'
                    ? Math.min(this.totalDuration, Math.max(0.5, options.duration))
                    : this.totalDuration);

            // Ensure Web Audio context is active and clock is ticking
            if (this.sfx) {
                const sfxCtx = this.sfx.ensureContext();
                if (sfxCtx && sfxCtx.state === 'suspended') {
                    try {
                        await sfxCtx.resume();
                    } catch (_) {}
                }
            }

            // Route voiceover audio into Web Audio stream destination if present
            if (this.audioElement && this.sfx) {
                const ctx = this.sfx.ensureContext();
                if (ctx && !this.audioElementSource) {
                    try {
                        this.audioElementSource = ctx.createMediaElementSource(this.audioElement);
                        this.audioElementSource.connect(this.sfx.masterGain);
                    } catch (_) {
                        // In case already connected or cross-origin restrictions
                    }
                }
            }

            const videoStream = this.canvas.captureStream(this.fps);
            const tracks = [...videoStream.getVideoTracks()];

            if (this.sfx && this.sfx.ctx && this.sfx.ctx.state === 'running') {
                const audioDest = this.sfx.getStreamDestination();
                if (audioDest && audioDest.stream) {
                    tracks.push(...audioDest.stream.getAudioTracks());
                }
            }

            const combined = new MediaStream(tracks);

            // Safari / Chrome MIME fallback matrix
            const candidateMimes = [
                'video/webm;codecs=vp9,opus',
                'video/webm;codecs=vp8,opus',
                'video/webm',
                'video/mp4;codecs=avc1,mp4a.40.2',
                'video/mp4',
            ];

            let selectedMime = '';
            for (const cand of candidateMimes) {
                if (MediaRecorder.isTypeSupported(cand)) {
                    selectedMime = cand;
                    break;
                }
            }

            const recorderOptions = selectedMime ? { mimeType: selectedMime } : undefined;
            const recorder = new MediaRecorder(combined, recorderOptions);
            const chunks = [];
            recorder.ondataavailable = (e) => {
                if (e.data && e.data.size > 0) chunks.push(e.data);
            };

            return new Promise((resolve, reject) => {
                let durationInterval = null;

                const finishRecording = () => {
                    if (durationInterval) {
                        clearInterval(durationInterval);
                        durationInterval = null;
                    }
                    try {
                        if (recorder.state !== 'inactive') {
                            try { recorder.requestData(); } catch (_) {}
                            recorder.stop();
                        }
                    } catch (err) {
                        reject(err);
                    }
                };

                recorder.onerror = (event) => {
                    if (durationInterval) {
                        clearInterval(durationInterval);
                        durationInterval = null;
                    }
                    reject(event.error || new Error('MediaRecorder recording failed.'));
                };

                recorder.onstop = () => {
                    if (durationInterval) {
                        clearInterval(durationInterval);
                        durationInterval = null;
                    }
                    setTimeout(() => {
                        const outputMime = selectedMime || 'video/webm';
                        resolve(new Blob(chunks, { type: outputMime }));
                    }, 50);
                };

                this.seek(0);
                recorder.start(100);
                const prevOnComplete = this.onComplete;
                this.onComplete = () => {
                    try {
                        finishRecording();
                    } finally {
                        this.onComplete = prevOnComplete;
                    }
                };

                if (targetDuration < this.totalDuration) {
                    const startWall = performance.now();
                    durationInterval = setInterval(() => {
                        const elapsedSec = (performance.now() - startWall) / 1000;
                        if (this.currentTime >= targetDuration || elapsedSec >= targetDuration) {
                            this.pause();
                            if (this.onComplete) this.onComplete();
                        }
                    }, 50);
                }

                this.play();
            });
        }
    }

    global.ExplainerCanvasRenderer = ExplainerCanvasRenderer;
})(typeof window !== 'undefined' ? window : globalThis);
