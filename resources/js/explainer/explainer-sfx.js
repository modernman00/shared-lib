/**
 * ExplainerSfx — Procedural Web Audio API Sound Effects Engine
 *
 * Zero-asset, deterministic sound synthesiser for code-driven explainer videos.
 * Synthesises whooshes, tactile UI clicks, kinetic word ticks, and harmonic chimes
 * directly via Web Audio API, with optional MediaStreamDestination capture for WebM export.
 */

(function (global) {
    'use strict';

    class ExplainerSfx {
        constructor(options = {}) {
            this.muted = Boolean(options.muted);
            this.masterVolume = typeof options.volume === 'number' ? options.volume : 0.35;
            this.ctx = null;
            this.masterGain = null;
            this.streamDest = null;
        }

        ensureContext() {
            if (typeof global.AudioContext === 'undefined' && typeof global.webkitAudioContext === 'undefined') {
                return null;
            }
            if (!this.ctx) {
                const AudioCtx = global.AudioContext || global.webkitAudioContext;
                this.ctx = new AudioCtx();
                this.masterGain = this.ctx.createGain();
                this.masterGain.gain.value = this.muted ? 0 : this.masterVolume;

                // DynamicsCompressorNode limiter to prevent digital clipping on overlapping SFX
                this.limiter = this.ctx.createDynamicsCompressor();
                this.limiter.threshold.setValueAtTime(-2.0, this.ctx.currentTime);
                this.limiter.knee.setValueAtTime(3.0, this.ctx.currentTime);
                this.limiter.ratio.setValueAtTime(12.0, this.ctx.currentTime);
                this.limiter.attack.setValueAtTime(0.003, this.ctx.currentTime);
                this.limiter.release.setValueAtTime(0.15, this.ctx.currentTime);

                this.masterGain.connect(this.limiter);
                this.limiter.connect(this.ctx.destination);

                // Continuous silent carrier to keep Web Audio clock active for MediaStreamDestination capture
                if (typeof this.ctx.createConstantSource === 'function') {
                    this.carrier = this.ctx.createConstantSource();
                    this.carrier.offset.setValueAtTime(0.0001, this.ctx.currentTime);
                    this.carrier.connect(this.masterGain);
                    this.carrier.start();
                }
            }
            if (this.ctx.state === 'suspended') {
                this.ctx.resume().catch(() => {});
            }
            return this.ctx;
        }

        setMuted(muted) {
            this.muted = Boolean(muted);
            if (this.masterGain && this.ctx) {
                this.masterGain.gain.setTargetAtTime(this.muted ? 0 : this.masterVolume, this.ctx.currentTime, 0.015);
            }
        }

        getStreamDestination() {
            const ctx = this.ensureContext();
            if (!ctx) return null;
            if (!this.streamDest) {
                this.streamDest = ctx.createMediaStreamDestination();
                if (this.limiter) {
                    this.limiter.connect(this.streamDest);
                } else {
                    this.masterGain.connect(this.streamDest);
                }
            }
            return this.streamDest;
        }

        /**
         * Bandpass-filtered noise sweep for scene transitions.
         */
        playWhoosh(duration = 0.28, startFreq = 260, endFreq = 1400) {
            if (this.muted) return;
            const ctx = this.ensureContext();
            if (!ctx) return;

            const now = ctx.currentTime;
            const sampleCount = Math.max(1, Math.floor(ctx.sampleRate * duration));
            const buffer = ctx.createBuffer(1, sampleCount, ctx.sampleRate);
            const data = buffer.getChannelData(0);
            for (let i = 0; i < sampleCount; i++) {
                data[i] = Math.random() * 2 - 1;
            }

            const noise = ctx.createBufferSource();
            noise.buffer = buffer;

            const filter = ctx.createBiquadFilter();
            filter.type = 'bandpass';
            filter.Q.value = 1.8;
            filter.frequency.setValueAtTime(startFreq, now);
            filter.frequency.exponentialRampToValueAtTime(endFreq, now + duration * 0.6);
            filter.frequency.exponentialRampToValueAtTime(Math.max(120, startFreq * 0.7), now + duration);

            const gain = ctx.createGain();
            gain.gain.setValueAtTime(0.001, now);
            gain.gain.linearRampToValueAtTime(0.45, now + duration * 0.35);
            gain.gain.exponentialRampToValueAtTime(0.001, now + duration);

            noise.connect(filter);
            filter.connect(gain);
            gain.connect(this.masterGain);

            noise.onended = () => {
                try {
                    noise.disconnect();
                    filter.disconnect();
                    gain.disconnect();
                } catch (_) {}
            };

            noise.start(now);
            noise.stop(now + duration);
        }

        /**
         * Crisp tactile click for virtual cursor actions.
         */
        playClick(freq = 620) {
            if (this.muted) return;
            const ctx = this.ensureContext();
            if (!ctx) return;

            const now = ctx.currentTime;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'triangle';
            osc.frequency.setValueAtTime(freq, now);
            osc.frequency.exponentialRampToValueAtTime(Math.max(120, freq * 0.35), now + 0.065);

            gain.gain.setValueAtTime(0.55, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.07);

            osc.connect(gain);
            gain.connect(this.masterGain);

            osc.onended = () => {
                try {
                    osc.disconnect();
                    gain.disconnect();
                } catch (_) {}
            };

            osc.start(now);
            osc.stop(now + 0.075);
        }

        /**
         * Subtle kinetic pop for emphasised words or metric counters.
         */
        playTick(freq = 880) {
            if (this.muted) return;
            const ctx = this.ensureContext();
            if (!ctx) return;

            const now = ctx.currentTime;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, now);
            osc.frequency.exponentialRampToValueAtTime(freq * 1.15, now + 0.03);

            gain.gain.setValueAtTime(0.18, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.04);

            osc.connect(gain);
            gain.connect(this.masterGain);

            osc.onended = () => {
                try {
                    osc.disconnect();
                    gain.disconnect();
                } catch (_) {}
            };

            osc.start(now);
            osc.stop(now + 0.045);
        }

        /**
         * Harmonic triad chime for CTA / completion beats.
         */
        playChime(notes = [523.25, 659.25, 783.99]) {
            if (this.muted) return;
            const ctx = this.ensureContext();
            if (!ctx) return;

            const now = ctx.currentTime;
            notes.forEach((freq, idx) => {
                const start = now + idx * 0.06;
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, start);

                gain.gain.setValueAtTime(0.001, start);
                gain.gain.linearRampToValueAtTime(0.24, start + 0.025);
                gain.gain.exponentialRampToValueAtTime(0.001, start + 0.45);

                osc.connect(gain);
                gain.connect(this.masterGain);

                osc.onended = () => {
                    try {
                        osc.disconnect();
                        gain.disconnect();
                    } catch (_) {}
                };

                osc.start(start);
                osc.stop(start + 0.48);
            });
        }
    }

    global.ExplainerSfx = ExplainerSfx;
})(typeof window !== 'undefined' ? window : globalThis);
