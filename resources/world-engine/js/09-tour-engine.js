/* ==========================================================================
 * ATG World Engine — 09 Guided Tour Engine (WE.Tour)
 * Zero-dependency, CSP-safe interactive onboarding & feature discovery engine.
 * Features:
 *   • Subtle first-visit welcome pill (never hijacks the screen on load)
 *   • Always-available "❓ Tour" header menu in .wes-top with room/Grand Tour picker
 *   • App-context tailoring via studio.cfg.brand.name & studio.cfg.app
 *   • Room-aware tours (Explainer, Posters, Video & Photo Lab) + Full Grand Tour
 *   • Interactive "Try It" micro-demos on key steps across all 3 rooms
 *   • Automatic room & inspector-tab switching before spotlighting controls
 *   • Viewport-safe floating popover (auto-flips & clamps on desktop & mobile)
 *   • Keyboard navigation (ArrowRight, ArrowLeft, Enter, Escape) & 44px targets
 *   • British English (en-GB) copy + localStorage persistence (we:tour:seen:<app>)
 * ========================================================================== */
(function (root) {
  'use strict';
  const WE = (root.WorldEngine = root.WorldEngine || {});

  const APP_EXAMPLES = {
    partyplatform: 'party invitations, event countdowns & celebration reels',
    familyplatform: 'family reunion flyers, milestone tributes & memory reels',
    idecideapp: 'group poll promos, event pitches & decision explainers',
    iaccountapp: 'personal finance tips, savings goals & monthly snapshots',
    tenantscore: 'tenant readiness guides, rental passports & property flyers',
    scoretenant: 'tenant readiness guides, rental passports & property flyers',
    execmindapp: 'executive briefings, strategy updates & quarterly recaps',
    loaneasyfinance: 'clear finance explainers, rate cards & borrower guides',
    bcp: 'care team updates, training explainers & recruitment posters',
  };

  const ROOM_LABELS = {
    explainer: '🎬 Explainer videos',
    poster: '🖼️ Posters & flyers',
    nle: '🎞️ Video & Photo Lab',
  };

  const getContext = (studio) => {
    const cfg = (studio && studio.cfg) || {};
    const appKey = String(cfg.app || 'app').toLowerCase();
    const brandName = (cfg.brand && cfg.brand.name) ? String(cfg.brand.name) : 'your brand';
    const example = APP_EXAMPLES[appKey] || 'branded explainers, flyers & social reels';
    return { appKey, brandName, example };
  };

  const buildTours = (ctx) => ({
    explainer: {
      title: 'Explainer Videos Tour',
      steps: [
        {
          room: 'explainer',
          selector: '.wes-stage',
          title: `Live 60fps Stage for ${ctx.brandName}`,
          body: `Preview animated explainers for ${ctx.example} in real time. Switch between 16:9 widescreen, 9:16 vertical, and 1:1 square formats whenever you like.`,
          tryLabel: '▶ Try It: Play / Pause Preview',
          tryIt: (el, studio) => {
            const btn = el.querySelector('[data-act="play"]');
            if (btn) btn.click();
            if (WE.Audio && WE.Audio.sfx) {
              try { WE.Audio.ensure(); WE.Audio.sfx('pop'); } catch (e) { /* audio optional */ }
            }
            return 'Toggled live stage playback.';
          },
        },
        {
          room: 'explainer',
          selector: '.wes-timeline',
          title: 'Scene Storyboard & Drag Reorder',
          body: 'Click any scene card to edit its headline and narration, drag cards to reorder your story, or tap ＋ to insert a new scene.',
        },
        {
          room: 'explainer',
          selector: '.wes-side',
          tab: 'sound',
          title: 'British Voices, Music & Sound Effects',
          body: `Pick a British narrator, adjust pitch and pace, or record your own voice per scene so ${ctx.brandName} sounds unmistakable.`,
          tryLabel: '🔊 Try It: Hear Voice & SFX',
          tryIt: (el) => {
            if (WE.Audio && WE.Audio.sfx) {
              try { WE.Audio.ensure(); WE.Audio.sfx('tada'); } catch (e) { /* audio optional */ }
            }
            const speakBtn = el.querySelector('[data-act="speakscene"]');
            if (speakBtn) speakBtn.click();
            return 'Played sound effect and scene narration.';
          },
        },
        {
          room: 'explainer',
          selector: '.wes-side',
          tab: 'dir',
          title: 'Director Chat — Instant Edits',
          body: 'Type plain-English requests or tap a quick suggestion chip to restyle scenes, change tone, or switch to vertical format in one click.',
          tryLabel: '✨ Try It: Run a Quick Suggestion',
          tryIt: (el) => {
            const chip = el.querySelector('.wes-chip');
            if (chip) {
              chip.click();
              return `Applied "${chip.textContent.trim()}".`;
            }
            return 'Director chat is ready for your prompt.';
          },
        },
        {
          room: 'explainer',
          selector: '.wes-stagebar',
          title: 'Storyboards, Undo & Zero-Server Export',
          body: 'Switch storyboards, undo or redo changes, and open the Export tab to save WebM/MP4 videos, SRT/VTT subtitles, or scene stills straight from your browser.',
        },
      ],
    },
    poster: {
      title: 'Posters & Flyers Tour',
      steps: [
        {
          room: 'poster',
          selector: '.wes-gallery',
          title: `18+ Templates for ${ctx.brandName}`,
          body: `Browse art-directed layouts ideal for ${ctx.example}. Long headlines auto-fit cleanly without awkward mid-word clipping.`,
          tryLabel: '🎲 Try It: Preview Surprise Template',
          tryIt: (el) => {
            const btn = el.querySelector('[data-act="surprise"]');
            if (btn) btn.click();
            return 'Generated a fresh template & palette combination.';
          },
        },
        {
          room: 'poster',
          selector: '.wes-poster-stage',
          title: 'Live Motion Poster Stage',
          body: 'Every design animates smoothly as a looping motion poster or exports as a crisp high-resolution PNG or A4 print.',
        },
        {
          room: 'poster',
          selector: '.wes-side',
          tab: 'style',
          title: 'Palettes, Photos & Backdrop Fade',
          body: `Apply curated colour palettes or ${ctx.brandName} brand colours, upload a photo with smooth backdrop transparency, and add stickers.`,
          tryLabel: '🎨 Try It: Cycle Colour Palette',
          tryIt: (el) => {
            const pals = Array.from(el.querySelectorAll('.wes-pal'));
            if (!pals.length) return 'Open Style & photo to pick a palette.';
            const curIdx = pals.findIndex((p) => p.classList.contains('on'));
            const nextPal = pals[(curIdx + 1) % pals.length];
            nextPal.click();
            return 'Switched to the next colour palette.';
          },
        },
        {
          room: 'poster',
          selector: '.wes-side',
          tab: 'content',
          title: 'Words, Samples & Scannable QR Codes',
          body: 'Posters start clean by default—fill from a sample or type your own title, date, venue, and link to generate a live QR code.',
        },
      ],
    },
    nle: {
      title: 'Video & Photo Lab Tour',
      steps: [
        {
          room: 'nle',
          selector: '.wes-nle-aspect',
          title: `Canvas Formats for ${ctx.brandName}`,
          body: `Switch seamlessly between 9:16 Reels/TikTok, 16:9 YouTube, 1:1 Feed, and 4:5 Instagram Portrait for ${ctx.example}.`,
          tryLabel: '📐 Try It: Switch Aspect Ratio',
          tryIt: (el) => {
            const sel = el.querySelector('.wes-nle-aspect');
            if (!sel || !sel.options.length) return 'Aspect ratio selector ready.';
            sel.selectedIndex = (sel.selectedIndex + 1) % sel.options.length;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            return `Switched canvas to ${sel.options[sel.selectedIndex].textContent}.`;
          },
        },
        {
          room: 'nle',
          selector: '[data-ntab="media"]',
          tab: 'media',
          title: 'Private Browser-Local Media Bin',
          body: 'Import MP4, WebM, PNG, JPG, WebP, MP3, or WAV files directly in your browser—100% private with zero server upload.',
        },
        {
          room: 'nle',
          selector: '.wes-nle-timeline-wrap',
          title: 'Multi-Track NLE Timeline',
          body: 'Arrange clips across V1 (Main), V2 (Overlay/PiP), T1 (Text & Captions), and A1 (Audio). Drag clips or click the ruler to scrub.',
        },
        {
          room: 'nle',
          selector: '[data-act="split"]',
          title: 'Razor Split, Duplicate & 60-Step Undo',
          body: 'Move the playhead inside any clip and tap Split to cut cleanly. Every edit supports 60-step Undo and Redo.',
        },
        {
          room: 'nle',
          selector: '[data-ntab="transform"]',
          tab: 'transform',
          title: 'Keyframes, Speed, Masks & Transitions',
          body: 'Animate position, scale, rotation, and opacity with Keyframe diamonds, apply speed ramps (0.25x–4x), blend modes, and mask shapes.',
          tryLabel: '◆ Try It: Add Sample Keyframe',
          tryIt: (el) => {
            let clip = el.querySelector('.wes-nle-clip');
            if (!clip) {
              const textTab = el.querySelector('[data-ntab="text"]');
              if (textTab) textTab.click();
              const addText = el.querySelector('[data-add-text]');
              if (addText) addText.click();
            } else if (!el.querySelector('.wes-nle-clip.on')) {
              clip.click();
            }
            const tTab = el.querySelector('[data-ntab="transform"]');
            if (tTab) tTab.click();
            const kfBtn = el.querySelector('[data-kf="add"]');
            if (kfBtn) {
              kfBtn.click();
              return 'Added a gold keyframe diamond to the timeline.';
            }
            return 'Select or add a clip to place keyframes.';
          },
        },
        {
          room: 'nle',
          selector: '[data-ntab="colour"]',
          tab: 'colour',
          title: 'Colour Grading, LUTs & Chroma Key',
          body: 'Apply cinematic LUT presets, grade brightness, contrast, warmth, and vignette, key out green screens, or auto-remove photo backdrops.',
          tryLabel: '🎞️ Try It: Preview Heritage Gold LUT',
          tryIt: (el) => {
            let clip = el.querySelector('.wes-nle-clip');
            if (!clip) {
              const textTab = el.querySelector('[data-ntab="text"]');
              if (textTab) textTab.click();
              const addText = el.querySelector('[data-add-text]');
              if (addText) addText.click();
            } else if (!el.querySelector('.wes-nle-clip.on')) {
              clip.click();
            }
            const cTab = el.querySelector('[data-ntab="colour"]');
            if (cTab) cTab.click();
            const lutSel = el.querySelector('[data-lut]');
            if (lutSel) {
              lutSel.value = lutSel.value === 'heritage_gold' ? 'cinema_teal_orange' : 'heritage_gold';
              lutSel.dispatchEvent(new Event('change', { bubbles: true }));
              const lbl = lutSel.options[lutSel.selectedIndex] ? lutSel.options[lutSel.selectedIndex].textContent : lutSel.value;
              return `Applied "${lbl}" colour LUT.`;
            }
            return 'Switched to Colour & FX tab.';
          },
        },
        {
          room: 'nle',
          selector: '[data-ntab="text"]',
          tab: 'text',
          title: 'Captions, SRT Subtitles & Stickers',
          body: 'Add animated captions, paste SRT subtitle files directly onto T1, and drop vector stickers onto V2.',
        },
        {
          room: 'nle',
          selector: '[data-act="export-video"]',
          title: 'Export Video, Photo or NLE JSON',
          body: 'Render your full multi-track edit as WebM/MP4 video, save a high-res PNG frame, or export the NLE JSON project.',
        },
      ],
    },
  });

  const buildGrandTour = (ctx) => {
    const tours = buildTours(ctx);
    return [
      ...tours.explainer.steps.slice(0, 3),
      ...tours.poster.steps.slice(0, 3),
      ...tours.nle.steps.slice(0, 5),
    ];
  };

  // Backwards-compatible static snapshot for inspection
  const TOURS = buildTours({ appKey: 'app', brandName: 'your brand', example: 'branded explainers, flyers & social reels' });

  const mount = (studio) => {
    const el = studio.el;
    const esc = WE.esc || ((s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])));
    const ctx = getContext(studio);
    const tours = buildTours(ctx);
    const KEY_SEEN = 'we:tour:seen:' + ((studio.cfg && studio.cfg.app) || 'app');

    let overlay = null;
    let activeSteps = [];
    let activeMode = 'explainer';
    let idx = 0;
    let menuEl = null;
    let pillEl = null;
    let lastFocused = null;

    const currentRoom = () => {
      const r = el.querySelector('.wes-room.on');
      return r ? r.dataset.room : 'explainer';
    };

    const hasSeen = () => {
      try { return localStorage.getItem(KEY_SEEN) === '1'; } catch (e) { return true; }
    };

    const markSeen = () => {
      try { localStorage.setItem(KEY_SEEN, '1'); } catch (e) { /* storage guard */ }
    };

    const dismissPill = (persist = true) => {
      if (persist) markSeen();
      if (pillEl) {
        pillEl.remove();
        pillEl = null;
      }
    };

    const closeMenu = () => {
      const btn = el.querySelector('[data-tour-menu]');
      if (btn) btn.setAttribute('aria-expanded', 'false');
      if (menuEl) {
        menuEl.remove();
        menuEl = null;
      }
      document.removeEventListener('click', onDocClick, true);
      root.removeEventListener('keydown', onMenuKey);
    };

    const onDocClick = (e) => {
      const wrap = el.querySelector('.wes-tour-wrap');
      if (wrap && !wrap.contains(e.target)) closeMenu();
    };

    const onMenuKey = (e) => {
      if (!menuEl) return;
      if (e.key === 'Escape') {
        e.preventDefault();
        closeMenu();
        const btn = el.querySelector('[data-tour-menu]');
        if (btn) btn.focus();
      } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        const items = Array.from(menuEl.querySelectorAll('[data-pick]'));
        if (!items.length) return;
        e.preventDefault();
        const curIdx = items.indexOf(document.activeElement);
        const dir = e.key === 'ArrowDown' ? 1 : -1;
        const nextIdx = curIdx === -1 ? (dir > 0 ? 0 : items.length - 1) : (curIdx + dir + items.length) % items.length;
        items[nextIdx].focus();
      }
    };

    const positionMenu = () => {
      if (!menuEl) return;
      const trigger = el.querySelector('[data-tour-menu]');
      if (!trigger) return;
      const vw = root.innerWidth || document.documentElement.clientWidth || 1024;
      const vh = root.innerHeight || document.documentElement.clientHeight || 768;
      const tr = trigger.getBoundingClientRect();
      const mw = menuEl.offsetWidth || Math.min(300, vw - 24);
      const mh = menuEl.offsetHeight || 280;
      const left = Math.max(12, Math.min(vw - mw - 12, tr.right - mw));
      let top = tr.bottom + 8;
      if (top + mh > vh - 12 && tr.top - mh - 8 >= 12) {
        top = tr.top - mh - 8;
      }
      top = Math.max(12, Math.min(vh - Math.min(mh, vh - 24) - 12, top));
      menuEl.style.left = Math.round(left) + 'px';
      menuEl.style.right = 'auto';
      menuEl.style.top = Math.round(top) + 'px';
    };

    const openMenu = () => {
      if (menuEl) { closeMenu(); return; }
      const wrap = el.querySelector('.wes-tour-wrap');
      const trigger = el.querySelector('[data-tour-menu]');
      if (!wrap || !trigger) return;

      const cur = currentRoom();
      const curLabel = ROOM_LABELS[cur] || 'Current room';

      menuEl = document.createElement('div');
      menuEl.className = 'wes-tour-menu';
      menuEl.setAttribute('role', 'menu');
      menuEl.setAttribute('aria-label', 'Guided studio tours');
      menuEl.innerHTML = `
        <div class="wes-tour-menu-head">Guided Tours · ${esc(ctx.brandName)}</div>
        <button type="button" class="wes-tour-menu-item wes-tour-menu-primary" role="menuitem" data-pick="${esc(cur)}">
          <span>▶ Tour this room</span><small>${esc(curLabel)}</small>
        </button>
        <button type="button" class="wes-tour-menu-item" role="menuitem" data-pick="grand">
          <span>✨ Grand Tour</span><small>All 3 studio rooms</small>
        </button>
        <div class="wes-tour-menu-sep" role="separator"></div>
        <button type="button" class="wes-tour-menu-item" role="menuitem" data-pick="explainer"><span>🎬 Explainer videos</span><small>${tours.explainer.steps.length} steps</small></button>
        <button type="button" class="wes-tour-menu-item" role="menuitem" data-pick="poster"><span>🖼️ Posters &amp; flyers</span><small>${tours.poster.steps.length} steps</small></button>
        <button type="button" class="wes-tour-menu-item" role="menuitem" data-pick="nle"><span>🎞️ Video &amp; Photo Lab</span><small>${tours.nle.steps.length} steps</small></button>
        <div class="wes-tour-menu-foot">Keys: ← → navigate · Esc close</div>
      `;
      wrap.appendChild(menuEl);
      trigger.setAttribute('aria-expanded', 'true');
      positionMenu();

      menuEl.querySelectorAll('[data-pick]').forEach((item) => {
        item.addEventListener('click', () => {
          const mode = item.dataset.pick;
          closeMenu();
          start(mode);
        });
      });

      setTimeout(() => {
        document.addEventListener('click', onDocClick, true);
        root.addEventListener('keydown', onMenuKey);
        const first = menuEl && menuEl.querySelector('[data-pick]');
        if (first) first.focus();
      }, 0);
    };

    const cleanup = () => {
      if (overlay) {
        overlay.remove();
        overlay = null;
      }
      root.removeEventListener('keydown', onKey);
      root.removeEventListener('resize', positionStep);
      root.removeEventListener('scroll', positionStep, true);
    };

    const onKey = (e) => {
      if (!overlay) return;
      if (e.key === 'Escape') { e.preventDefault(); finish(true); }
      else if (e.key === 'ArrowRight') { e.preventDefault(); next(); }
      else if (e.key === 'ArrowLeft') { e.preventDefault(); prev(); }
      else if (e.key === 'Tab') {
        const focusables = Array.from(overlay.querySelectorAll('.wes-tour-card button:not([disabled])')).filter((b) => !b.hidden && !b.closest('[hidden]'));
        if (!focusables.length) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        const ae = document.activeElement;
        if (e.shiftKey) {
          if (ae === first || !overlay.contains(ae)) { e.preventDefault(); last.focus(); }
        } else {
          if (ae === last || !overlay.contains(ae)) { e.preventDefault(); first.focus(); }
        }
      } else if (e.key === 'Enter') {
        const ae = document.activeElement;
        if (ae && ae.closest && ae.closest('.wes-tour-card button') && ae.dataset.tour !== 'next') {
          return; // allow native click on Try It, Back, Close, Grand Tour
        }
        e.preventDefault();
        next();
      }
    };

    const finish = (persist = true) => {
      if (persist) markSeen();
      cleanup();
      dismissPill(persist);
      if (lastFocused && typeof lastFocused.focus === 'function' && lastFocused.isConnected) {
        lastFocused.focus();
      }
      lastFocused = null;
    };

    const positionStep = () => {
      if (!overlay || !activeSteps[idx]) return;
      const step = activeSteps[idx];
      const spot = overlay.querySelector('.wes-tour-spot');
      const card = overlay.querySelector('.wes-tour-card');
      const target = el.querySelector(step.selector);

      const vw = root.innerWidth || document.documentElement.clientWidth || 1024;
      const vh = root.innerHeight || document.documentElement.clientHeight || 768;
      const cw = Math.min(380, Math.max(260, vw - 24));
      card.style.width = cw + 'px';

      const r = target ? target.getBoundingClientRect() : null;
      if (!target || !r || (r.width === 0 && r.height === 0)) {
        spot.style.opacity = '0';
        card.style.top = Math.max(12, Math.round((vh - (card.offsetHeight || 240)) / 2)) + 'px';
        card.style.left = Math.max(12, Math.round((vw - cw) / 2)) + 'px';
        return;
      }

      const pad = 6;
      spot.style.opacity = '1';
      spot.style.top = Math.max(4, Math.round(r.top - pad)) + 'px';
      spot.style.left = Math.max(4, Math.round(r.left - pad)) + 'px';
      spot.style.width = Math.min(vw - 8, Math.max(44, Math.round(r.width + pad * 2))) + 'px';
      spot.style.height = Math.min(vh - 8, Math.max(44, Math.round(r.height + pad * 2))) + 'px';

      const ch = card.offsetHeight || 240;
      let top = r.bottom + 14;
      let left = Math.max(12, Math.min(vw - cw - 12, r.left));
      if (top + ch > vh - 12) {
        top = r.top - ch - 14;
      }
      if (top < 12) {
        top = Math.max(12, Math.min(vh - ch - 12, Math.round((vh - ch) / 2)));
      }
      card.style.top = Math.round(top) + 'px';
      card.style.left = Math.round(left) + 'px';
    };

    const renderStep = () => {
      if (!overlay) return;
      const step = activeSteps[idx];
      if (!step) return finish(true);

      if (step.room) {
        const roomBtn = el.querySelector(`.wes-room[data-room="${step.room}"]`);
        if (roomBtn && !roomBtn.classList.contains('on')) roomBtn.click();
      }
      if (step.tab) {
        const tabBtn = el.querySelector(`[data-ntab="${step.tab}"], [data-t="${step.tab}"], [data-tab="${step.tab}"]`);
        if (tabBtn && !tabBtn.classList.contains('on')) tabBtn.click();
      }

      const target = el.querySelector(step.selector);
      if (target && target.scrollIntoView) {
        target.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
      }

      const pct = Math.round(((idx + 1) / activeSteps.length) * 100);
      overlay.querySelector('.wes-tour-step').textContent = `Step ${idx + 1} of ${activeSteps.length}`;
      const roomBadge = overlay.querySelector('.wes-tour-room-badge');
      roomBadge.textContent = ROOM_LABELS[step.room] || '';
      roomBadge.hidden = !step.room;

      overlay.querySelector('.wes-tour-bar-fill').style.width = pct + '%';
      overlay.querySelector('.wes-tour-title').textContent = step.title;
      overlay.querySelector('.wes-tour-body').textContent = step.body;

      const tryWrap = overlay.querySelector('.wes-tour-try-wrap');
      const tryBtn = overlay.querySelector('[data-tour="try"]');
      const tryStatus = overlay.querySelector('.wes-tour-try-status');
      tryStatus.textContent = '';
      tryStatus.hidden = true;
      if (typeof step.tryIt === 'function') {
        tryWrap.hidden = false;
        tryBtn.textContent = step.tryLabel || '▶ Try It';
        tryBtn.onclick = () => {
          const msg = step.tryIt(el, studio);
          if (msg) {
            tryStatus.textContent = '✓ ' + msg;
            tryStatus.hidden = false;
          }
          requestAnimationFrame(() => setTimeout(positionStep, 40));
        };
      } else {
        tryWrap.hidden = true;
        tryBtn.onclick = null;
      }

      const grandBtn = overlay.querySelector('[data-tour="grand"]');
      grandBtn.hidden = activeMode === 'grand';

      const prevBtn = overlay.querySelector('[data-tour="prev"]');
      const nextBtn = overlay.querySelector('[data-tour="next"]');
      prevBtn.disabled = idx === 0;
      nextBtn.textContent = idx === activeSteps.length - 1 ? 'Finish Tour ✓' : 'Next →';

      requestAnimationFrame(() => setTimeout(positionStep, 70));
    };

    const next = () => {
      if (idx < activeSteps.length - 1) { idx++; renderStep(); }
      else finish(true);
    };
    const prev = () => {
      if (idx > 0) { idx--; renderStep(); }
    };

    const start = (mode = 'auto') => {
      closeMenu();
      dismissPill(false);
      if (!overlay) lastFocused = document.activeElement;
      cleanup();

      let resolved = mode;
      if (resolved === 'auto') resolved = currentRoom();
      activeMode = resolved;
      activeSteps = resolved === 'grand'
        ? buildGrandTour(ctx)
        : ((tours[resolved] && tours[resolved].steps) || tours.explainer.steps);
      idx = 0;

      overlay = document.createElement('div');
      overlay.className = 'wes-tour-overlay';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');
      overlay.setAttribute('aria-label', `${ctx.brandName} Studio Guided Tour`);
      overlay.innerHTML = `
        <div class="wes-tour-spot" aria-hidden="true"></div>
        <div class="wes-tour-card" tabindex="-1">
          <div class="wes-tour-head">
            <span class="wes-tour-step">Step 1 of ${activeSteps.length}</span>
            <span class="wes-tour-room-badge" hidden></span>
            <button type="button" class="wes-btn wes-tour-close" data-tour="close" aria-label="Close tour">✕</button>
          </div>
          <div class="wes-tour-bar"><div class="wes-tour-bar-fill"></div></div>
          <h3 class="wes-tour-title"></h3>
          <p class="wes-tour-body"></p>
          <div class="wes-tour-try-wrap" hidden>
            <button type="button" class="wes-btn wes-tour-try-btn" data-tour="try">▶ Try It</button>
            <div class="wes-tour-try-status" aria-live="polite" hidden></div>
          </div>
          <div class="wes-tour-foot">
            <button type="button" class="wes-btn" data-tour="grand" title="Tour all 3 studio rooms">Grand Tour</button>
            <div class="wes-spacer"></div>
            <button type="button" class="wes-btn" data-tour="prev">← Back</button>
            <button type="button" class="wes-btn wes-primary" data-tour="next">Next →</button>
          </div>
        </div>
      `;
      document.body.appendChild(overlay);
      overlay.querySelector('[data-tour="close"]').addEventListener('click', () => finish(true));
      overlay.querySelector('[data-tour="prev"]').addEventListener('click', prev);
      overlay.querySelector('[data-tour="next"]').addEventListener('click', next);
      overlay.querySelector('[data-tour="grand"]').addEventListener('click', () => start('grand'));

      root.addEventListener('keydown', onKey);
      root.addEventListener('resize', positionStep);
      root.addEventListener('scroll', positionStep, true);
      renderStep();

      const nextBtn = overlay.querySelector('[data-tour="next"]');
      if (nextBtn) nextBtn.focus();
    };

    const showWelcomePill = () => {
      if (hasSeen() || pillEl) return;
      pillEl = document.createElement('aside');
      pillEl.className = 'wes-tour-pill';
      pillEl.setAttribute('aria-label', 'Studio onboarding tour');
      pillEl.innerHTML = `
        <div class="wes-tour-pill-text">
          <b>👋 New to ${esc(ctx.brandName)} Studio?</b>
          <small>Take a quick interactive tour of the creative tools.</small>
        </div>
        <div class="wes-tour-pill-actions">
          <button type="button" class="wes-btn wes-primary" data-pill-start>Start tour</button>
          <button type="button" class="wes-btn" data-pill-grand>Grand Tour</button>
          <button type="button" class="wes-btn" data-pill-dismiss aria-label="Dismiss tour prompt">✕</button>
        </div>
      `;
      el.appendChild(pillEl);
      pillEl.querySelector('[data-pill-start]').addEventListener('click', () => start('auto'));
      pillEl.querySelector('[data-pill-grand]').addEventListener('click', () => start('grand'));
      pillEl.querySelector('[data-pill-dismiss]').addEventListener('click', () => dismissPill(true));
    };

    const trigger = el.querySelector('[data-tour-menu]');
    if (trigger) {
      trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        openMenu();
      });
    }

    showWelcomePill();

    return { start, finish, hasSeen, openMenu, closeMenu, dismissPill, TOURS: tours };
  };

  WE.Tour = { mount, TOURS, buildTours, buildGrandTour };
})(typeof window !== 'undefined' ? window : globalThis);
