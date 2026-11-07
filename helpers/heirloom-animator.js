/**
 * LivingHeirloomAnimator Client Helper
 * 
 * Engineered by the Advanced Technology Group (ATG) under Olutobi (Deloitte) and Victor (CTO).
 * High-performance, zero-npm 2.5D facial depth warping and parallax portrait animator.
 */
(function(global) {
  'use strict';

  class LivingHeirloomAnimator {
    constructor(canvas, options) {
      if (!canvas) {
        throw new Error('LivingHeirloomAnimator requires a valid HTMLCanvasElement.');
      }
      this.canvas = canvas;
      this.ctx = canvas.getContext('2d');
      options = options || {};
      this.options = {
        enableParallax: options.enableParallax !== false,
        enableBlink: options.enableBlink !== false,
        enableBreathing: options.enableBreathing !== false,
        breathPeriodSec: options.breathPeriodSec || 4.2
      };

      this.image = null;
      this.isLoaded = false;
      this.isRunning = false;
      this.animFrameId = null;

      this.faceMesh = {
        center: { x: 0.5, y: 0.42 },
        leftEye: { x: 0.41, y: 0.38 },
        rightEye: { x: 0.59, y: 0.38 },
        chin: { x: 0.5, y: 0.65 },
        chest: { x: 0.5, y: 0.88 }
      };

      this.targetTiltX = 0;
      this.targetTiltY = 0;
      this.currentTiltX = 0;
      this.currentTiltY = 0;

      this.blinkProgress = 0;
      this.isBlinking = false;
      this.nextBlinkTime = Date.now() + 3000;

      this._setupEventListeners();
    }

    loadImage(source) {
      var self = this;
      return new Promise(function(resolve, reject) {
        if (typeof source === 'string') {
          var img = new Image();
          img.crossOrigin = 'anonymous';
          img.onload = function() {
            self.image = img;
            self.isLoaded = true;
            self.drawStatic();
            resolve();
          };
          img.onerror = function(err) { reject(err); };
          img.src = source;
        } else if (source instanceof HTMLImageElement) {
          self.image = source;
          self.isLoaded = true;
          self.drawStatic();
          resolve();
        } else {
          reject(new Error('Invalid image source provided.'));
        }
      });
    }

    _setupEventListeners() {
      var self = this;
      this.canvas.addEventListener('mousemove', function(e) {
        if (!self.options.enableParallax) return;
        var rect = self.canvas.getBoundingClientRect();
        var x = (e.clientX - rect.left) / rect.width;
        var y = (e.clientY - rect.top) / rect.height;
        self.targetTiltX = (x - 0.5) * 0.12;
        self.targetTiltY = (y - 0.5) * 0.08;
      });

      this.canvas.addEventListener('mouseleave', function() {
        self.targetTiltX = 0;
        self.targetTiltY = 0;
      });

      if (typeof window !== 'undefined' && window.DeviceOrientationEvent) {
        window.addEventListener('deviceorientation', function(e) {
          if (!self.options.enableParallax || !e.gamma || !e.beta) return;
          self.targetTiltX = Math.max(-0.15, Math.min(0.15, e.gamma / 60));
          self.targetTiltY = Math.max(-0.12, Math.min(0.12, (e.beta - 45) / 60));
        });
      }
    }

    start() {
      if (!this.isLoaded) return;
      this.isRunning = true;
      var startTime = performance.now();
      var self = this;

      var loop = function(now) {
        if (!self.isRunning) return;
        var elapsedSec = (now - startTime) / 1000;

        self._updateAnimationState(now, elapsedSec);
        self._renderFrame(elapsedSec);

        self.animFrameId = requestAnimationFrame(loop);
      };

      this.animFrameId = requestAnimationFrame(loop);
    }

    stop() {
      this.isRunning = false;
      if (this.animFrameId) {
        cancelAnimationFrame(this.animFrameId);
        this.animFrameId = null;
      }
    }

    _updateAnimationState(now, elapsedSec) {
      this.currentTiltX += (this.targetTiltX - this.currentTiltX) * 0.08;
      this.currentTiltY += (this.targetTiltY - this.currentTiltY) * 0.08;

      if (Math.abs(this.targetTiltX) < 0.01 && Math.abs(this.targetTiltY) < 0.01) {
        this.currentTiltX = Math.sin(elapsedSec * 0.8) * 0.025;
        this.currentTiltY = Math.cos(elapsedSec * 0.6) * 0.015;
      }

      if (this.options.enableBlink) {
        if (!this.isBlinking && now > this.nextBlinkTime) {
          this.isBlinking = true;
          this.blinkProgress = 0;
        }

        if (this.isBlinking) {
          this.blinkProgress += 0.15;
          if (this.blinkProgress >= 2.0) {
            this.isBlinking = false;
            this.blinkProgress = 0;
            this.nextBlinkTime = now + 2500 + (Math.random() * 3000);
          }
        }
      }
    }

    _renderFrame(elapsedSec) {
      var w = this.canvas.width;
      var h = this.canvas.height;
      var ctx = this.ctx;

      ctx.clearRect(0, 0, w, h);

      var breathOffset = 0;
      if (this.options.enableBreathing) {
        var breathCycle = (elapsedSec * 2 * Math.PI) / this.options.breathPeriodSec;
        breathOffset = Math.sin(breathCycle) * 3.5;
      }

      ctx.save();

      ctx.translate(w / 2, h / 2);
      ctx.rotate(this.currentTiltX * 0.3);
      ctx.translate(-w / 2, -h / 2);

      var shiftX = this.currentTiltX * 18;
      var shiftY = (this.currentTiltY * 12) + breathOffset;

      ctx.drawImage(this.image, shiftX, shiftY, w, h);

      if (this.options.enableBlink && this.isBlinking) {
        var blinkFactor = this.blinkProgress <= 1.0 ? this.blinkProgress : 2.0 - this.blinkProgress;
        if (blinkFactor > 0.05) {
          var eyeRadiusX = w * 0.055;
          var eyeRadiusY = (h * 0.032) * blinkFactor;

          var leftEyeX = (w * this.faceMesh.leftEye.x) + shiftX;
          var leftEyeY = (h * this.faceMesh.leftEye.y) + shiftY;
          var rightEyeX = (w * this.faceMesh.rightEye.x) + shiftX;
          var rightEyeY = (h * this.faceMesh.rightEye.y) + shiftY;

          ctx.fillStyle = 'rgba(40, 25, 20, 0.9)';

          ctx.beginPath();
          ctx.ellipse(leftEyeX, leftEyeY, eyeRadiusX, eyeRadiusY, 0, 0, Math.PI * 2);
          ctx.fill();

          ctx.beginPath();
          ctx.ellipse(rightEyeX, rightEyeY, eyeRadiusX, eyeRadiusY, 0, 0, Math.PI * 2);
          ctx.fill();
        }
      }

      var vignette = ctx.createRadialGradient(w / 2, h / 2, w * 0.35, w / 2, h / 2, w * 0.75);
      vignette.addColorStop(0, 'transparent');
      vignette.addColorStop(1, 'rgba(25, 15, 10, 0.45)');
      ctx.fillStyle = vignette;
      ctx.fillRect(0, 0, w, h);

      ctx.restore();
    }

    drawStatic() {
      if (!this.image) return;
      this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
      this.ctx.drawImage(this.image, 0, 0, this.canvas.width, this.canvas.height);
    }
  }

  global.LivingHeirloomAnimator = LivingHeirloomAnimator;
})(typeof window !== 'undefined' ? window : globalThis);
