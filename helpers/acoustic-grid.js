/**
 * PartyPulseAcousticGrid Client Helper
 * 
 * Engineered by the Advanced Technology Group (ATG) under Olutobi (Deloitte) and Victor (CTO).
 * High-performance, zero-npm distributed multi-phone speaker mesh.
 */
(function(global) {
  'use strict';

  class PartyPulseAcousticGrid {
    constructor(options) {
      options = options || {};
      this.clockSyncEndpoint = options.clockSyncEndpoint || '/api/acoustic/clock-sync';
      this.channel = options.channel || 'party-room-general';
      this.scheduleLeadMs = options.scheduleLeadMs || 1500;
      
      this.audioCtx = null;
      this.clockOffsetMs = 0;
      this.roundTripTimeMs = 0;
      this.isSynced = false;
      this.isPlaying = false;
      this.analyser = null;
      this.gainNode = null;
      this.loopTimer = null;
    }

    initAudio() {
      if (!this.audioCtx) {
        var AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) {
          throw new Error('Web Audio API is not supported in this browser.');
        }
        this.audioCtx = new AudioContextClass();
        this.gainNode = this.audioCtx.createGain();
        this.analyser = this.audioCtx.createAnalyser();
        this.analyser.fftSize = 64;

        this.gainNode.connect(this.analyser);
        this.analyser.connect(this.audioCtx.destination);
      }

      if (this.audioCtx.state === 'suspended') {
        this.audioCtx.resume();
      }

      return this.audioCtx;
    }

    syncClock() {
      var self = this;
      var samples = [];
      var iterations = 3;
      var p = Promise.resolve();

      for (var i = 0; i < iterations; i++) {
        (function() {
          p = p.then(function() {
            var t0 = Date.now();
            return fetch(self.clockSyncEndpoint + '?t=' + t0, {
              headers: { 'Accept': 'application/json' },
              cache: 'no-store'
            })
            .then(function(res) {
              var t3 = Date.now();
              if (res.ok) {
                return res.json().then(function(data) {
                  var t1 = Number(data.server_receive_time || data.timestamp || Date.now());
                  var t2 = Number(data.server_transmit_time || data.timestamp || Date.now());
                  var rtt = (t3 - t0) - (t2 - t1);
                  var offset = ((t1 - t0) + (t2 - t3)) / 2;
                  samples.push({ rtt: rtt, offset: offset });
                });
              }
            })
            .catch(function(err) {
              console.warn('[PartyPulse] Clock sync sample failed:', err);
            });
          });
        })();
      }

      return p.then(function() {
        if (samples.length > 0) {
          samples.sort(function(a, b) { return a.rtt - b.rtt; });
          self.roundTripTimeMs = Math.round(samples[0].rtt);
          self.clockOffsetMs = Math.round(samples[0].offset);
          self.isSynced = true;
        } else {
          self.clockOffsetMs = 0;
          self.isSynced = false;
        }
        return {
          offsetMs: self.clockOffsetMs,
          rttMs: self.roundTripTimeMs,
          isSynced: self.isSynced
        };
      });
    }

    getNetworkTime() {
      return Date.now() + this.clockOffsetMs;
    }

    playSynthesizedBeat(targetServerTime) {
      this.initAudio();
      this.stop();
      this.isPlaying = true;

      var bpm = 124;
      var beatInterval = 60 / bpm;
      var leadMs = targetServerTime ? Math.max(0, targetServerTime - this.getNetworkTime()) : 0;
      var startDelaySec = leadMs / 1000;
      var startTime = this.audioCtx.currentTime + startDelaySec;

      for (var bar = 0; bar < 8; bar++) {
        for (var beat = 0; beat < 4; beat++) {
          var time = startTime + (bar * 4 + beat) * beatInterval;
          if (beat === 0 || beat === 2) {
            this._scheduleKick(time);
          }
          if (beat === 1 || beat === 3) {
            this._scheduleSnare(time);
          }
          this._scheduleHiHat(time);
          this._scheduleHiHat(time + beatInterval * 0.5);
        }
      }

      var self = this;
      var totalDurationMs = (8 * 4 * beatInterval) * 1000;
      this.loopTimer = setTimeout(function() {
        if (self.isPlaying) {
          self.playSynthesizedBeat();
        }
      }, leadMs + totalDurationMs - 100);
    }

    _scheduleKick(time) {
      var osc = this.audioCtx.createOscillator();
      var gain = this.audioCtx.createGain();

      osc.frequency.setValueAtTime(150, time);
      osc.frequency.exponentialRampToValueAtTime(0.01, time + 0.35);

      gain.gain.setValueAtTime(1.0, time);
      gain.gain.exponentialRampToValueAtTime(0.001, time + 0.35);

      osc.connect(gain);
      gain.connect(this.gainNode);

      osc.start(time);
      osc.stop(time + 0.35);
    }

    _scheduleSnare(time) {
      var osc = this.audioCtx.createOscillator();
      var gain = this.audioCtx.createGain();

      osc.type = 'triangle';
      osc.frequency.setValueAtTime(220, time);
      gain.gain.setValueAtTime(0.7, time);
      gain.gain.exponentialRampToValueAtTime(0.001, time + 0.2);

      osc.connect(gain);
      gain.connect(this.gainNode);

      osc.start(time);
      osc.stop(time + 0.2);
    }

    _scheduleHiHat(time) {
      var osc = this.audioCtx.createOscillator();
      var gain = this.audioCtx.createGain();

      osc.type = 'square';
      osc.frequency.setValueAtTime(8000, time);
      gain.gain.setValueAtTime(0.15, time);
      gain.gain.exponentialRampToValueAtTime(0.001, time + 0.05);

      osc.connect(gain);
      gain.connect(this.gainNode);

      osc.start(time);
      osc.stop(time + 0.05);
    }

    attachVisualizer(canvas) {
      if (!canvas || !this.analyser) return;
      var ctx = canvas.getContext('2d');
      var bufferLength = this.analyser.frequencyBinCount;
      var dataArray = new Uint8Array(bufferLength);
      var self = this;

      var render = function() {
        if (!self.isPlaying) {
          ctx.clearRect(0, 0, canvas.width, canvas.height);
          return;
        }

        requestAnimationFrame(render);
        self.analyser.getByteFrequencyData(dataArray);

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        var cx = canvas.width / 2;
        var cy = canvas.height / 2;
        var baseRadius = Math.min(cx, cy) * 0.45;

        var sum = 0;
        for (var i = 0; i < bufferLength; i++) {
          sum += dataArray[i];
        }
        var avg = sum / bufferLength;
        var pulseRadius = baseRadius + (avg * 0.4);

        var grad = ctx.createRadialGradient(cx, cy, baseRadius * 0.5, cx, cy, pulseRadius);
        grad.addColorStop(0, 'rgba(201, 168, 106, 0.85)');
        grad.addColorStop(0.7, 'rgba(99, 102, 241, 0.45)');
        grad.addColorStop(1, 'transparent');

        ctx.beginPath();
        ctx.arc(cx, cy, pulseRadius, 0, Math.PI * 2);
        ctx.fillStyle = grad;
        ctx.fill();

        ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(cx, cy, pulseRadius * 1.15, 0, Math.PI * 2);
        ctx.stroke();
      };

      requestAnimationFrame(render);
    }

    stop() {
      this.isPlaying = false;
      if (this.loopTimer) {
        clearTimeout(this.loopTimer);
        this.loopTimer = null;
      }
    }
  }

  global.PartyPulseAcousticGrid = PartyPulseAcousticGrid;
})(typeof window !== 'undefined' ? window : globalThis);
