/**
 * CanvasPhysicsEngine & Social Mini-Games Suite
 * 
 * Engineered by the Advanced Technology Group (ATG) under Olutobi (Deloitte),
 * Victor (CTO), Kaelen Vance (Game Systems Architect), and Maya Lindqvist (Canvas Specialist).
 * 
 * High-performance, zero-npm, hardware-accelerated 2D Verlet physics and interactive
 * social mini-games for PartyPlatform, FamilyPlatform, and iDecideApp.
 * 
 * Performance budget: < 45KB, 60fps, touch targets >= 44px (Fitts's Law).
 */
(function(global) {
  'use strict';

  // --- CORE VERLET PHYSICS ENGINE ---
  class CanvasPhysicsEngine {
    constructor(canvas, options = {}) {
      this.canvas = typeof canvas === 'string' ? document.getElementById(canvas) : canvas;
      if (!this.canvas) {
        throw new Error('[CanvasPhysicsEngine] Canvas element not found');
      }
      this.ctx = this.canvas.getContext('2d');
      this.width = options.width || this.canvas.width || 600;
      this.height = options.height || this.canvas.height || 400;
      this.canvas.width = this.width;
      this.canvas.height = this.height;

      this.gravity = options.gravity !== undefined ? options.gravity : { x: 0, y: 0.25 };
      this.friction = options.friction || 0.99;
      this.restitution = options.restitution || 0.75; // Bounciness

      this.bodies = [];
      this.staticColliders = [];
      this.particles = [];
      this.draggedBody = null;
      this.dragOffset = { x: 0, y: 0 };
      this.isRunning = false;
      this.animFrameId = null;

      this._initInteractions();
    }

    _initInteractions() {
      const getPos = (e) => {
        const rect = this.canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        const scaleX = this.width / rect.width;
        const scaleY = this.height / rect.height;
        return {
          x: (clientX - rect.left) * scaleX,
          y: (clientY - rect.top) * scaleY
        };
      };

      const onStart = (e) => {
        const pos = getPos(e);
        // Find top draggable body under pointer (ensuring >= 44px hit-box)
        for (let i = this.bodies.length - 1; i >= 0; i--) {
          const b = this.bodies[i];
          if (!b.isDraggable) continue;
          const hitRadius = Math.max(b.radius || 22, 22);
          const dx = pos.x - b.x;
          const dy = pos.y - b.y;
          if (dx * dx + dy * dy <= hitRadius * hitRadius) {
            this.draggedBody = b;
            b.isBeingDragged = true;
            this.dragOffset = { x: dx, y: dy };
            if (e.cancelable) e.preventDefault();
            break;
          }
        }
      };

      const onMove = (e) => {
        if (!this.draggedBody) return;
        const pos = getPos(e);
        this.draggedBody.x = pos.x - this.dragOffset.x;
        this.draggedBody.y = pos.y - this.dragOffset.y;
        this.draggedBody.oldX = this.draggedBody.x;
        this.draggedBody.oldY = this.draggedBody.y;
        if (e.cancelable) e.preventDefault();
      };

      const onEnd = () => {
        if (this.draggedBody) {
          this.draggedBody.isBeingDragged = false;
          if (this.draggedBody.onRelease) {
            this.draggedBody.onRelease(this.draggedBody);
          }
          this.draggedBody = null;
        }
      };

      this.canvas.addEventListener('mousedown', onStart);
      window.addEventListener('mousemove', onMove);
      window.addEventListener('mouseup', onEnd);

      this.canvas.addEventListener('touchstart', onStart, { passive: false });
      window.addEventListener('touchmove', onMove, { passive: false });
      window.addEventListener('touchend', onEnd);
    }

    addCircle(options = {}) {
      if (this.bodies.length >= 200) {
        this.bodies.shift();
      }
      const body = {
        id: options.id || 'circle_' + Math.random().toString(36).substr(2, 6),
        type: 'circle',
        x: options.x || 100,
        y: options.y || 100,
        oldX: options.x || 100,
        oldY: options.y || 100,
        radius: options.radius || 20,
        mass: options.mass || 1,
        color: options.color || '#3b82f6',
        label: options.label || '',
        emoji: options.emoji || '',
        isDraggable: options.isDraggable !== false,
        isBeingDragged: false,
        restitution: options.restitution !== undefined ? options.restitution : this.restitution,
        onCollision: options.onCollision || null,
        onRelease: options.onRelease || null,
      };
      if (options.vx || options.vy) {
        body.oldX -= options.vx || 0;
        body.oldY -= options.vy || 0;
      }
      this.bodies.push(body);
      return body;
    }

    addStaticPeg(x, y, radius = 8, color = '#64748b') {
      const peg = { x, y, radius, color, isStatic: true };
      this.staticColliders.push(peg);
      return peg;
    }

    addParticleBurst(x, y, count = 25, colors = ['#f59e0b', '#ec4899', '#3b82f6', '#10b981']) {
      const safeCount = Math.min(Math.max(1, count), 60);
      for (let i = 0; i < safeCount; i++) {
        if (this.particles.length >= 500) {
          this.particles.shift();
        }
        const angle = Math.random() * Math.PI * 2;
        const speed = Math.random() * 6 + 2;
        this.particles.push({
          x,
          y,
          vx: Math.cos(angle) * speed,
          vy: Math.sin(angle) * speed - 2,
          radius: Math.random() * 3 + 2,
          color: colors[Math.floor(Math.random() * colors.length)],
          alpha: 1.0,
          decay: Math.random() * 0.02 + 0.015,
        });
      }
    }

    step() {
      // 1. Verlet Integration for mobile bodies
      for (let i = 0; i < this.bodies.length; i++) {
        const b = this.bodies[i];
        if (b.isBeingDragged) continue;

        const vx = (b.x - b.oldX) * this.friction;
        const vy = (b.y - b.oldY) * this.friction;

        b.oldX = b.x;
        b.oldY = b.y;

        b.x += vx + this.gravity.x;
        b.y += vy + this.gravity.y;

        // Boundary constraints
        if (b.x - b.radius < 0) {
          b.x = b.radius;
          b.oldX = b.x + vx * b.restitution;
        } else if (b.x + b.radius > this.width) {
          b.x = this.width - b.radius;
          b.oldX = b.x + vx * b.restitution;
        }

        if (b.y - b.radius < 0) {
          b.y = b.radius;
          b.oldY = b.y + vy * b.restitution;
        } else if (b.y + b.radius > this.height) {
          b.y = this.height - b.radius;
          b.oldY = b.y + vy * b.restitution;
        }
      }

      // 2. Circle-to-Circle Collisions (O(n^2) optimized)
      for (let i = 0; i < this.bodies.length; i++) {
        const b1 = this.bodies[i];
        for (let j = i + 1; j < this.bodies.length; j++) {
          const b2 = this.bodies[j];
          const dx = b2.x - b1.x;
          const dy = b2.y - b1.y;
          const distSq = dx * dx + dy * dy;
          const minDist = b1.radius + b2.radius;

          if (distSq < minDist * minDist && distSq > 0) {
            const dist = Math.sqrt(distSq);
            const overlap = (minDist - dist) * 0.5;
            const nx = dx / dist;
            const ny = dy / dist;

            if (!b1.isBeingDragged) {
              b1.x -= nx * overlap;
              b1.y -= ny * overlap;
            }
            if (!b2.isBeingDragged) {
              b2.x += nx * overlap;
              b2.y += ny * overlap;
            }

            if (b1.onCollision) b1.onCollision(b2);
            if (b2.onCollision) b2.onCollision(b1);
          }
        }
      }

      // 3. Static Collider Collisions (Pegs / Obstacles)
      for (let i = 0; i < this.bodies.length; i++) {
        const b = this.bodies[i];
        if (b.isBeingDragged) continue;

        for (let j = 0; j < this.staticColliders.length; j++) {
          const peg = this.staticColliders[j];
          const dx = b.x - peg.x;
          const dy = b.y - peg.y;
          const distSq = dx * dx + dy * dy;
          const minDist = b.radius + peg.radius;

          if (distSq < minDist * minDist && distSq > 0) {
            const dist = Math.sqrt(distSq);
            const nx = dx / dist;
            const ny = dy / dist;
            const overlap = minDist - dist;

            b.x += nx * overlap;
            b.y += ny * overlap;

            // Bounce impulse
            const vx = b.x - b.oldX;
            const vy = b.y - b.oldY;
            b.oldX = b.x + vx * b.restitution * 0.8;
            b.oldY = b.y + vy * b.restitution * 0.8;

            if (b.onCollision) b.onCollision(peg);
          }
        }
      }

      // 4. Particle Step
      for (let i = this.particles.length - 1; i >= 0; i--) {
        const p = this.particles[i];
        p.x += p.vx;
        p.y += p.vy;
        p.vy += 0.15; // particle gravity
        p.alpha -= p.decay;
        if (p.alpha <= 0) {
          this.particles.splice(i, 1);
        }
      }
    }

    render() {
      this.ctx.clearRect(0, 0, this.width, this.height);

      // Render Static Colliders (Pegs)
      for (const peg of this.staticColliders) {
        this.ctx.beginPath();
        this.ctx.arc(peg.x, peg.y, peg.radius, 0, Math.PI * 2);
        this.ctx.fillStyle = peg.color;
        this.ctx.fill();
        this.ctx.strokeStyle = '#475569';
        this.ctx.lineWidth = 2;
        this.ctx.stroke();
      }

      // Render Dynamic Bodies
      for (const b of this.bodies) {
        this.ctx.save();
        this.ctx.beginPath();
        this.ctx.arc(b.x, b.y, b.radius, 0, Math.PI * 2);
        this.ctx.fillStyle = b.color;
        this.ctx.fill();
        this.ctx.lineWidth = b.isBeingDragged ? 4 : 2;
        this.ctx.strokeStyle = b.isBeingDragged ? '#fbbf24' : '#1e293b';
        this.ctx.stroke();

        // Render emoji or label
        if (b.emoji) {
          this.ctx.font = `${Math.floor(b.radius * 1.1)}px sans-serif`;
          this.ctx.textAlign = 'center';
          this.ctx.textBaseline = 'middle';
          this.ctx.fillText(b.emoji, b.x, b.y);
        } else if (b.label) {
          this.ctx.font = '12px system-ui, -apple-system, sans-serif';
          this.ctx.fillStyle = '#ffffff';
          this.ctx.textAlign = 'center';
          this.ctx.textBaseline = 'middle';
          this.ctx.fillText(b.label, b.x, b.y);
        }
        this.ctx.restore();
      }

      // Render Particles (Confetti)
      for (const p of this.particles) {
        this.ctx.save();
        this.ctx.globalAlpha = p.alpha;
        this.ctx.beginPath();
        this.ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
        this.ctx.fillStyle = p.color;
        this.ctx.fill();
        this.ctx.restore();
      }
    }

    start() {
      if (this.isRunning) return;
      this.isRunning = true;
      const loop = () => {
        if (!this.isRunning) return;
        this.step();
        this.render();
        this.animFrameId = requestAnimationFrame(loop);
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
  }

  // --- GAME 1: PARTYPLATFORM "PARTYPULSE AUX ROULETTE" ---
  class PartyPulseAuxRoulette {
    constructor(canvas, options = {}) {
      this.canvas = typeof canvas === 'string' ? document.getElementById(canvas) : canvas;
      this.ctx = this.canvas.getContext('2d');
      this.items = options.items || ['Afrobeats', 'Hip Hop', '90s R&B', 'Amapiano', 'Pop Anthems', 'Reggae'];
      this.colors = ['#ec4899', '#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#ef4444'];
      this.angle = 0;
      this.velocity = 0;
      this.isSpinning = false;
      this.onSelected = options.onSelected || null;
      this.size = Math.min(this.canvas.width, this.canvas.height);

      this._initInteraction();
      this.render();
    }

    _initInteraction() {
      this.canvas.addEventListener('click', () => {
        if (!this.isSpinning) {
          this.spin();
        }
      });
    }

    spin(force = null) {
      this.isSpinning = true;
      this.velocity = force || (Math.random() * 0.35 + 0.45);
      const tick = () => {
        this.angle += this.velocity;
        this.velocity *= 0.985; // Natural friction deceleration
        this.render();

        if (this.velocity > 0.002) {
          requestAnimationFrame(tick);
        } else {
          this.isSpinning = false;
          this.velocity = 0;
          const sliceAngle = (Math.PI * 2) / this.items.length;
          // Pointer is at the top (angle 3*PI/2)
          const normalized = (Math.PI * 2 - (this.angle % (Math.PI * 2))) % (Math.PI * 2);
          const index = Math.floor(normalized / sliceAngle) % this.items.length;
          if (this.onSelected) {
            this.onSelected(this.items[index]);
          }
        }
      };
      requestAnimationFrame(tick);
    }

    render() {
      const cx = this.canvas.width / 2;
      const cy = this.canvas.height / 2;
      const radius = (this.size / 2) - 15;
      const sliceAngle = (Math.PI * 2) / this.items.length;

      this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

      // Draw Segments
      for (let i = 0; i < this.items.length; i++) {
        const start = this.angle + i * sliceAngle;
        const end = start + sliceAngle;
        this.ctx.beginPath();
        this.ctx.moveTo(cx, cy);
        this.ctx.arc(cx, cy, radius, start, end);
        this.ctx.fillStyle = this.colors[i % this.colors.length];
        this.ctx.fill();
        this.ctx.lineWidth = 2;
        this.ctx.strokeStyle = '#ffffff';
        this.ctx.stroke();

        // Label
        this.ctx.save();
        this.ctx.translate(cx, cy);
        this.ctx.rotate(start + sliceAngle / 2);
        this.ctx.textAlign = 'right';
        this.ctx.fillStyle = '#ffffff';
        this.ctx.font = 'bold 14px system-ui, sans-serif';
        this.ctx.fillText(this.items[i], radius - 20, 5);
        this.ctx.restore();
      }

      // Centre Hub
      this.ctx.beginPath();
      this.ctx.arc(cx, cy, 24, 0, Math.PI * 2);
      this.ctx.fillStyle = '#0f172a';
      this.ctx.fill();
      this.ctx.lineWidth = 3;
      this.ctx.strokeStyle = '#fbbf24';
      this.ctx.stroke();

      // Flapper Indicator Pin at Top
      this.ctx.beginPath();
      this.ctx.moveTo(cx, cy - radius - 8);
      this.ctx.lineTo(cx - 10, cy - radius + 12);
      this.ctx.lineTo(cx + 10, cy - radius + 12);
      this.ctx.closePath();
      this.ctx.fillStyle = '#fbbf24';
      this.ctx.fill();
    }
  }

  // --- GAME 2: FAMILYPLATFORM "KINSHIP QUEST PINBALL" ---
  class KinshipQuestPinball {
    constructor(canvas, options = {}) {
      this.physics = new CanvasPhysicsEngine(canvas, {
        gravity: { x: 0, y: 0.35 },
        friction: 0.985,
        restitution: 0.8
      });
      this.onScore = options.onScore || null;
      this._buildField();
      this.physics.start();
    }

    _buildField() {
      const rows = 4;
      const cols = 5;
      const spacingX = this.physics.width / (cols + 1);
      const spacingY = 50;

      // Add Peg Matrix
      for (let r = 0; r < rows; r++) {
        const offset = (r % 2 === 0) ? 0 : spacingX * 0.5;
        for (let c = 0; c < cols; c++) {
          const x = spacingX * (c + 1) + offset - (r % 2 === 0 ? 0 : spacingX * 0.25);
          const y = 80 + r * spacingY;
          if (x > 20 && x < this.physics.width - 20) {
            this.physics.addStaticPeg(x, y, 7, '#38bdf8');
          }
        }
      }
    }

    dropChoreBall(choreName, emoji = '⭐') {
      const startX = this.physics.width / 2 + (Math.random() * 40 - 20);
      const ball = this.physics.addCircle({
        x: startX,
        y: 25,
        radius: 18,
        emoji: emoji,
        color: '#f43f5e',
        onCollision: (other) => {
          if (other.isStatic) {
            this.physics.addParticleBurst(ball.x, ball.y, 4, ['#facc15', '#38bdf8']);
          }
        }
      });

      // Check bottom arrival with bounded tick limit to prevent interval leaks
      let ticks = 0;
      const checker = setInterval(() => {
        ticks++;
        if (!this.physics.isRunning || ticks > 150) {
          clearInterval(checker);
          return;
        }
        if (ball.y >= this.physics.height - 25) {
          clearInterval(checker);
          this.physics.addParticleBurst(ball.x, ball.y, 30);
          if (this.onScore) {
            const bucket = Math.floor((ball.x / this.physics.width) * 3) + 1;
            this.onScore({ chore: choreName, points: bucket * 50 });
          }
        }
      }, 100);
    }
  }

  // --- GAME 3: IDECIDE "DILEMMA SLINGSHOT & CHOICE ARENA" ---
  class DilemmaSlingshotArena {
    constructor(canvas, options = {}) {
      this.physics = new CanvasPhysicsEngine(canvas, {
        gravity: { x: 0, y: 0.05 },
        friction: 0.992,
        restitution: 0.85
      });
      this.wells = options.wells || [
        { label: 'High Reward', x: this.physics.width * 0.25, y: this.physics.height * 0.3, weight: 1.2, color: '#10b981' },
        { label: 'Low Risk', x: this.physics.width * 0.75, y: this.physics.height * 0.3, weight: 1.0, color: '#3b82f6' },
        { label: 'Fast Speed', x: this.physics.width * 0.5, y: this.physics.height * 0.75, weight: 0.8, color: '#f59e0b' }
      ];
      this.onEquilibrium = options.onEquilibrium || null;
      this._initArena();
      this.physics.start();
    }

    _initArena() {
      // Add custom rendering for gravitational decision wells
      const originalRender = this.physics.render.bind(this.physics);
      this.physics.render = () => {
        originalRender();
        const ctx = this.physics.ctx;

        // Draw Gravitational Decision Wells
        for (const well of this.wells) {
          ctx.save();
          ctx.beginPath();
          ctx.arc(well.x, well.y, 38, 0, Math.PI * 2);
          ctx.fillStyle = well.color + '22';
          ctx.fill();
          ctx.strokeStyle = well.color;
          ctx.lineWidth = 2;
          ctx.setLineDash([4, 4]);
          ctx.stroke();

          ctx.font = 'bold 12px sans-serif';
          ctx.fillStyle = well.color;
          ctx.textAlign = 'center';
          ctx.fillText(well.label, well.x, well.y + 4);
          ctx.restore();
        }
      };

      // Apply gravitational attraction from wells during physics step (zero leaked intervals)
      const originalStep = this.physics.step.bind(this.physics);
      this.physics.step = () => {
        for (const b of this.physics.bodies) {
          if (b.isBeingDragged) continue;
          for (const well of this.wells) {
            const dx = well.x - b.x;
            const dy = well.y - b.y;
            const dist = Math.sqrt(dx * dx + dy * dy);
            if (dist > 15 && dist < 250) {
              const force = (well.weight * 0.45) / (dist * 0.05);
              b.x += (dx / dist) * force;
              b.y += (dy / dist) * force;
            }
          }
        }
        originalStep();
      };
    }

    addDecisionOption(name, emoji = '🎯') {
      return this.physics.addCircle({
        x: this.physics.width / 2 + (Math.random() * 60 - 30),
        y: this.physics.height / 2 + (Math.random() * 60 - 30),
        radius: 26,
        emoji: emoji,
        label: name,
        color: '#6366f1',
        isDraggable: true,
        onRelease: (body) => {
          this.physics.addParticleBurst(body.x, body.y, 15);
        }
      });
    }
  }

  // Export module globally
  global.CanvasPhysicsEngine = CanvasPhysicsEngine;
  global.PartyPulseAuxRoulette = PartyPulseAuxRoulette;
  global.KinshipQuestPinball = KinshipQuestPinball;
  global.DilemmaSlingshotArena = DilemmaSlingshotArena;

})(typeof window !== 'undefined' ? window : this);
