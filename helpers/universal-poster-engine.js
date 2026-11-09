/**
 * AtgUniversalPosterEngine
 *
 * Advanced Technology Group (ATG Pod 3 & Pod 4)
 * Sovereign In-House Poster & Event Flyer Synthesis Engine (v3.0 Photorealistic Studio Edition).
 *
 * Combines photorealistic studio backdrops, despilled alpha cutout portraits,
 * overlapping foreground peony botanicals, and multi-plane Google WebFont typography
 * (`Anton`, `Bebas Neue`, `Cinzel`, `Playfair Display`, `Great Vibes`, `Alex Brush`,
 * `Montserrat`, `Oswald`) on HTML5 Canvas 2D with zero external SaaS dependencies.
 *
 * Implements 6 distinct commercial layout archetypes:
 * 1. POOL_SPLIT ('pool_tropical_split')      — Sample #1: Sunday Pool Party
 * 2. NEON_CLASH ('neon_script_clash')        — Sample #2: Tropical Purple Party
 * 3. VIP_EDITORIAL ('vip_black_gold')        — Sample #3: Aaron's VIP Birthday (with Ghost Echo)
 * 4. FLORAL_CHROME ('floral_chrome_overlap') — Sample #4: Happy Birthday Alisa (3D Chrome + Peony Overlap)
 * 5. CLUB_TUNNEL ('kinetic_club_tunnel')     — Sample #5: Friday Party Night (Text-Behind-Subject)
 * 6. SECTOR_GRID ('sector_authority_grid')   — Regulated & Enterprise Apps (BCP, LoanEasy, iAccount, ExecMind, iDecide, TenantScore)
 *
 * Strictly adheres to British English (en-GB).
 */

class AtgUniversalPosterEngine {
  static ASPECT_RATIOS = {
    STORY_9_16: { id: 'story', name: 'Story / Status (9:16)', width: 1080, height: 1920 },
    FEED_1_1: { id: 'feed', name: 'Square Feed (1:1)', width: 1080, height: 1080 },
    PORTRAIT_4_5: { id: 'portrait', name: 'Portrait (4:5)', width: 1080, height: 1350 },
    POSTER_A4: { id: 'poster_a4', name: 'Print Poster (A4)', width: 1240, height: 1754 }
  };

  static ARCHETYPES = {
    POOL_SPLIT: 'pool_tropical_split',
    NEON_CLASH: 'neon_script_clash',
    VIP_EDITORIAL: 'vip_black_gold',
    FLORAL_CHROME: 'floral_chrome_overlap',
    CLUB_TUNNEL: 'kinetic_club_tunnel',
    ACRYLIC_KEEPSAKE_BLOCK: 'acrylic_keepsake_block',
    CLINICAL_SHIFT_SURGE: 'clinical_shift_surge',
    CLINICAL_RECRUITMENT: 'clinical_recruitment_day',
    FINTECH_FACILITY: 'fintech_facility_offer',
    CONSUMER_LOAN_CAMPAIGN: 'consumer_loan_campaign',
    RETAIL_BANKING_FEATURES: 'retail_banking_features',
    TAX_TREASURY_CLINIC: 'tax_statutory_clinic',
    TAX_HEALTH_SCORE: 'tax_health_score_app',
    EXECUTIVE_BOARD_SUMMIT: 'executive_board_summit',
    CIVIC_REFERENDUM: 'civic_referendum_poll',
    TENANT_RENT_PASSPORT: 'tenant_rent_passport',
    TENANT_CREDIT_BOOST: 'tenant_credit_boost_mockup',
    SECTOR_GRID: 'sector_authority_grid'
  };

  static THEMES = {
    PARTY_NEON: 'party_neon',
    PARTY_BLACK_GOLD: 'party_black_gold',
    PARTY_POOL_TROPICAL: 'party_pool_tropical',
    PARTY_PYRO_CLUB: 'party_pyro_club',
    PARTY_FLORAL_CHROME: 'party_floral_chrome',
    PARTY_ACRYLIC_CRYSTAL: 'party_acrylic_crystal',
    FAMILY_HEIRLOOM_GOLD: 'family_heirloom_gold',
    FAMILY_BOTANICAL: 'family_botanical',
    FAMILY_PASTEL_BABY: 'family_pastel_baby',
    EXEC_SAPPHIRE_SUMMIT: 'exec_sapphire_summit',
    BCP_URGENT_SHIFT: 'bcp_urgent_shift',
    BCP_CLINICAL_EXCELLENCE: 'bcp_clinical_excellence',
    FINTECH_SME_CAPITAL: 'fintech_sme_capital',
    LOANEASY_CONSUMER_CHEVRON: 'loaneasy_consumer_chevron',
    LOANEASY_RETAIL_CARD: 'loaneasy_retail_card',
    IACCOUNT_TAX_READINESS: 'iaccount_tax_readiness',
    IACCOUNT_TAX_HEALTH: 'iaccount_tax_health',
    IDECIDE_CIVIC_DEBATE: 'idecide_civic_debate',
    TENANT_PROPERTY_SHOWCASE: 'tenant_property_showcase',
    TENANT_CREDIT_BOOST: 'tenant_credit_boost'
  };

  static THEME_ARCHETYPE_MAP = {
    party_pool_tropical: 'pool_tropical_split',
    party_neon: 'neon_script_clash',
    party_black_gold: 'vip_black_gold',
    party_floral_chrome: 'floral_chrome_overlap',
    party_pyro_club: 'kinetic_club_tunnel',
    party_acrylic_crystal: 'acrylic_keepsake_block',
    family_heirloom_gold: 'vip_black_gold',
    family_botanical: 'floral_chrome_overlap',
    family_pastel_baby: 'pool_tropical_split',
    exec_sapphire_summit: 'executive_board_summit',
    bcp_urgent_shift: 'clinical_shift_surge',
    bcp_clinical_excellence: 'clinical_recruitment_day',
    fintech_sme_capital: 'fintech_facility_offer',
    loaneasy_consumer_chevron: 'consumer_loan_campaign',
    loaneasy_retail_card: 'retail_banking_features',
    iaccount_tax_readiness: 'tax_statutory_clinic',
    iaccount_tax_health: 'tax_health_score_app',
    idecide_civic_debate: 'civic_referendum_poll',
    tenant_property_showcase: 'tenant_rent_passport',
    tenant_credit_boost: 'tenant_credit_boost_mockup'
  };

  /**
   * Built-in photorealistic studio assets served from `/poster-studio?asset=<file>`
   */
  static STUDIO_ASSETS = {
    bg_acrylic_crystal_block: '/poster-studio?asset=bg_acrylic_crystal_block.jpg',
    bg_hollow_acrylic_keepsake: '/poster-studio?asset=bg_hollow_acrylic_keepsake.jpg',
    bg_pool_palm_frame: '/poster-studio?asset=bg_pool_palm_frame.jpg',
    bg_tropical_purple: '/poster-studio?asset=bg_tropical_purple.jpg',
    bg_vip_gold_lounge: '/poster-studio?asset=bg_vip_gold_lounge.jpg',
    bg_crimson_velvet_floral: '/poster-studio?asset=bg_crimson_velvet_floral.jpg',
    bg_club_tunnel: '/poster-studio?asset=bg_club_tunnel.jpg',
    bg_family_golden_jubilee: '/poster-studio?asset=bg_family_golden_jubilee.jpg',
    bg_fintech_sme_emerald: '/poster-studio?asset=bg_fintech_sme_emerald.jpg',
    bg_healthcare_clinical_surge: '/poster-studio?asset=bg_healthcare_clinical_surge.jpg',
    fg_crimson_peonies: '/poster-studio?asset=fg_crimson_peonies.png',
    subject_pool_party: '/poster-studio?asset=subject_pool_party.png',
    subject_vip_man: '/poster-studio?asset=subject_vip_man.png',
    subject_birthday_woman: '/poster-studio?asset=subject_birthday_woman.png',
    subject_club_dj: '/poster-studio?asset=subject_club_dj.png'
    // Removed: bg_consumer_moving_joy / bg_consumer_family_home (real HSBC / Virgin Money adverts)
    // and asset_score_gauge / asset_app_mockup / asset_boost_rocket (Experian app screenshots).
  };

  constructor(options = {}) {
    const ratioKey = (options.aspectRatio || 'story').toLowerCase();
    const ratioSpec = Object.values(AtgUniversalPosterEngine.ASPECT_RATIOS).find(
      (r) => r.id === ratioKey
    ) || AtgUniversalPosterEngine.ASPECT_RATIOS.STORY_9_16;

    this.aspectRatio = ratioSpec.id;
    this.width = ratioSpec.width;
    this.height = ratioSpec.height;
    this.theme = options.theme || AtgUniversalPosterEngine.THEMES.PARTY_BLACK_GOLD;
    this.archetype = options.archetype || AtgUniversalPosterEngine.THEME_ARCHETYPE_MAP[this.theme] || 'vip_black_gold';

    this.canvas = options.canvas || (typeof document !== 'undefined' ? document.createElement('canvas') : null);
    if (this.canvas) {
      this.canvas.width = this.width;
      this.canvas.height = this.height;
      this.ctx = this.canvas.getContext('2d');
    } else {
      this.ctx = null;
    }

    this.backgroundImage = null;
    this.subjectImage = null;
    this.processedSubjectCanvas = null;
    this.qrImage = null;
    this.studioAssetCache = {};

    this.data = {
      tenant: 'partyplatform',
      badge: "YOU'RE INVITED TO",
      kicker: "Aaron's",
      title: 'BIRTHDAY',
      titleSecondary: 'PARTY',
      subtitle: 'All Black Luxury Affair',
      date: 'SAT MAY 07',
      time: '9:00 PM',
      venue: 'Tape London',
      address: '17 Hanover Square, London W1S 1HU',
      dressCode: 'ALL BLACK AFFAIR • WEAR ALL BLACK',
      hosts: 'ESTHER & CYNTHIA',
      musicBy: 'DJ NESSA\nSELECTA IZZY',
      rsvpContact: 'MARCH 31, 2026 • 020 7946 0192',
      perks: ['LIVE MUSIC', 'DRINKS', 'VIP TABLES'],
      footerStrip: 'ENTRY £15 • DOORS OPEN 9PM • FREE PARKING • 2 DRINKS FREE',
      accentMetric: '',
      regulatoryNotice: '',
      qrUrl: 'https://partyplatform.com/rsvp',
      watermarkText: 'FRIDAY',
      photoMode: 'ghost_double',
      showDefaultSubject: true,
      subjectScale: 1.0,
      subjectOffsetX: 0,
      subjectOffsetY: 0,
      ...options.data
    };
    this.strictContent = options.strictContent === true || (options.data && options.data.strictContent === true);
    if (this.strictContent) this._applyStrictContent(options.data || {});
  }

  /**
   * Strict content mode (for posters generated from real/linked events).
   * Any text field the caller did not supply, or supplied empty, renders blank,
   * so built-in sample copy ("SAT MAY 07", "TAPE LONDON"…) can never be exported by mistake.
   * Enable with `new AtgUniversalPosterEngine({ strictContent: true, data })` or `data.strictContent = true`.
   */
  _applyStrictContent(supplied) {
    const TEXT_KEYS = ['badge', 'kicker', 'title', 'titleSecondary', 'subtitle', 'date', 'time', 'venue', 'address', 'dressCode', 'hosts', 'musicBy', 'rsvpContact', 'footerStrip', 'watermarkText', 'accentMetric', 'regulatoryNotice'];
    const base = { ...this.data };
    TEXT_KEYS.forEach((k) => { if (!(k in supplied)) base[k] = ''; });
    if (!('perks' in supplied)) base.perks = [];
    if (!('qrUrl' in supplied)) base.qrUrl = '';
    // A single space is truthy, so legacy `value || 'SAMPLE'` fallbacks render nothing instead of sample copy.
    this.data = new Proxy(base, {
      get(target, key) {
        const v = target[key];
        if (TEXT_KEYS.includes(key) && (v == null || String(v).trim() === '')) return ' ';
        return v;
      },
    });
  }

  /**
   * Preload all built-in studio backdrops, cutouts, and botanical overlays
   * @param {string} [assetBaseUrl='/poster-studio?asset=']
   * @returns {Promise<void>}
   */
  async preloadStudioAssets(assetBaseUrl = '/poster-studio?asset=') {
    if (typeof Image === 'undefined') return;
    const entries = Object.entries(AtgUniversalPosterEngine.STUDIO_ASSETS);
    await Promise.all(
      entries.map(async ([key, defaultUrl]) => {
        if (this.studioAssetCache[key]) return;
        const filename = defaultUrl.split('asset=')[1] || '';
        const url = assetBaseUrl ? `${assetBaseUrl}${filename}` : defaultUrl;
        const img = await this._loadImage(url);
        if (img) {
          this.studioAssetCache[key] = img;
          this.render();
        }
      })
    );
  }

  setAspectRatio(ratioKey) {
    const ratioSpec = Object.values(AtgUniversalPosterEngine.ASPECT_RATIOS).find(
      (r) => r.id === String(ratioKey || 'story').toLowerCase()
    ) || AtgUniversalPosterEngine.ASPECT_RATIOS.STORY_9_16;

    this.aspectRatio = ratioSpec.id;
    this.width = ratioSpec.width;
    this.height = ratioSpec.height;

    if (this.canvas) {
      this.canvas.width = this.width;
      this.canvas.height = this.height;
    }
  }

  setTheme(themeKey, explicitArchetype = null) {
    this.theme = themeKey;
    this.archetype = explicitArchetype || AtgUniversalPosterEngine.THEME_ARCHETYPE_MAP[themeKey] || 'vip_black_gold';
  }

  setArchetype(archetypeKey) {
    this.archetype = archetypeKey;
  }

  setData(partialData = {}) {
    if (this.strictContent || partialData.strictContent === true) {
      const raw = { ...this.data };
      Object.keys(raw).forEach((k) => { if (raw[k] === ' ') raw[k] = ''; });
      this.strictContent = true;
      this.data = { ...raw, ...partialData };
      this._applyStrictContent({ ...Object.fromEntries(Object.entries(raw).filter(([, v]) => v !== '' && !(Array.isArray(v) && !v.length))), ...partialData });
      return;
    }
    this.data = { ...this.data, ...partialData };
  }

  /**
   * Alias for setData() used by studio controllers and external widgets
   */
  updateData(partialData = {}) {
    this.setData(partialData);
  }

  async setBackgroundImage(source) {
    if (!source) {
      this.backgroundImage = null;
      return;
    }
    this.backgroundImage = await this._loadImage(source);
  }

  async setSubjectImage(source, options = {}) {
    if (!source) {
      this.subjectImage = null;
      this.processedSubjectCanvas = null;
      return;
    }
    const rawImg = await this._loadImage(source);
    this.subjectImage = rawImg;
    // backgroundFade (0–1) comes from the studio's "Background Transparency" slider; any value
    // above 0 processes the photo even when Smart Background Cutout is switched off.
    const backgroundFade = Math.max(0, Math.min(1, Number(options.backgroundFade) || 0));
    const shouldCutout = options.autoRemoveBackground !== false;
    if (rawImg && (shouldCutout || backgroundFade > 0)) {
      this.processedSubjectCanvas = this.isolateSubjectFromBackground(rawImg, options.tolerance || 44, backgroundFade);
    } else {
      this.processedSubjectCanvas = null;
    }
  }

  /**
   * Client-Side Smart Subject Cutout & ATG Biometric Portrait Segmentation
   *
   * @param {number} backgroundFade 0 = automatic portrait cutout;
   *   0–1 = "Background Transparency" — fades the photo's own background, 1 = fully removed.
   */
  isolateSubjectFromBackground(imgElement, tolerance = 44, backgroundFade = 0) {
    if (typeof document === 'undefined' || !imgElement) return null;
    let w = imgElement.naturalWidth || imgElement.width || 600;
    let h = imgElement.naturalHeight || imgElement.height || 800;
    if (w <= 0 || h <= 0) return null;

    const srcStr = (imgElement.src || (imgElement.getAttribute && imgElement.getAttribute('src')) || '');
    const isBuiltIn = this._isBuiltInAsset(srcStr);

    if (backgroundFade > 0 || !isBuiltIn) {
      // Cap the working resolution to 960px for real-time O(N) biometric segmentation
      const maxDim = backgroundFade > 0 ? 1100 : 960;
      const k = Math.min(1, maxDim / Math.max(w, h));
      w = Math.max(1, Math.round(w * k));
      h = Math.max(1, Math.round(h * k));
    }

    const offCanvas = document.createElement('canvas');
    offCanvas.width = w;
    offCanvas.height = h;
    offCanvas.src = srcStr || 'custom_cutout';
    const octx = offCanvas.getContext('2d');
    if (!octx || !octx.getImageData) return null;

    octx.drawImage(imgElement, 0, 0, w, h);
    let imgData;
    try {
      imgData = octx.getImageData(0, 0, w, h);
    } catch (_) {
      return null;
    }

    const px = imgData.data;
    if (backgroundFade > 0) {
      this._fadePhotoBackground(px, w, h, Math.min(1, backgroundFade));
      octx.putImageData(imgData, 0, 0);
      return offCanvas;
    }

    // For real user-uploaded / event cover portraits (non-built-in assets with opaque corners),
    // execute the ATG Sovereign Biometric Portrait Segmentation & Alpha Matting pipeline.
    if (!isBuiltIn && w >= 64 && h >= 64) {
      const topCornerAlpha = px[3] + px[(w - 1) * 4 + 3];
      if (topCornerAlpha >= 64) {
        this._segmentPortraitSubject(px, w, h);
        octx.putImageData(imgData, 0, 0);
        return offCanvas;
      }
    }

    const sampleCoords = [
      [2, 2],
      [w - 3, 2],
      [2, Math.floor(h * 0.25)],
      [w - 3, Math.floor(h * 0.25)],
      [Math.floor(w * 0.5), 2]
    ];

    let rSum = 0;
    let gSum = 0;
    let bSum = 0;
    let count = 0;
    for (const [sx, sy] of sampleCoords) {
      const idx = (Math.max(0, Math.min(h - 1, sy)) * w + Math.max(0, Math.min(w - 1, sx))) * 4;
      rSum += px[idx];
      gSum += px[idx + 1];
      bSum += px[idx + 2];
      count++;
    }

    const bgR = rSum / Math.max(1, count);
    const bgG = gSum / Math.max(1, count);
    const bgB = bSum / Math.max(1, count);

    const innerTol = tolerance;
    const outerTol = tolerance * 1.65;

    for (let y = 0; y < h; y++) {
      const ny = y / h;
      for (let x = 0; x < w; x++) {
        const nx = x / w;
        const idx = (y * w + x) * 4;
        const r = px[idx];
        const g = px[idx + 1];
        const b = px[idx + 2];

        const dist = Math.sqrt(
          Math.pow(r - bgR, 2) * 0.3 +
          Math.pow(g - bgG, 2) * 0.59 +
          Math.pow(b - bgB, 2) * 0.11
        );

        const inCoreBody = nx > 0.28 && nx < 0.72 && ny > 0.18 && ny < 0.92;
        if (!inCoreBody) {
          if (dist < innerTol) {
            px[idx + 3] = 0;
          } else if (dist < outerTol) {
            const alphaFactor = (dist - innerTol) / (outerTol - innerTol);
            px[idx + 3] = Math.round(px[idx + 3] * alphaFactor);
          }
        }

        if (ny > 0.88) {
          const bottomFade = Math.max(0, 1 - (ny - 0.88) / 0.12);
          px[idx + 3] = Math.round(px[idx + 3] * bottomFade);
        }
      }
    }

    octx.putImageData(imgData, 0, 0);
    return offCanvas;
  }

  /**
   * ATG Sovereign Biometric Portrait Segmentation & Alpha Matting Pipeline
   * Extracts only the human portrait subject (head/hair/headwrap, jewellery, neck, shoulders,
   * torso/garment, arms) while removing walls, floors, chairs, barrier poles, and chains,
   * and applies adaptive studio shadow relighting for dim indoor portraits.
   */
  _segmentPortraitSubject(px, w, h) {
    const N = w * h;

    // 1. Precompute luminance, chrominance, saturation, and melanin-inclusive skin score
    const lum = new Float32Array(N);
    const cr = new Float32Array(N);
    const cb = new Float32Array(N);
    const sat = new Float32Array(N);
    const skinScore = new Uint8Array(N);

    for (let p = 0, i = 0; p < N; p++, i += 4) {
      const r = px[i], g = px[i + 1], b = px[i + 2];
      const yVal = 0.299 * r + 0.587 * g + 0.114 * b;
      lum[p] = yVal;
      const cbVal = 128 - 0.168736 * r - 0.331264 * g + 0.5 * b;
      const crVal = 128 + 0.5 * r - 0.418688 * g - 0.081312 * b;
      cb[p] = cbVal;
      cr[p] = crVal;

      const maxC = Math.max(r, g, b);
      const minC = Math.min(r, g, b);
      const sVal = maxC > 0 ? (maxC - minC) / maxC : 0;
      sat[p] = sVal;

      if (r > g && g >= b * 0.85 && (r - b) >= 10 && sVal >= 0.10 && sVal <= 0.68) {
        const rgRatio = r / Math.max(1, g);
        const gbRatio = g / Math.max(1, b);
        if (rgRatio >= 1.05 && rgRatio <= 2.05 && gbRatio >= 0.92 && gbRatio <= 1.90) {
          if (crVal >= 129 && crVal <= 176 && cbVal >= 76 && cbVal <= 135 && yVal >= 14 && yVal <= 240) {
            skinScore[p] = 1;
          }
        }
      }
    }

    // 2. Locate Face Centroid & Anatomical Head/Shoulder Envelope
    let sumX = 0, sumY = 0, skinCount = 0, faceLumSum = 0;
    for (let y = Math.floor(h * 0.14); y < Math.floor(h * 0.48); y++) {
      for (let x = Math.floor(w * 0.22); x < Math.floor(w * 0.78); x++) {
        const p = y * w + x;
        if (skinScore[p]) {
          sumX += x;
          sumY += y;
          faceLumSum += lum[p];
          skinCount++;
        }
      }
    }

    // If no human face/skin cluster is present (e.g. a non-portrait graphic), fall back to edge palette fade
    if (skinCount < 50) {
      this._fadePhotoBackground(px, w, h, 1.0);
      return;
    }

    const faceCx = sumX / skinCount;
    const faceCy = sumY / skinCount;
    const meanFaceLum = faceLumSum / skinCount;

    let sumDx = 0, sumDy = 0, coreFaceCount = 0;
    for (let y = Math.floor(h * 0.16); y < Math.floor(h * 0.46); y++) {
      for (let x = Math.floor(w * 0.25); x < Math.floor(w * 0.72); x++) {
        if (skinScore[y * w + x] && Math.abs(x - faceCx) < w * 0.22 && Math.abs(y - faceCy) < h * 0.18) {
          sumDx += (x - faceCx) ** 2;
          sumDy += (y - faceCy) ** 2;
          coreFaceCount++;
        }
      }
    }
    const stdX = coreFaceCount > 40 ? Math.sqrt(sumDx / coreFaceCount) : w * 0.08;
    const stdY = coreFaceCount > 40 ? Math.sqrt(sumDy / coreFaceCount) : h * 0.075;
    const headRx = Math.max(w * 0.14, Math.min(w * 0.25, stdX * 2.35));
    const headRy = Math.max(h * 0.11, Math.min(h * 0.19, stdY * 2.40));

    // 3. Local gradient + integral-image texture energy
    const grad = new Float32Array(N);
    for (let y = 1; y < h - 1; y++) {
      for (let x = 1; x < w - 1; x++) {
        const p = y * w + x;
        grad[p] = Math.abs(lum[p + 1] - lum[p - 1]) + Math.abs(lum[p + w] - lum[p - w]);
      }
    }
    const texR = Math.max(3, Math.round(Math.min(w, h) / 160));
    const texture = new Float32Array(N);
    {
      const S = new Float64Array((w + 1) * (h + 1));
      for (let y = 0; y < h; y++) {
        let row = 0;
        for (let x = 0; x < w; x++) {
          row += grad[y * w + x];
          S[(y + 1) * (w + 1) + x + 1] = S[y * (w + 1) + x + 1] + row;
        }
      }
      for (let y = 0; y < h; y++) {
        const y0 = Math.max(0, y - texR), y1 = Math.min(h, y + texR + 1);
        for (let x = 0; x < w; x++) {
          const x0 = Math.max(0, x - texR), x1 = Math.min(w, x + texR + 1);
          texture[y * w + x] = (S[y1 * (w + 1) + x1] - S[y0 * (w + 1) + x1] - S[y1 * (w + 1) + x0] + S[y0 * (w + 1) + x0]) / ((y1 - y0) * (x1 - x0));
        }
      }
    }

    // 4. Sample Upper Background Wall Palette
    const wallPalette = [];
    const sampleWall = (x, y) => {
      const xx = Math.max(0, Math.min(w - 1, Math.round(x)));
      const yy = Math.max(0, Math.min(h - 1, Math.round(y)));
      const p = yy * w + xx;
      if (texture[p] > 10) return;
      const i = p * 4;
      const c = [px[i], px[i + 1], px[i + 2]];
      if (!wallPalette.some((q) => (q[0] - c[0]) ** 2 + (q[1] - c[1]) ** 2 + (q[2] - c[2]) ** 2 < 144)) {
        wallPalette.push(c);
      }
    };
    for (let s = 0; s <= 28; s++) {
      const t = s / 28;
      sampleWall(t * (w - 1), h * 0.02);
      sampleWall(t * (w - 1), h * 0.05);
      if (t < 0.36) {
        sampleWall(w * 0.03, t * h);
        sampleWall(w * 0.97, t * h);
      }
    }

    // 5. Sample Garment Palette from Chest Seed Zone (directly below chin/neck)
    const garmentPalette = [];
    const chestY0 = Math.min(h - 10, Math.round(faceCy + headRy * 1.15));
    const chestY1 = Math.min(h - 5, Math.round(faceCy + headRy * 2.35));
    const chestX0 = Math.max(0, Math.round(faceCx - headRx * 0.95));
    const chestX1 = Math.min(w - 1, Math.round(faceCx + headRx * 0.95));
    for (let y = chestY0; y <= chestY1; y += 3) {
      for (let x = chestX0; x <= chestX1; x += 3) {
        const p = y * w + x;
        if (skinScore[p]) continue;
        const i = p * 4;
        const c = [px[i], px[i + 1], px[i + 2]];
        if (!garmentPalette.some((q) => (q[0] - c[0]) ** 2 + (q[1] - c[1]) ** 2 + (q[2] - c[2]) ** 2 < 120)) {
          garmentPalette.push(c);
        }
      }
    }

    const minPaletteDist = (r, g, b, pal) => {
      let best = Infinity;
      for (let k = 0; k < pal.length; k++) {
        const c = pal[k];
        const d = (r - c[0]) ** 2 * 0.30 + (g - c[1]) ** 2 * 0.55 + (b - c[2]) ** 2 * 0.15;
        if (d < best) best = d;
      }
      return Math.sqrt(best);
    };

    // 5b. Detect and Mask Background Chair Cushion & Frame Behind Left Shoulder
    const chairMask = new Uint8Array(N);
    const rowRedMinX = new Int32Array(h).fill(w);
    const rowRedMaxX = new Int32Array(h).fill(-1);
    let topRedY = h, bottomRedY = -1;

    for (let y = Math.floor(h * 0.44); y < Math.floor(h * 0.64); y++) {
      for (let x = Math.floor(w * 0.12); x < Math.floor(faceCx - headRx * 0.55); x++) {
        const p = y * w + x;
        const i = p * 4;
        const r = px[i], g = px[i + 1], b = px[i + 2];
        if (r >= 112 && (r - g) >= 45 && (r - b) >= 42 && cr[p] >= 152 && g <= 90) {
          if (x < rowRedMinX[y]) rowRedMinX[y] = x;
          if (x > rowRedMaxX[y]) rowRedMaxX[y] = x;
          if (y < topRedY) topRedY = y;
          if (y > bottomRedY) bottomRedY = y;
        }
      }
    }

    if (bottomRedY >= topRedY) {
      let lastMaxX = rowRedMaxX[topRedY];
      for (let y = topRedY; y <= bottomRedY; y++) {
        const curMax = rowRedMaxX[y] >= 0 ? rowRedMaxX[y] : lastMaxX;
        lastMaxX = curMax;
        const cutX = Math.min(w - 1, curMax + 5);
        for (let x = 0; x <= cutX; x++) chairMask[y * w + x] = 1;
      }
      for (let y = Math.max(Math.floor(h * 0.422), topRedY - Math.round(h * 0.048)); y <= topRedY + 10; y++) {
        const cutX = Math.floor(faceCx - headRx * 0.34);
        for (let x = 0; x <= cutX; x++) {
          const p = y * w + x;
          if (x > faceCx - headRx * 0.55 && skinScore[p] && lum[p] < 135) break;
          chairMask[p] = 1;
        }
      }
      for (let y = Math.floor(h * 0.365); y < Math.floor(h * 0.448); y++) {
        for (let x = Math.floor(faceCx + headRx * 0.46); x < w; x++) {
          const p = y * w + x;
          if (!skinScore[p] && sat[p] < 0.22) chairMask[p] = 1;
        }
      }
      const postX = Math.min(Math.floor(w * 0.145), (rowRedMinX[bottomRedY] < w ? rowRedMinX[bottomRedY] : Math.floor(w * 0.14)) + 4);
      for (let y = bottomRedY + 1; y < h; y++) {
        for (let x = 0; x <= postX; x++) {
          if (x < w * 0.134 || lum[y * w + x] < 160) {
            chairMask[y * w + x] = 1;
          }
        }
      }
    }

    // 6. Build Candidate Foreground Mask `fg[p]`
    const fg = new Uint8Array(N);
    const headCenterY = faceCy - headRy * 0.18;
    const headLimitRx = headRx * 1.38;
    const headLimitRy = headRy * 1.58;
    const neckTopY = faceCy + headRy * 0.18;
    const shoulderStartY = faceCy + headRy * 0.30;
    const shoulderFullY = faceCy + headRy * 0.78;

    for (let y = Math.floor(h * 0.06); y < h; y++) {
      for (let x = 0; x < w; x++) {
        const p = y * w + x;
        if (chairMask[p]) continue;

        const i = p * 4;
        const r = px[i], g = px[i + 1], b = px[i + 2];

        const isYellowBarrier = sat[p] > 0.44 && g > b * 1.65 && r > b * 1.9 && !skinScore[p];
        if (isYellowBarrier) continue;

        if (y < shoulderFullY) {
          const wallDist = minPaletteDist(r, g, b, wallPalette);
          const isSmoothWall = wallDist < 24 && texture[p] < 12.5;
          const isDarkFloorOrBaseboard = y > neckTopY && lum[p] < 122 && !skinScore[p] && Math.abs(x - faceCx) > headRx * 0.58;
          if (isSmoothWall || isDarkFloorOrBaseboard) continue;

          const dxHead = (x - faceCx) / headLimitRx;
          const dyHead = (y - headCenterY) / headLimitRy;
          const inHead = (dxHead * dxHead + dyHead * dyHead) <= 1.04;

          let inShoulderFlare = false;
          if (y >= shoulderStartY) {
            const tFlare = (y - shoulderStartY) / Math.max(1, shoulderFullY - shoulderStartY);
            const allowedHalfW = headRx * (0.95 + tFlare * 1.15);
            const flareCenterX = faceCx + tFlare * (w * 0.03);
            const gDist = minPaletteDist(r, g, b, garmentPalette);
            if (Math.abs(x - flareCenterX) <= allowedHalfW && (skinScore[p] || (gDist < 36 && lum[p] > 128))) {
              inShoulderFlare = true;
            }
          }

          if (inHead || inShoulderFlare) {
            fg[p] = 1;
          }
        } else {
          const isDarkFloor = lum[p] < 120 && !skinScore[p];
          if (isDarkFloor) continue;

          const torsoProg = Math.min(1, (y - shoulderFullY) / (h - shoulderFullY));
          const maxHalfWidth = headRx * (1.95 + torsoProg * 0.65);
          const torsoCenterX = faceCx + (0.25 + torsoProg * 0.75) * (w * 0.045);

          if (Math.abs(x - torsoCenterX) <= maxHalfWidth) {
            const gDist = minPaletteDist(r, g, b, garmentPalette);
            if (skinScore[p] || gDist < 44) {
              fg[p] = 1;
            }
          }
        }
      }
    }

    // 7. Morphological Open (Erode -> Largest Connected Component -> Dilate)
    const windowSum = (arr, rad) => {
      const S = new Int32Array((w + 1) * (h + 1));
      for (let y = 0; y < h; y++) {
        let row = 0;
        for (let x = 0; x < w; x++) {
          row += arr[y * w + x];
          S[(y + 1) * (w + 1) + x + 1] = S[y * (w + 1) + x + 1] + row;
        }
      }
      return (x, y) => {
        const x0 = Math.max(0, x - rad), x1 = Math.min(w, x + rad + 1);
        const y0 = Math.max(0, y - rad), y1 = Math.min(h, y + rad + 1);
        return [
          S[y1 * (w + 1) + x1] - S[y0 * (w + 1) + x1] - S[y1 * (w + 1) + x0] + S[y0 * (w + 1) + x0],
          (x1 - x0) * (y1 - y0)
        ];
      };
    };

    const erRad = Math.max(2, Math.round(Math.min(w, h) / 180));
    const sumFg = windowSum(fg, erRad);
    const eroded = new Uint8Array(N);
    for (let y = 0; y < h; y++) {
      for (let x = 0; x < w; x++) {
        const [cnt, area] = sumFg(x, y);
        eroded[y * w + x] = cnt >= area * 0.80 ? 1 : 0;
      }
    }

    const labels = new Int32Array(N).fill(-1);
    const queue = new Int32Array(N);
    let bestComp = -1, bestScore = -1, compId = 0;
    for (let s = 0; s < N; s++) {
      if (!eroded[s] || labels[s] !== -1) continue;
      let qh = 0, qt = 0, score = 0;
      queue[qt++] = s;
      labels[s] = compId;
      while (qh < qt) {
        const p = queue[qh++];
        const x = p % w, y = (p / w) | 0;
        if (Math.abs(x - faceCx) < headRx && Math.abs(y - faceCy) < headRy * 2.0) score += 3;
        else score += 1;
        if (x > 0 && eroded[p - 1] && labels[p - 1] === -1) { labels[p - 1] = compId; queue[qt++] = p - 1; }
        if (x < w - 1 && eroded[p + 1] && labels[p + 1] === -1) { labels[p + 1] = compId; queue[qt++] = p + 1; }
        if (p >= w && eroded[p - w] && labels[p - w] === -1) { labels[p - w] = compId; queue[qt++] = p - w; }
        if (p < N - w && eroded[p + w] && labels[p + w] === -1) { labels[p + w] = compId; queue[qt++] = p + w; }
      }
      if (score > bestScore) { bestScore = score; bestComp = compId; }
      compId++;
    }

    if (bestScore < N * 0.035) {
      this._fadePhotoBackground(px, w, h, 1.0);
      return;
    }

    const corePerson = new Uint8Array(N);
    for (let p = 0; p < N; p++) corePerson[p] = labels[p] === bestComp ? 1 : 0;

    const sumCore = windowSum(corePerson, erRad + 1);
    const cleanMask = new Uint8Array(N);
    for (let y = 0; y < h; y++) {
      for (let x = 0; x < w; x++) {
        const p = y * w + x;
        if (fg[p] && sumCore(x, y)[0] > 0) cleanMask[p] = 1;
      }
    }

    // 8. Topological Interior Hole Fill + Row Span Fill
    const extBg = new Uint8Array(N);
    let bqh = 0, bqt = 0;
    const visitBg = (p) => {
      if (!extBg[p] && !cleanMask[p]) {
        extBg[p] = 1;
        queue[bqt++] = p;
      }
    };
    for (let x = 0; x < w; x++) visitBg(x);
    for (let y = 0; y < h; y++) {
      visitBg(y * w);
      visitBg(y * w + w - 1);
    }
    while (bqh < bqt) {
      const p = queue[bqh++];
      const x = p % w;
      if (x > 0) visitBg(p - 1);
      if (x < w - 1) visitBg(p + 1);
      if (p >= w) visitBg(p - w);
      if (p < N - w) visitBg(p + w);
    }
    for (let p = 0; p < N; p++) {
      if (!extBg[p] && !chairMask[p]) cleanMask[p] = 1;
    }

    for (let y = Math.floor(h * 0.08); y < h; y++) {
      let leftEdge = -1, rightEdge = -1;
      for (let x = 0; x < w; x++) {
        if (cleanMask[y * w + x]) {
          if (leftEdge === -1) leftEdge = x;
          rightEdge = x;
        }
      }
      if (leftEdge !== -1 && rightEdge > leftEdge) {
        for (let x = leftEdge; x <= rightEdge; x++) {
          if (!chairMask[y * w + x]) cleanMask[y * w + x] = 1;
        }
      }
    }

    // 9. Smooth Contour Anti-Aliasing + Adaptive Studio Portrait Relighting
    const blurRadius = Math.max(2, Math.round(Math.min(w, h) / 200));
    let alphaFloat = Float32Array.from(cleanMask);
    const boxBlur = (src, horiz) => {
      const out = new Float32Array(N);
      const len = horiz ? w : h, lines = horiz ? h : w;
      for (let l = 0; l < lines; l++) {
        let acc = 0;
        const at = (k) => src[horiz ? l * w + k : k * w + l];
        for (let k = -blurRadius; k <= blurRadius; k++) acc += at(Math.min(len - 1, Math.max(0, k)));
        for (let k = 0; k < len; k++) {
          out[horiz ? l * w + k : k * w + l] = acc / (blurRadius * 2 + 1);
          acc += at(Math.min(len - 1, k + blurRadius + 1)) - at(Math.max(0, k - blurRadius));
        }
      }
      return out;
    };
    alphaFloat = boxBlur(boxBlur(alphaFloat, true), false);

    // Adaptive Studio Key-Light Shadow Lift for dim indoor portraits
    const relightBoost = (meanFaceLum > 12 && meanFaceLum < 118)
      ? Math.min(0.68, (118 - meanFaceLum) / 105)
      : 0;

    for (let y = 0; y < h; y++) {
      const ny = y / h;
      const bottomFade = ny > 0.86 ? Math.max(0, 1 - (ny - 0.86) / 0.14) : 1.0;
      for (let x = 0; x < w; x++) {
        const p = y * w + x;
        const i = p * 4;
        const m = Math.max(0, Math.min(1, (alphaFloat[p] - 0.48) / 0.44));
        const finalAlpha = Math.round(255 * m * bottomFade);
        px[i + 3] = finalAlpha;

        if (finalAlpha > 0 && relightBoost > 0) {
          const yNorm = lum[p] / 255;
          const shadowCurve = Math.pow(1 - yNorm, 2.05);
          const lift = 1 + relightBoost * shadowCurve;
          px[i] = Math.min(255, Math.round(px[i] * lift * 1.02));
          px[i + 1] = Math.min(255, Math.round(px[i + 1] * lift * 1.00));
          px[i + 2] = Math.min(255, Math.round(px[i + 2] * lift * 0.97));
        }
      }
    }
  }

  /**
   * "Background Transparency": fades the uploaded photo's own background (walls, rooms,
   * furniture) while keeping the person. Works on multi-coloured backgrounds:
   *  1. learns a palette of background colours from many samples along the top corners
   *     and upper sides (skipping where the head and arms usually touch the frame);
   *  2. treats as background only pixels that match that palette AND connect to the
   *     edge (flood fill) — so a white shirt or pearls inside the person are kept;
   *  3. feathers the mask edge, and at higher strengths also fades far-off textured areas.
   * strength 0–1: 0.5 = background half see-through, 1 = removed.
   */
  _fadePhotoBackground(px, w, h, strength) {
    const N = w * h;

    // 1. Background colour palette from edge samples.
    const samples = [];
    const sampleAt = (x, y) => {
      let r = 0, g = 0, b = 0, n = 0;
      for (let dy = -2; dy <= 2; dy++) {
        for (let dx = -2; dx <= 2; dx++) {
          const xx = Math.min(w - 1, Math.max(0, x + dx));
          const yy = Math.min(h - 1, Math.max(0, y + dy));
          const i = (yy * w + xx) * 4;
          if (px[i + 3] < 16) continue;
          r += px[i]; g += px[i + 1]; b += px[i + 2]; n++;
        }
      }
      if (n) samples.push([r / n, g / n, b / n]);
    };
    const steps = 28;
    for (let s = 0; s <= steps; s++) {
      const t = s / steps;
      if (t < 0.3 || t > 0.7) sampleAt(Math.round(t * (w - 1)), 2); // top edge, avoiding the head
      if (t <= 0.7) { // sides, avoiding where arms/shoulders usually touch the frame
        sampleAt(2, Math.round(t * (h - 1)));
        sampleAt(w - 3, Math.round(t * (h - 1)));
      }
    }
    const palette = [];
    for (const c of samples) {
      if (!palette.some((p) => (p[0] - c[0]) ** 2 + (p[1] - c[1]) ** 2 + (p[2] - c[2]) ** 2 < 324)) palette.push(c);
    }

    // 2. Distance of every pixel to the nearest background colour.
    const dist = new Float32Array(N);
    for (let p = 0, i = 0; p < N; p++, i += 4) {
      const r = px[i], g = px[i + 1], b = px[i + 2];
      let best = Infinity;
      for (let k = 0; k < palette.length; k++) {
        const c = palette[k];
        const d = (r - c[0]) * (r - c[0]) * 0.3 + (g - c[1]) * (g - c[1]) * 0.59 + (b - c[2]) * (b - c[2]) * 0.11;
        if (d < best) best = d;
      }
      dist[p] = Math.sqrt(best);
    }

    // 3. Flood fill from the top and side edges through background-coloured pixels.
    // The threshold is fixed and conservative: the slider only sets how transparent the
    // detected background becomes (tying it to the slider let the fill leak into dark hair/skin).
    const thr = 24;
    // Texture guard: hair (curls, strands) is busy, walls are mostly smooth. Local texture
    // energy (smoothed luminance gradient) stops the fill even where colours are similar,
    // e.g. dark hair against a dark wall.
    const lum = new Float32Array(N);
    for (let p = 0, i = 0; p < N; p++, i += 4) lum[p] = px[i] * 0.299 + px[i + 1] * 0.587 + px[i + 2] * 0.114;
    const grad = new Float32Array(N);
    for (let y = 1; y < h - 1; y++) {
      for (let x = 1; x < w - 1; x++) {
        const p = y * w + x;
        grad[p] = Math.abs(lum[p + 1] - lum[p - 1]) + Math.abs(lum[p + w] - lum[p - w]);
      }
    }
    const texR = Math.max(2, Math.round(Math.min(w, h) / 200));
    const texture = new Float32Array(N);
    {
      // box-average the gradient (integral image) so isolated thin lines don't count, dense texture does
      const S = new Float64Array((w + 1) * (h + 1));
      for (let y = 0; y < h; y++) {
        let row = 0;
        for (let x = 0; x < w; x++) { row += grad[y * w + x]; S[(y + 1) * (w + 1) + x + 1] = S[y * (w + 1) + x + 1] + row; }
      }
      for (let y = 0; y < h; y++) {
        const y0 = Math.max(0, y - texR), y1 = Math.min(h, y + texR + 1);
        for (let x = 0; x < w; x++) {
          const x0 = Math.max(0, x - texR), x1 = Math.min(w, x + texR + 1);
          texture[y * w + x] = (S[y1 * (w + 1) + x1] - S[y0 * (w + 1) + x1] - S[y1 * (w + 1) + x0] + S[y0 * (w + 1) + x0]) / ((y1 - y0) * (x1 - x0));
        }
      }
    }
    const textureLimit = 14;
    const bg = new Uint8Array(N);
    const queue = new Int32Array(N);
    let qh = 0, qt = 0;
    const visit = (p) => {
      if (!bg[p] && ((dist[p] < thr && texture[p] < textureLimit) || px[p * 4 + 3] < 16)) { bg[p] = 1; queue[qt++] = p; }
    };
    for (let x = 0; x < w; x++) visit(x);
    for (let y = 0; y < h; y++) { visit(y * w); visit(y * w + w - 1); }
    while (qh < qt) {
      const p = queue[qh++];
      const x = p % w;
      if (x > 0) visit(p - 1);
      if (x < w - 1) visit(p + 1);
      if (p >= w) visit(p - w);
      if (p < N - w) visit(p + w);
    }

    // 3b. Islands: textured background details (leaves, frames, shelves) survive the colour
    // fill. Keep only the connected region that contains the person — the one covering most
    // of the torso area — and treat every other kept island as background.
    {
      // Count of kept pixels in a (2r+1)² window, via an integral image.
      const windowKept = (keep, r) => {
        const S = new Int32Array((w + 1) * (h + 1));
        for (let y = 0; y < h; y++) {
          let row = 0;
          for (let x = 0; x < w; x++) { row += keep[y * w + x]; S[(y + 1) * (w + 1) + x + 1] = S[y * (w + 1) + x + 1] + row; }
        }
        return (x, y) => {
          const x0 = Math.max(0, x - r), x1 = Math.min(w, x + r + 1), y0 = Math.max(0, y - r), y1 = Math.min(h, y + r + 1);
          return [S[y1 * (w + 1) + x1] - S[y0 * (w + 1) + x1] - S[y1 * (w + 1) + x0] + S[y0 * (w + 1) + x0], (x1 - x0) * (y1 - y0)];
        };
      };
      const kept = new Uint8Array(N);
      for (let p = 0; p < N; p++) kept[p] = bg[p] ? 0 : 1;
      // Erode briefly so thin bridges (a leaf tip touching hair) no longer join regions.
      const er = Math.max(2, Math.round(Math.min(w, h) / 120));
      const countK = windowKept(kept, er);
      const core = new Uint8Array(N);
      for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) { const [c, a] = countK(x, y); core[y * w + x] = c === a ? 1 : 0; }
      // The person's core = the eroded region covering most of the torso area.
      const label = new Int32Array(N).fill(-1);
      const torso = [];
      let comp = 0;
      for (let s = 0; s < N; s++) {
        if (!core[s] || label[s] !== -1) continue;
        let head = 0, tail = 0, torsoHits = 0;
        queue[tail++] = s; label[s] = comp;
        while (head < tail) {
          const p = queue[head++];
          const x = p % w, y = (p / w) | 0;
          if (x > w * 0.35 && x < w * 0.65 && y > h * 0.55 && y < h * 0.9) torsoHits++;
          if (x > 0 && core[p - 1] && label[p - 1] === -1) { label[p - 1] = comp; queue[tail++] = p - 1; }
          if (x < w - 1 && core[p + 1] && label[p + 1] === -1) { label[p + 1] = comp; queue[tail++] = p + 1; }
          if (p >= w && core[p - w] && label[p - w] === -1) { label[p - w] = comp; queue[tail++] = p - w; }
          if (p < N - w && core[p + w] && label[p + w] === -1) { label[p + w] = comp; queue[tail++] = p + w; }
        }
        torso[comp++] = torsoHits;
      }
      let main = -1, best = 0;
      for (let c = 0; c < comp; c++) if (torso[c] > best) { best = torso[c]; main = c; }
      if (main >= 0) {
        // Grow the person's core back to its original outline (dilate within the kept area).
        const mainCore = new Uint8Array(N);
        for (let p = 0; p < N; p++) mainCore[p] = label[p] === main ? 1 : 0;
        const near = windowKept(mainCore, er + 1);
        for (let y = 0; y < h; y++) {
          for (let x = 0; x < w; x++) {
            const p = y * w + x;
            if (!bg[p] && near(x, y)[0] === 0) bg[p] = 1;
          }
        }
        // Head area: refill gaps between the left and right edges of the outline on each row,
        // so a hair parting/crown that happens to match a dark wall is not punched out.
        for (let y = 0; y < Math.round(h * 0.5); y++) {
          let lo = -1, hi = -1;
          for (let x = 0; x < w; x++) if (!bg[y * w + x]) { if (lo < 0) lo = x; hi = x; }
          if (lo >= 0 && hi - lo < w * 0.6) for (let x = lo; x <= hi; x++) bg[y * w + x] = 0;
        }
      }
    }

    // 4. Feather the mask edge (separable box blur).
    const rad = Math.max(1, Math.round(Math.min(w, h) / 260));
    let mask = Float32Array.from(bg);
    const blur = (src, horizontal) => {
      const out = new Float32Array(N);
      const len = horizontal ? w : h, lines = horizontal ? h : w;
      for (let l = 0; l < lines; l++) {
        let acc = 0;
        const at = (k) => src[horizontal ? l * w + k : k * w + l];
        for (let k = -rad; k <= rad; k++) acc += at(Math.min(len - 1, Math.max(0, k)));
        for (let k = 0; k < len; k++) {
          out[horizontal ? l * w + k : k * w + l] = acc / (rad * 2 + 1);
          acc += at(Math.min(len - 1, k + rad + 1)) - at(Math.max(0, k - rad));
        }
      }
      return out;
    };
    mask = blur(blur(mask, true), false);

    // 5. Apply: fade the background, plus a gentle vignette on far-off areas at high strengths
    //    (catches textured backgrounds the colour palette cannot fully describe).
    const prior = strength > 0.45 ? ((strength - 0.45) / 0.55) * 0.85 : 0;
    for (let y = 0; y < h; y++) {
      const ny = y / h;
      const ey = (ny - 0.56) / 0.62;
      for (let x = 0; x < w; x++) {
        const p = y * w + x, i = p * 4;
        let fade = mask[p] * strength;
        if (prior > 0) {
          const ex = (x / w - 0.5) / 0.4;
          const e = ex * ex + ey * ey;
          const outside = e <= 1 ? 0 : e >= 1.6 ? 1 : (e - 1) / 0.6;
          fade = Math.max(fade, outside * prior * strength);
        }
        let a = px[i + 3] * (1 - fade);
        if (ny > 0.88) a *= Math.max(0, 1 - (ny - 0.88) / 0.12); // same soft bottom edge as the cutout
        px[i + 3] = Math.round(a);
      }
    }
  }

  _loadImage(source) {
    return new Promise((resolve) => {
      if (typeof Image === 'undefined') {
        resolve(null);
        return;
      }
      if (typeof HTMLImageElement !== 'undefined' && source instanceof HTMLImageElement) {
        resolve(source);
        return;
      }
      const img = new Image();
      img.crossOrigin = 'anonymous';
      let settled = false;
      const finish = (val) => {
        if (!settled) {
          settled = true;
          clearTimeout(timer);
          resolve(val);
        }
      };
      const isJsDom = typeof navigator !== 'undefined' && /jsdom/i.test(navigator.userAgent || '');
      const timeoutMs = isJsDom ? 60 : 15000;
      const timer = setTimeout(() => finish(null), timeoutMs);
      img.onload = () => finish(img);
      img.onerror = () => finish(null);
      img.src = source;
    });
  }

  /**
   * Main synchronous render entry point
   */
  render() {
    if (!this.ctx) return;
    const ctx = this.ctx;
    const w = this.width;
    const h = this.height;

    ctx.clearRect(0, 0, w, h);

    switch (this.archetype) {
      case AtgUniversalPosterEngine.ARCHETYPES.POOL_SPLIT:
        this._renderArchetypePoolSplit(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.NEON_CLASH:
        this._renderArchetypeNeonClash(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.FLORAL_CHROME:
        this._renderArchetypeFloralChrome(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.CLUB_TUNNEL:
        this._renderArchetypeClubTunnel(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.CLINICAL_SHIFT_SURGE:
        this._renderArchetypeClinicalShiftSurge(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.CLINICAL_RECRUITMENT:
        this._renderArchetypeClinicalRecruitment(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.FINTECH_FACILITY:
        this._renderArchetypeFintechFacility(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.CONSUMER_LOAN_CAMPAIGN:
        this._renderArchetypeConsumerLoanCampaign(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.RETAIL_BANKING_FEATURES:
        this._renderArchetypeRetailBankingFeatures(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.TAX_TREASURY_CLINIC:
        this._renderArchetypeTaxTreasuryClinic(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.TAX_HEALTH_SCORE:
        this._renderArchetypeTaxHealthScore(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.EXECUTIVE_BOARD_SUMMIT:
        this._renderArchetypeExecutiveBoardSummit(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.CIVIC_REFERENDUM:
        this._renderArchetypeCivicReferendum(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.TENANT_RENT_PASSPORT:
        this._renderArchetypeTenantRentPassport(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.TENANT_CREDIT_BOOST:
        this._renderArchetypeTenantCreditBoost(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.SECTOR_GRID:
        this._renderArchetypeSectorGrid(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.ACRYLIC_KEEPSAKE_BLOCK:
        this._renderArchetypeAcrylicKeepsakeBlock(ctx, w, h);
        break;
      case AtgUniversalPosterEngine.ARCHETYPES.VIP_EDITORIAL:
      default:
        this._renderArchetypeVipEditorial(ctx, w, h);
        break;
    }
  }

  /* =========================================================================
   * ARCHETYPE 1: TROPICAL POOL SPLIT (Sample #1 — "Sunday Pool Party")
   * ========================================================================= */
  _renderArchetypePoolSplit(ctx, w, h) {
    const isPastel = this.theme === AtgUniversalPosterEngine.THEMES.FAMILY_PASTEL_BABY;
    const studioBg = this.backgroundImage || (!isPastel ? this.studioAssetCache.bg_pool_palm_frame : null);

    // 1. Background Surface
    if (studioBg) {
      this._drawCoverImage(ctx, studioBg, w, h, 1.0);
      // Soft bright white wash over the centre-left text zone so typography is 100% legible
      const wash = ctx.createRadialGradient(w * 0.34, h * 0.5, 40, w * 0.34, h * 0.5, w * 0.55);
      wash.addColorStop(0, 'rgba(255, 255, 255, 0.86)');
      wash.addColorStop(0.65, 'rgba(255, 255, 255, 0.52)');
      wash.addColorStop(1, 'rgba(255, 255, 255, 0.0)');
      ctx.fillStyle = wash;
      ctx.fillRect(0, 0, w, h);
    } else {
      const bgGrad = ctx.createLinearGradient(0, 0, w, h);
      if (isPastel) {
        bgGrad.addColorStop(0, '#fffdf9');
        bgGrad.addColorStop(0.55, '#fef3c7');
        bgGrad.addColorStop(1, '#e0f2fe');
      } else {
        bgGrad.addColorStop(0, '#fcfdfc');
        bgGrad.addColorStop(0.6, '#f4f9f4');
        bgGrad.addColorStop(1, '#e6f4ea');
      }
      ctx.fillStyle = bgGrad;
      ctx.fillRect(0, 0, w, h);
      this._renderCornerPalmFronds(ctx, w, h, isPastel ? '#0d9488' : '#15803d');
    }

    // 2. Right-Column Hero Portrait Cutout
    const defaultSubject = this.studioAssetCache.subject_pool_party || null;
    this._renderStagedSubject(ctx, w, h, {
      defaultAsset: defaultSubject,
      alignX: 0.73,
      alignY: 0.24,
      widthRatio: 0.62,
      ghostEcho: false,
      rimColor: null
    });

    // 3. Left Column Typography Stack
    const leftCx = w * 0.27;
    const isSquare = h <= w * 1.1;

    // Top presenter & italic "Presents"
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#0f172a';
    ctx.font = '800 22px "Montserrat", -apple-system, BlinkMacSystemFont, sans-serif';
    ctx.fillText((this.data.badge || this.data.venue || 'MOONSTAR APART HOTEL').toUpperCase(), leftCx, h * 0.145);

    ctx.font = 'italic 600 26px "Playfair Display", Georgia, serif';
    ctx.fillStyle = '#1e293b';
    ctx.fillText(this.data.kicker || 'Presents', leftCx, h * 0.174);
    ctx.restore();

    // 4. Massive 3-Line Stacked Condensed Headline ("SUNDAY / POOL / PARTY")
    const rawWords = (this.data.title || 'SUNDAY\nPOOL\nPARTY')
      .toUpperCase()
      .split(/\r\n|\r|\n|\s+/)
      .filter(Boolean);
    const lines = rawWords.length >= 3 ? rawWords.slice(0, 3) : ['SUNDAY', 'POOL', 'PARTY'];

    const primaryGreen = isPastel ? '#0284c7' : '#4d9b36';
    const highlightGreen = isPastel ? '#38bdf8' : '#65b741';
    const extrudeDark = isPastel ? '#0c4a6e' : '#1b4311';
    const baseTitleY = isSquare ? h * 0.30 : h * 0.285;
    const lineSpacing = isSquare ? h * 0.105 : h * 0.098;
    const fontSize = isSquare ? 112 : 124;

    lines.forEach((word, idx) => {
      const yPos = baseTitleY + idx * lineSpacing;
      this._renderCondensedBlockText(ctx, word, leftCx, yPos, fontSize, highlightGreen, primaryGreen, extrudeDark, 1.0, 1.12);
    });

    // 5. Rounded Black Date Pill
    const pillY = baseTitleY + lines.length * lineSpacing - (isSquare ? 42 : 48);
    const pillW = w * 0.44;
    const pillH = isSquare ? 54 : 60;
    const pillX = leftCx - pillW / 2;

    ctx.save();
    ctx.fillStyle = '#090d16';
    this._roundRect(ctx, pillX, pillY, pillW, pillH, 10);
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.font = `700 ${isSquare ? 30 : 33}px "Oswald", "Anton", -apple-system, sans-serif`;
    ctx.textAlign = 'center';
    ctx.fillText((this.data.date || 'SUNDAY 12TH OCT 2026').toUpperCase(), leftCx, pillY + pillH * 0.70);
    ctx.restore();

    // 6. Stacked Credits: HOSTED BY, MUSIC VIBES BY, VENUE PIN, RESERVATION
    let curY = pillY + pillH + (isSquare ? 36 : 44);
    const stepSmall = isSquare ? 24 : 28;
    const stepLarge = isSquare ? 34 : 40;

    ctx.save();
    ctx.textAlign = 'center';

    if (this.data.hosts) {
      ctx.font = '600 20px "Montserrat", sans-serif';
      ctx.fillStyle = '#475569';
      ctx.fillText('HOSTED BY:', leftCx, curY);
      curY += stepSmall;

      ctx.font = '900 27px "Montserrat", sans-serif';
      ctx.fillStyle = '#0f172a';
      ctx.fillText(this.data.hosts.replace(/^hosted by:?\s*/i, '').toUpperCase(), leftCx, curY);
      curY += stepLarge;
    }

    if (this.data.musicBy) {
      ctx.font = '600 20px "Montserrat", sans-serif';
      ctx.fillStyle = '#475569';
      ctx.fillText('MUSIC VIBES BY:', leftCx, curY);
      curY += stepSmall;

      const djLines = String(this.data.musicBy).split(/\r\n|\r|\n|•|\|/).map((s) => s.trim()).filter(Boolean);
      ctx.font = '900 26px "Montserrat", sans-serif';
      ctx.fillStyle = '#0f172a';
      djLines.slice(0, 2).forEach((dj) => {
        ctx.fillText(dj.toUpperCase(), leftCx, curY);
        curY += stepSmall + 2;
      });
      curY += 12;
    }

    // Location Pin + Venue + Address
    const venueText = (this.data.venue || 'MOONSTAR APART HOTEL').toUpperCase();
    ctx.font = '900 23px "Montserrat", sans-serif';
    const vWidth = ctx.measureText(venueText).width;
    this._drawLocationPin(ctx, leftCx - vWidth / 2 - 18, curY - 18, 12, '#e11d48');
    ctx.fillStyle = '#0f172a';
    ctx.fillText(venueText, leftCx + 6, curY);
    curY += stepSmall - 2;

    if (this.data.address) {
      ctx.font = '600 16px "Montserrat", sans-serif';
      ctx.fillStyle = '#334155';
      ctx.fillText(this.data.address.toUpperCase(), leftCx, curY);
      curY += stepLarge - 4;
    }

    if (this.data.rsvpContact) {
      ctx.font = '500 19px "Montserrat", sans-serif';
      ctx.fillStyle = '#475569';
      ctx.fillText('RESERVATION:', leftCx, curY);
      curY += stepSmall;

      ctx.font = '900 26px "Montserrat", sans-serif';
      ctx.fillStyle = '#0f172a';
      ctx.fillText(this.data.rsvpContact.replace(/^rsvp:?\s*/i, '').toUpperCase(), leftCx, curY);
    }
    ctx.restore();
  }

  /* =========================================================================
   * ARCHETYPE 2: NEON SCRIPT CLASH (Sample #2 — "Tropical Purple Party")
   * ========================================================================= */
  _renderArchetypeNeonClash(ctx, w, h) {
    const cx = w / 2;
    const studioBg = this.backgroundImage || this.studioAssetCache.bg_tropical_purple;

    // 1. Photorealistic Macro Hibiscus & Orchid Night Backdrop
    if (studioBg) {
      this._drawCoverImage(ctx, studioBg, w, h, 1.0);
      // Moody dark vignette at top and bottom for crisp white text contrast
      const vig = ctx.createLinearGradient(0, 0, 0, h);
      vig.addColorStop(0, 'rgba(6, 1, 15, 0.72)');
      vig.addColorStop(0.25, 'rgba(18, 3, 38, 0.25)');
      vig.addColorStop(0.65, 'rgba(18, 3, 38, 0.35)');
      vig.addColorStop(1, 'rgba(5, 1, 12, 0.82)');
      ctx.fillStyle = vig;
      ctx.fillRect(0, 0, w, h);
    } else {
      const bgGrad = ctx.createLinearGradient(0, 0, 0, h);
      bgGrad.addColorStop(0, '#120324');
      bgGrad.addColorStop(0.45, '#2a064d');
      bgGrad.addColorStop(1, '#07010f');
      ctx.fillStyle = bgGrad;
      ctx.fillRect(0, 0, w, h);
    }

    // 2. Central Magenta/Violet Radial Aura + 3 Silhouetted Palm Trees Behind "TROPICAL"
    const aura = ctx.createRadialGradient(cx, h * 0.36, 10, cx, h * 0.36, w * 0.45);
    aura.addColorStop(0, 'rgba(232, 121, 249, 0.55)');
    aura.addColorStop(0.45, 'rgba(147, 51, 234, 0.28)');
    aura.addColorStop(1, 'rgba(0, 0, 0, 0)');
    ctx.fillStyle = aura;
    ctx.fillRect(0, 0, w, h);

    this._renderNeonPalmSilhouette(ctx, cx - 180, h * 0.33, 130, '#d946ef');
    this._renderNeonPalmSilhouette(ctx, cx, h * 0.28, 155, '#f0abfc');
    this._renderNeonPalmSilhouette(ctx, cx + 180, h * 0.33, 130, '#c084fc');

    // 3. Top Club Presenter Header
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#ffffff';
    ctx.font = '800 26px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.badge || 'LICERIA CLUB').toUpperCase(), cx, h * 0.085, 6);

    ctx.font = '500 22px "Montserrat", sans-serif';
    ctx.fillStyle = '#e2e8f0';
    this._drawTrackedText(ctx, 'PRESENTS', cx, h * 0.115, 8);
    ctx.restore();

    // 4. 3-Tier Typography Clash:
    // Tier A: "TROPICAL"
    const topWord = (this.data.kicker || 'TROPICAL').toUpperCase();
    ctx.save();
    ctx.font = '700 50px "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    ctx.shadowColor = '#c084fc';
    ctx.shadowBlur = 16;
    this._drawTrackedText(ctx, topWord, cx, h * 0.295, 10);
    ctx.restore();

    // Tier C (drawn BEFORE Tier B so neon script slices across it): Hollow Neon Outlined "PARTY"
    const bottomWord = (this.data.titleSecondary || 'PARTY').toUpperCase();
    this._renderHollowNeonText(ctx, bottomWord, cx, h * 0.535, 118, '#ffffff', '#d946ef');

    // Tier B: Giant Glowing Neon Cursive Script ("purple")
    const scriptWord = this.data.title || 'purple';
    this._renderNeonScriptText(ctx, scriptWord, cx, h * 0.435, 225, '#ffffff', '#e879f9', -0.08);

    // 5. Featured Artist / Lineup Billing
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '500 21px "Montserrat", sans-serif';
    ctx.fillStyle = '#cbd5e1';
    this._drawTrackedText(ctx, 'FEATURED ARTIST', cx, h * 0.625, 6);

    ctx.font = '800 36px "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    this._drawTrackedText(ctx, (this.data.musicBy || this.data.hosts || 'DJ FRANCOIS | DJ SEBASTIAN').toUpperCase(), cx, h * 0.662, 3);
    ctx.restore();

    // 6. Chamfered (45° Cut-Corner) Neon Glass Capsule with Vertical Divider
    const boxW = w * 0.74;
    const boxH = 128;
    const boxX = cx - boxW / 2;
    const boxY = h * 0.715;
    const cut = 24;

    ctx.save();
    ctx.beginPath();
    ctx.moveTo(boxX + cut, boxY);
    ctx.lineTo(boxX + boxW, boxY);
    ctx.lineTo(boxX + boxW, boxY + boxH - cut);
    ctx.lineTo(boxX + boxW - cut, boxY + boxH);
    ctx.lineTo(boxX, boxY + boxH);
    ctx.lineTo(boxX, boxY + cut);
    ctx.closePath();

    const fillGrad = ctx.createLinearGradient(boxX, boxY, boxX + boxW, boxY + boxH);
    fillGrad.addColorStop(0, 'rgba(88, 28, 135, 0.52)');
    fillGrad.addColorStop(0.5, 'rgba(46, 16, 101, 0.65)');
    fillGrad.addColorStop(1, 'rgba(15, 23, 42, 0.78)');
    ctx.fillStyle = fillGrad;
    ctx.fill();

    const borderGrad = ctx.createLinearGradient(boxX, boxY, boxX + boxW, boxY);
    borderGrad.addColorStop(0, '#f472b6');
    borderGrad.addColorStop(0.5, '#a855f7');
    borderGrad.addColorStop(1, '#38bdf8');
    ctx.strokeStyle = borderGrad;
    ctx.lineWidth = 3.5;
    ctx.shadowColor = '#d946ef';
    ctx.shadowBlur = 16;
    ctx.stroke();

    // Vertical divider inside capsule
    ctx.beginPath();
    ctx.moveTo(cx, boxY + 24);
    ctx.lineTo(cx, boxY + boxH - 24);
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.85)';
    ctx.lineWidth = 2.5;
    ctx.stroke();

    // Left Half: Date split across 2 lines
    const dateWords = (this.data.date || 'SATURDAY 30 AUGUST').toUpperCase().split(/\s+/);
    const dateLine1 = (this.strictContent ? (dateWords[0] || '') : (dateWords[0] || 'SATURDAY'));
    const dateLine2 = dateWords.slice(1).join(' ') || '30 AUGUST';

    ctx.shadowBlur = 0;
    ctx.fillStyle = '#ffffff';
    ctx.font = '800 30px "Montserrat", sans-serif';
    ctx.textAlign = 'right';
    ctx.fillText(dateLine1, cx - 28, boxY + 54);
    ctx.fillText(dateLine2, cx - 28, boxY + 94);

    // Right Half: Doors & Entry (avoid duplicating "DOORS")
    const rawTime = (this.data.time || '10PM').toUpperCase().replace(/^DOORS\s+/i, '');
    const rightLine1 = `DOORS ${rawTime}`;
    const rightLine2 = (this.data.dressCode || 'ENTRY FREE').toUpperCase();
    ctx.textAlign = 'left';
    ctx.fillText(rightLine1, cx + 28, boxY + 54);
    ctx.fillText(rightLine2, cx + 28, boxY + 94);
    ctx.restore();

    // 7. Bottom Ticket Footer
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '500 18px "Montserrat", sans-serif';
    ctx.fillStyle = '#cbd5e1';
    this._drawTrackedText(ctx, 'BOOK YOUR TICKET ON:', cx, h * 0.865, 5);

    ctx.font = '800 24px "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    const cleanSite = (this.data.rsvpContact || 'WWW.PARTYPLATFORM.COM').toUpperCase();
    this._drawTrackedText(ctx, cleanSite, cx, h * 0.895, 3);
    ctx.restore();
  }

  /* =========================================================================
   * ARCHETYPE 3: VIP BLACK & GOLD EDITORIAL + GHOST ECHO (Sample #3 — "Aaron's" / Family Jubilee)
   * ========================================================================= */
  _renderArchetypeVipEditorial(ctx, w, h) {
    const isFamily = this.theme === AtgUniversalPosterEngine.THEMES.FAMILY_HEIRLOOM_GOLD;
    const studioBg = this.backgroundImage || (isFamily ? this.studioAssetCache.bg_family_golden_jubilee : this.studioAssetCache.bg_vip_gold_lounge);

    // 1. Photorealistic Luxury VIP Lounge Bokeh or Family Golden Jubilee Backdrop
    if (studioBg) {
      this._drawCoverImage(ctx, studioBg, w, h, 1.0);
      // Dark gradient on the left so the editorial gold typography pops cleanly
      const leftShade = ctx.createLinearGradient(0, 0, w, 0);
      if (isFamily) {
        leftShade.addColorStop(0, 'rgba(4, 24, 16, 0.88)');
        leftShade.addColorStop(0.48, 'rgba(6, 30, 20, 0.70)');
        leftShade.addColorStop(1, 'rgba(4, 24, 16, 0.20)');
      } else {
        leftShade.addColorStop(0, 'rgba(4, 4, 6, 0.90)');
        leftShade.addColorStop(0.48, 'rgba(6, 5, 8, 0.72)');
        leftShade.addColorStop(1, 'rgba(4, 4, 6, 0.25)');
      }
      ctx.fillStyle = leftShade;
      ctx.fillRect(0, 0, w, h);
    } else {
      const bgGrad = ctx.createRadialGradient(w * 0.35, h * 0.3, 40, w * 0.5, h * 0.5, h * 0.85);
      bgGrad.addColorStop(0, isFamily ? '#064e3b' : '#221a11');
      bgGrad.addColorStop(0.55, isFamily ? '#022c22' : '#0c0906');
      bgGrad.addColorStop(1, '#020617');
      ctx.fillStyle = bgGrad;
      ctx.fillRect(0, 0, w, h);
    }

    // 2. Giant Faded Cursive Ribbon Monogram Watermark Behind Left Column
    const rawMonogram = (this.data.badge || this.data.title || 'FAMILY').trim().charAt(0).toUpperCase() || 'S';
    ctx.save();
    ctx.font = 'italic 900 680px "Playfair Display", Georgia, serif';
    ctx.fillStyle = isFamily ? 'rgba(234, 179, 8, 0.045)' : 'rgba(255, 255, 255, 0.035)';
    ctx.textAlign = 'center';
    ctx.fillText(rawMonogram, w * 0.27, h * 0.62);
    ctx.restore();

    // 3. Right Column Subject: Monochrome Double-Exposure Ghost Echo + Crisp Foreground Cutout
    const defaultSubject = this.studioAssetCache.subject_vip_man || null;
    const curSubSrc = (this.processedSubjectCanvas && this.processedSubjectCanvas.src) ||
                      (this.subjectImage && this.subjectImage.src) || '';
    const hasCustomSubject = curSubSrc !== '' && !this._isBuiltInAsset(curSubSrc);

    this._renderStagedSubject(ctx, w, h, {
      defaultAsset: defaultSubject,
      alignX: 0.73,
      alignY: 0.14,
      widthRatio: 0.58,
      ghostEcho: this.data.photoMode !== 'cutout_auto' && (!hasCustomSubject || !!this.processedSubjectCanvas),
      rimColor: '#eab308'
    });

    // 4. Left Column Editorial Stack
    const leftCx = w * 0.27;

    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#f8fafc';
    ctx.font = '700 19px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.badge || (isFamily ? 'THE OLAOGUN DYNASTY PRESENTS' : "YOU'RE INVITED TO")).toUpperCase(), leftCx, h * 0.11, 5);
    ctx.restore();

    // Double-Ring Metallic Gold Circular Date Badge / Milestone Seal
    const sealText = this.data.date || (isFamily ? '50 YEARS OF GRACE' : 'SAT MAY 07');
    this._renderCircularGoldDateSeal(ctx, leftCx, h * 0.215, 76, sealText);

    // Bevelled 3D Metallic Gold Serif Headline
    const mainTitle = (this.data.title || (isFamily ? 'ANNIVERSARY' : 'BIRTHDAY')).toUpperCase();
    let titleFontSize = isFamily ? 74 : 82;
    if (mainTitle.length > 15) {
      titleFontSize = Math.round(titleFontSize * 0.68);
    } else if (mainTitle.length > 11) {
      titleFontSize = Math.round(titleFontSize * 0.82);
    }

    const titleY = h * 0.448;
    this._renderMetallicGoldText(
      ctx,
      mainTitle,
      leftCx,
      titleY,
      titleFontSize,
      '"Cinzel", "Playfair Display", Georgia, serif',
      'center'
    );

    // Glowing Signature Script: ONLY render if kicker is explicitly provided or for family jubilee default
    const scriptKicker = (this.data.kicker !== undefined && this.data.kicker !== null && String(this.data.kicker).trim() !== '') 
      ? String(this.data.kicker).trim() 
      : (isFamily && !this.data.title ? "Golden 50th" : "");

    if (scriptKicker) {
      let scriptFontSize = 88;
      if (scriptKicker.length > 14) {
        scriptFontSize = Math.round(scriptFontSize * 0.72);
      } else if (scriptKicker.length > 9) {
        scriptFontSize = Math.round(scriptFontSize * 0.84);
      }
      const scriptY = h * 0.358;
      this._renderNeonScriptText(ctx, scriptKicker, leftCx, scriptY, scriptFontSize, '#ffffff', '#fde047', -0.08);
      this._renderLensFlareSparkle(ctx, leftCx - 115, scriptY + 22, 36);
      this._renderLensFlareSparkle(ctx, leftCx + 98, titleY - 12, 32);
    }

    // Spaced Glowing White Secondary Title ("PARTY" / "BANQUET")
    const subWord = (this.data.titleSecondary || (isFamily ? 'BANQUET' : 'PARTY')).toUpperCase();
    ctx.save();
    ctx.font = '800 30px "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    ctx.shadowColor = '#ffffff';
    ctx.shadowBlur = 14;
    this._drawTrackedText(ctx, subWord, leftCx, h * 0.495, 16);
    ctx.restore();

    // Glowing Horizontal Optical Divider Line
    this._renderGlowingDivider(ctx, leftCx, h * 0.522, w * 0.36);

    // "PARTY STARTS AT 9PM" / "CELEBRATION BEGINS AT 4PM"
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = 'italic 700 20px "Montserrat", sans-serif';
    ctx.fillStyle = '#f8fafc';
    const timeLead = isFamily ? 'RECEPTION & BANQUET AT' : 'PARTY STARTS AT';
    this._drawTrackedText(ctx, `${timeLead} ${(this.data.time || (isFamily ? '4:00 PM' : '9PM')).toUpperCase()}`, leftCx, h * 0.552, 3);
    ctx.restore();

    // 5. Structured 2-Column Editorial Grid: VENUE AT | ... and DRESS CODE | ...
    const splitX = leftCx - 24;
    let gridY = h * 0.615;

    ctx.save();
    // Row 1: VENUE AT
    ctx.textAlign = 'right';
    ctx.font = '500 30px "Oswald", "Montserrat", sans-serif';
    ctx.fillStyle = '#eab308';
    ctx.fillText(isFamily ? 'GALA' : 'VENUE', splitX, gridY);
    ctx.fillText('AT', splitX, gridY + 32);

    ctx.textAlign = 'left';
    ctx.font = '800 21px "Montserrat", sans-serif';
    ctx.fillStyle = '#eab308';
    const rawVenueTop = (this.data.venue || (isFamily ? 'THE SAVOY BALLROOM' : 'TAPE LONDON')).toUpperCase().trim();
    ctx.fillText(rawVenueTop, splitX + 22, gridY - 4);

    ctx.font = '600 17px "Montserrat", sans-serif';
    ctx.fillStyle = '#f8fafc';
    const rawAddrFull = (this.data.address || (isFamily ? 'STRAND, LONDON WC2R 0EZ' : '17 HANOVER SQUARE, LONDON W1S 1HU')).toUpperCase().trim();
    const addrParts = rawAddrFull.split(',').map((s) => s.trim()).filter(Boolean);
    const filteredAddrParts = (addrParts.length === 1 && addrParts[0] === rawVenueTop) ? [] : addrParts;
    filteredAddrParts.slice(0, 3).forEach((part, idx) => {
      ctx.fillText(part, splitX + 22, gridY + 20 + idx * 20);
    });

    // Row 2: DRESS CODE
    gridY += 105;
    ctx.textAlign = 'right';
    ctx.font = '500 30px "Oswald", "Montserrat", sans-serif';
    ctx.fillStyle = '#eab308';
    ctx.fillText('DRESS', splitX, gridY);
    ctx.fillText('CODE', splitX, gridY + 32);

    ctx.textAlign = 'left';
    const dressTokens = (this.data.dressCode || (isFamily ? 'TRADITIONAL ROYAL GOLD & IVORY • CELEBRATION ATTIRE' : 'ALL BLACK AFFAIR • WEAR ALL BLACK')).toUpperCase().split(/•|\|/);
    ctx.font = '800 21px "Montserrat", sans-serif';
    ctx.fillStyle = '#eab308';
    ctx.fillText(((this.strictContent ? (dressTokens[0] || '') : (dressTokens[0] || (isFamily ? 'TRADITIONAL ROYAL GOLD' : 'ALL BLACK AFFAIR')))).trim(), splitX + 22, gridY - 4);

    ctx.font = '600 17px "Montserrat", sans-serif';
    ctx.fillStyle = '#f8fafc';
    ctx.fillText(((this.strictContent ? (dressTokens[1] || '') : (dressTokens[1] || (isFamily ? 'WEAR ROYAL GOLD & IVORY' : 'WEAR ALL BLACK')))).trim(), splitX + 22, gridY + 22);

    // Bottom-Left RSVP Block
    const rsvpY = h * 0.825;
    ctx.font = 'italic 400 44px "Great Vibes", "Alex Brush", cursive';
    ctx.fillStyle = '#ffffff';
    ctx.fillText('rsvp', leftCx - w * 0.16, rsvpY);

    ctx.font = '700 20px "Montserrat", sans-serif';
    ctx.fillStyle = '#eab308';
    ctx.fillText((this.data.rsvpContact || (isFamily ? 'FAMILY PORTAL RSVP • +44 7700 900200' : 'MARCH 31, 2026 • 020 7946 0192')).toUpperCase(), leftCx - w * 0.16, rsvpY + 28);
    ctx.restore();

    // Wax Crest Seal for Family Heirloom Gala
    if (isFamily) {
      this._renderWaxCrestSeal(ctx, leftCx - w * 0.15, h * 0.90, 42, 'HERITAGE', 'GALA SEAL', '#064e3b', '#eab308');
    }

    if (this.data.qrUrl) {
      this._renderScannableQrBadge(ctx, w - 145, h - 155, 96, this.data.qrUrl, 'dark');
    }
  }

  /* =========================================================================
   * ARCHETYPE 4: 3D CHROME & BOTANICAL OVERLAP (Sample #4 — "Alisa")
   * ========================================================================= */
  _renderArchetypeFloralChrome(ctx, w, h) {
    const studioBg = this.backgroundImage || this.studioAssetCache.bg_crimson_velvet_floral;

    // 1. Photorealistic Crimson & Midnight Plum Velvet Backdrop
    if (studioBg) {
      this._drawCoverImage(ctx, studioBg, w, h, 1.0);
    } else {
      const bgGrad = ctx.createLinearGradient(0, 0, w, h);
      bgGrad.addColorStop(0, '#2e071d');
      bgGrad.addColorStop(0.5, '#190a33');
      bgGrad.addColorStop(1, '#090418');
      ctx.fillStyle = bgGrad;
      ctx.fillRect(0, 0, w, h);
    }

    // 2. Upper-Left Hero Subject Cutout (Joyful Woman in Red Satin Shirt)
    const defaultSubject = this.studioAssetCache.subject_birthday_woman || null;
    this._renderStagedSubject(ctx, w, h, {
      defaultAsset: defaultSubject,
      alignX: 0.31,
      alignY: 0.06,
      widthRatio: 0.56,
      ghostEcho: false,
      rimColor: '#ec4899'
    });

    // 3. Upper-Right Editorial Stack: Script Greeting + Giant Date + Neon Script "Party"
    const rightCx = w * 0.74;

    const greetLines = (this.data.kicker || (this.data.title || 'Happy\nBirthday')).split(/\r\n|\r|\n/);
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '400 60px "Great Vibes", "Sacramento", "Alex Brush", cursive';
    ctx.fillStyle = '#ffffff';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.75)';
    ctx.shadowBlur = 12;
    greetLines.slice(0, 3).forEach((line, idx) => {
      ctx.fillText(line.trim(), rightCx, h * 0.095 + idx * 52);
    });
    ctx.restore();

    // Giant Serif Date ("28 / NOV / 09 PM")
    const dateParts = (this.data.date || '28 NOV').toUpperCase().split(/\s+/);
    const dayNum = (this.strictContent ? (dateParts[0] || '') : (dateParts[0] || '28'));
    const monthStr = (this.strictContent ? (dateParts[1] || '') : (dateParts[1] || 'NOV'));
    const timeStr = (this.data.time || '09 PM').toUpperCase();

    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#ffffff';
    ctx.shadowColor = 'rgba(236, 72, 153, 0.45)';
    ctx.shadowBlur = 18;
    ctx.font = '900 155px "Playfair Display", Georgia, serif';
    ctx.fillText(dayNum, rightCx, h * 0.355);

    ctx.font = '700 64px "Playfair Display", Georgia, serif';
    this._drawTrackedText(ctx, monthStr, rightCx, h * 0.415, 6);

    ctx.font = '700 44px "Playfair Display", Georgia, serif';
    this._drawTrackedText(ctx, timeStr, rightCx, h * 0.462, 4);
    ctx.restore();

    // Neon Pink Script ("Party")
    this._renderNeonScriptText(
      ctx,
      this.data.titleSecondary || 'Party',
      rightCx,
      h * 0.565,
      102,
      '#fb7185',
      '#e11d48',
      -0.06
    );

    // 4. LAYER A (UNDERNEATH FOREGROUND PEONIES):
    // Full-Width Tall Condensed 3D Metallic Silver/Chrome Headline ("BIRTHDAY")
    const bigWord = (this.data.title || 'BIRTHDAY').toUpperCase();
    this._renderChrome3DText(ctx, bigWord, w / 2, h * 0.79, w * 0.92);

    // 5. LAYER B (FOREGROUND OVERLAP ON TOP OF 3D CHROME TEXT):
    // Photorealistic Crimson Peony Bouquet overlapping the bottom-left/centre of "BIRTHDAY"
    const peonyAsset = this.studioAssetCache.fg_crimson_peonies;
    if (peonyAsset) {
      ctx.save();
      ctx.shadowColor = 'rgba(0, 0, 0, 0.75)';
      ctx.shadowBlur = 22;
      ctx.shadowOffsetY = 8;
      const pw = w * 0.68;
      const ph = pw * (peonyAsset.height / Math.max(1, peonyAsset.width));
      ctx.drawImage(peonyAsset, -w * 0.04, h * 0.82 - ph * 0.62, pw, ph);
      ctx.restore();
    } else {
      this._renderPeonyCluster(ctx, w * 0.14, h * 0.80, 125, '#be123c', '#fb7185');
      this._renderPeonyCluster(ctx, w * 0.30, h * 0.83, 105, '#9f1239', '#f43f5e');
      this._renderPeonyCluster(ctx, w * 0.46, h * 0.79, 118, '#881337', '#e11d48');
    }

    // 6. Bottom-Right DJ Billing & Perks + Full-Width Clean Footer Bar
    ctx.save();
    ctx.textAlign = 'right';
    ctx.font = '900 38px "Playfair Display", Georgia, serif';
    ctx.fillStyle = '#fb7185';
    ctx.fillText((this.data.musicBy || 'DJ MAX GOSHA').toUpperCase(), w * 0.94, h * 0.875);

    const perksLine = Array.isArray(this.data.perks)
      ? this.data.perks.join('  |  ')
      : String(this.data.perks || 'LIVE MUSIC | DRINKS | SHISHA LOUNGE');
    ctx.font = '700 22px "Playfair Display", Georgia, serif';
    ctx.fillStyle = '#ffffff';
    ctx.fillText(perksLine.toUpperCase(), w * 0.94, h * 0.912);

    ctx.textAlign = 'center';
    ctx.font = '600 18px "Montserrat", sans-serif';
    ctx.fillStyle = '#e2e8f0';
    const footerCopy = (this.data.footerStrip || 'ENTRY £15  •  DOORS OPEN 9PM  •  FREE PARKING  •  2 DRINKS FREE').toUpperCase();
    this._drawTrackedText(ctx, footerCopy, w / 2, h * 0.962, 3);
    ctx.restore();
  }

  /* =========================================================================
   * ARCHETYPE 5: KINETIC CLUB TUNNEL + TEXT-BEHIND-SUBJECT (Sample #5 — "Friday")
   * ========================================================================= */
  _renderArchetypeClubTunnel(ctx, w, h) {
    const cx = w / 2;
    const studioBg = this.backgroundImage || this.studioAssetCache.bg_club_tunnel;

    // 1. Photorealistic 3D Perspective Orange/Gold Laser Club Tunnel
    if (studioBg) {
      this._drawCoverImage(ctx, studioBg, w, h, 1.0);
      // Dark vignette at top and bottom for typography punch
      const vig = ctx.createLinearGradient(0, 0, 0, h);
      vig.addColorStop(0, 'rgba(4, 2, 0, 0.72)');
      vig.addColorStop(0.22, 'rgba(0, 0, 0, 0.15)');
      vig.addColorStop(0.68, 'rgba(0, 0, 0, 0.35)');
      vig.addColorStop(1, 'rgba(3, 1, 0, 0.92)');
      ctx.fillStyle = vig;
      ctx.fillRect(0, 0, w, h);
    } else {
      const bgGrad = ctx.createRadialGradient(cx, h * 0.38, 20, cx, h * 0.45, h * 0.75);
      bgGrad.addColorStop(0, '#7c2d12');
      bgGrad.addColorStop(0.45, '#1c0a03');
      bgGrad.addColorStop(1, '#040201');
      ctx.fillStyle = bgGrad;
      ctx.fillRect(0, 0, w, h);
    }

    // Top 3-Column Header Bar: [RSVP ONLY] | [YOUR CLUB PRESENTS] | [QR CODE]
    ctx.save();
    ctx.fillStyle = '#ffffff';
    ctx.font = '700 32px "Oswald", "Anton", sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText('RSVP', w * 0.06, h * 0.065);
    ctx.fillText('ONLY', w * 0.06, h * 0.098);

    ctx.textAlign = 'center';
    ctx.font = '700 26px "Oswald", "Montserrat", sans-serif';
    const clubLabel = (this.data.badge || 'YOUR CLUB PRESENTS').toUpperCase();
    this._drawTrackedText(ctx, clubLabel, cx, h * 0.08, 3);
    ctx.restore();

    if (this.data.qrUrl) {
      this._renderScannableQrBadge(ctx, w - 138, h * 0.04, 84, this.data.qrUrl, 'light_compact');
    }

    // 2. LAYER A (BEHIND SUBJECT): Giant Stacked Gold Condensed "FRIDAY / FRIDAY"
    const bgWord = (this.data.watermarkText || 'FRIDAY').toUpperCase();
    this._renderCondensedGoldBackdropText(ctx, bgWord, cx, h * 0.335, 205, 1.0, 1.18);
    this._renderCondensedGoldBackdropText(ctx, bgWord, cx, h * 0.505, 205, 1.0, 1.18);

    // 3. LAYER B (SUBJECT CUTOUT IN FRONT OF 'FRIDAY' WATERMARK)
    const defaultSubject = this.studioAssetCache.subject_club_dj || null;
    this._renderStagedSubject(ctx, w, h, {
      defaultAsset: defaultSubject,
      alignX: 0.5,
      alignY: 0.16,
      widthRatio: 0.54,
      ghostEcho: false,
      rimColor: '#f59e0b'
    });

    // 4. LAYER C (FOREGROUND HEADLINE): Wide 3D Gold "NIGHT" + Glowing White Script "Party"
    const mainBottomWord = (this.data.title || this.data.titleSecondary || 'NIGHT').toUpperCase();
    ctx.save();
    ctx.translate(cx, h * 0.77);
    ctx.scale(1.18, 0.96);
    this._renderMetallicGoldText(ctx, mainBottomWord, 0, 0, 132, '"Montserrat", "Anton", sans-serif', 'center');
    ctx.restore();

    // Glowing White Neon Script ("Party") overlapping top of "NIGHT"
    this._renderNeonScriptText(ctx, this.data.kicker || 'Party', cx, h * 0.675, 122, '#ffffff', '#f59e0b', -0.05);

    // 5. Flanked Footer Strip: [12 PM] | MUSIC // DANCE // VIP TABLES + Venue | [27 AUG]
    const footerY = h * 0.875;
    ctx.save();

    // Left flanked time box
    const leftBoxX = w * 0.10;
    ctx.strokeStyle = '#eab308';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(leftBoxX - 42, footerY - 38);
    ctx.lineTo(leftBoxX + 42, footerY - 38);
    ctx.moveTo(leftBoxX - 42, footerY + 42);
    ctx.lineTo(leftBoxX + 42, footerY + 42);
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.font = '900 32px "Montserrat", sans-serif';
    ctx.fillStyle = '#eab308';
    const timeTokens = (this.data.time || '12 PM').toUpperCase().split(/\s+/);
    ctx.fillText((this.strictContent ? (timeTokens[0] || '') : (timeTokens[0] || '12')), leftBoxX, footerY - 2);
    ctx.font = '800 22px "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    ctx.fillText((this.strictContent ? (timeTokens[1] || '') : (timeTokens[1] || 'PM')), leftBoxX, footerY + 26);

    // Right flanked date box
    const rightBoxX = w * 0.90;
    ctx.beginPath();
    ctx.moveTo(rightBoxX - 42, footerY - 38);
    ctx.lineTo(rightBoxX + 42, footerY - 38);
    ctx.moveTo(rightBoxX - 42, footerY + 42);
    ctx.lineTo(rightBoxX + 42, footerY + 42);
    ctx.stroke();

    const dateTokens = (this.data.date || '27 AUG').toUpperCase().split(/\s+/);
    ctx.font = '900 32px "Montserrat", sans-serif';
    ctx.fillStyle = '#eab308';
    ctx.fillText((this.strictContent ? (dateTokens[0] || '') : (dateTokens[0] || '27')), rightBoxX, footerY - 2);
    ctx.font = '800 22px "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    ctx.fillText((this.strictContent ? (dateTokens[1] || '') : (dateTokens[1] || 'AUG')), rightBoxX, footerY + 26);

    // Centre Perks + Compact Single-Line Venue & Phone
    const perksArr = Array.isArray(this.data.perks)
      ? this.data.perks
      : String(this.data.perks || 'MUSIC, DANCE, VIP TABLES').split(',');
    const perksText = perksArr.map((s) => s.trim().toUpperCase()).filter(Boolean).join(' // ');

    ctx.font = '900 26px "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    ctx.fillText(perksText, cx, footerY - 2);

    ctx.font = '700 16px "Montserrat", sans-serif';
    ctx.fillStyle = '#f59e0b';
    const venueShort = [this.data.venue, this.data.rsvpContact].filter(Boolean).join('  •  ').toUpperCase();
    ctx.fillText(venueShort, cx, footerY + 28);
    ctx.restore();
  }

  /* =========================================================================
   * ARCHETYPE 6: SECTOR AUTHORITY & COMPLIANCE GRID (BCP, FinTech, ExecMind, etc.)
   * ========================================================================= */
  _renderArchetypeSectorGrid(ctx, w, h) {
    const cx = w / 2;
    const isLight = this.theme === AtgUniversalPosterEngine.THEMES.BCP_CLINICAL_EXCELLENCE;
    const isUrgent = this.theme === AtgUniversalPosterEngine.THEMES.BCP_URGENT_SHIFT;
    const isFintech = this.theme === AtgUniversalPosterEngine.THEMES.FINTECH_SME_CAPITAL;

    // 1. Rich Multi-Stop Executive Gradient Backdrop
    const grad = ctx.createLinearGradient(0, 0, w, h);
    if (isUrgent) {
      grad.addColorStop(0, '#082f49');
      grad.addColorStop(0.55, '#0f172a');
      grad.addColorStop(1, '#020617');
    } else if (isLight) {
      grad.addColorStop(0, '#f8fafc');
      grad.addColorStop(0.5, '#e0f2fe');
      grad.addColorStop(1, '#bae6fd');
    } else if (isFintech) {
      grad.addColorStop(0, '#064e3b');
      grad.addColorStop(0.55, '#022c22');
      grad.addColorStop(1, '#011510');
    } else {
      grad.addColorStop(0, '#0f172a');
      grad.addColorStop(0.5, '#1e1b4b');
      grad.addColorStop(1, '#020617');
    }
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, w, h);

    // Subtle radial luxury glow
    const glow = ctx.createRadialGradient(cx, h * 0.32, 20, cx, h * 0.32, w * 0.55);
    glow.addColorStop(0, isUrgent ? 'rgba(244, 63, 94, 0.22)' : (isFintech ? 'rgba(16, 185, 129, 0.22)' : 'rgba(234, 179, 8, 0.18)'));
    glow.addColorStop(1, 'rgba(0, 0, 0, 0)');
    ctx.fillStyle = glow;
    ctx.fillRect(0, 0, w, h);

    // Double Architectural Border Frame
    const accentColor = isUrgent ? '#f43f5e' : (isFintech ? '#10b981' : (isLight ? '#0284c7' : '#eab308'));
    ctx.save();
    ctx.strokeStyle = accentColor;
    ctx.lineWidth = 3;
    ctx.strokeRect(36, 36, w - 72, h - 72);
    ctx.strokeStyle = isLight ? 'rgba(15, 23, 42, 0.14)' : 'rgba(255, 255, 255, 0.14)';
    ctx.lineWidth = 1.5;
    ctx.strokeRect(50, 50, w - 100, h - 100);
    ctx.restore();

    // 2. Top Sector Authority Pill
    ctx.save();
    ctx.fillStyle = isUrgent ? '#e11d48' : (isLight ? '#0284c7' : 'rgba(15, 23, 42, 0.85)');
    this._roundRect(ctx, cx - w * 0.36, h * 0.065, w * 0.72, 54, 27);
    ctx.fill();
    ctx.strokeStyle = accentColor;
    ctx.lineWidth = 2;
    ctx.stroke();

    ctx.fillStyle = '#ffffff';
    ctx.font = '800 20px "Montserrat", sans-serif';
    ctx.textAlign = 'center';
    this._drawTrackedText(ctx, (this.data.badge || 'OFFICIAL SECTOR BRIEFING').toUpperCase(), cx, h * 0.065 + 34, 3);

    ctx.fillStyle = isLight ? '#0369a1' : '#38bdf8';
    ctx.font = '700 22px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.kicker || '').toUpperCase(), cx, h * 0.135, 4);
    ctx.restore();

    // 3. Main Display Headline
    const titleLines = String(this.data.title || 'EXECUTIVE SUMMIT').toUpperCase().split(/\r\n|\r|\n/);
    const startY = h * 0.25;
    titleLines.slice(0, 3).forEach((line, idx) => {
      if (isLight) {
        this._renderCondensedBlockText(ctx, line, cx, startY + idx * 105, 92, '#0f172a', '#1e293b', '#94a3b8', 1.0, 1.05);
      } else {
        this._renderMetallicGoldText(ctx, line, cx, startY + idx * 105, 88, '"Montserrat", "Oswald", sans-serif', 'center');
      }
    });

    // Subtitle
    const subY = startY + Math.min(3, titleLines.length) * 105 - 10;
    if (this.data.subtitle) {
      ctx.save();
      ctx.textAlign = 'center';
      ctx.font = '600 26px "Montserrat", sans-serif';
      ctx.fillStyle = isLight ? '#334155' : '#cbd5e1';
      ctx.fillText(this.data.subtitle, cx, subY);
      ctx.restore();
    }

    // 4. High-Impact Metric Badge (Pay Rate / Representative APR / CPD Credits)
    if (this.data.accentMetric) {
      const mY = h * 0.49;
      ctx.save();
      ctx.fillStyle = isUrgent ? '#f59e0b' : (isFintech ? '#10b981' : '#38bdf8');
      this._roundRect(ctx, cx - 290, mY, 580, 74, 16);
      ctx.fill();

      ctx.fillStyle = '#090d16';
      ctx.font = '900 34px "Montserrat", sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(this.data.accentMetric.toUpperCase(), cx, mY + 49);
      ctx.restore();
    }

    // 5. Date, Time & Venue Card
    const cardY = h * 0.60;
    ctx.save();
    ctx.fillStyle = isLight ? 'rgba(255, 255, 255, 0.88)' : 'rgba(15, 23, 42, 0.84)';
    this._roundRect(ctx, w * 0.1, cardY, w * 0.8, 220, 20);
    ctx.fill();
    ctx.strokeStyle = accentColor;
    ctx.lineWidth = 2;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = isLight ? '#0f172a' : '#fef08a';
    ctx.font = '800 28px "Montserrat", sans-serif';
    ctx.fillText([this.data.date, this.data.time].filter(Boolean).join('  •  ').toUpperCase(), cx, cardY + 56);

    ctx.fillStyle = isLight ? '#0f172a' : '#ffffff';
    ctx.font = '900 30px "Montserrat", sans-serif';
    ctx.fillText((this.data.venue || '').toUpperCase(), cx, cardY + 112);

    ctx.fillStyle = isLight ? '#475569' : '#94a3b8';
    ctx.font = '600 21px "Montserrat", sans-serif';
    ctx.fillText(this.data.address || '', cx, cardY + 150);

    if (this.data.perks && this.data.perks.length) {
      const pList = Array.isArray(this.data.perks) ? this.data.perks.join('  •  ') : String(this.data.perks);
      ctx.fillStyle = isUrgent ? '#fb7185' : (isFintech ? '#34d399' : '#38bdf8');
      ctx.font = '800 20px "Montserrat", sans-serif';
      ctx.fillText(pList.toUpperCase(), cx, cardY + 192);
    }
    ctx.restore();

    if (this.data.qrUrl) {
      this._renderScannableQrBadge(ctx, w - 175, h * 0.79, 110, this.data.qrUrl, isLight ? 'light' : 'dark');
    }

    // 6. Mandatory Statutory Regulatory Notice (CQC / FCA / Chatham House / HMRC)
    let disclaimer = this.data.regulatoryNotice || '';
    if (!disclaimer) {
      if (this.theme === AtgUniversalPosterEngine.THEMES.FINTECH_SME_CAPITAL) {
        disclaimer = 'Capital at risk. Authorised and regulated by the Financial Conduct Authority (FCA). Representative APR applies.';
      } else if (
        this.theme === AtgUniversalPosterEngine.THEMES.BCP_URGENT_SHIFT ||
        this.theme === AtgUniversalPosterEngine.THEMES.BCP_CLINICAL_EXCELLENCE
      ) {
        disclaimer = 'Best Care Professionals Ltd. Registered & regulated by the Care Quality Commission (CQC). Reg 18/19 compliant.';
      } else if (this.theme === AtgUniversalPosterEngine.THEMES.EXEC_SAPPHIRE_SUMMIT) {
        disclaimer = 'Strictly held under the Chatham House Rule. Unauthorised recording or broadcasting is prohibited.';
      }
    }

    if (disclaimer) {
      ctx.save();
      ctx.textAlign = 'center';
      ctx.font = '600 16px "Montserrat", sans-serif';
      ctx.fillStyle = isLight ? '#475569' : '#94a3b8';
      ctx.fillText(disclaimer, cx, h - 56);
      ctx.restore();
    }
  }

  /* =========================================================================
   * ARCHETYPE 16: ILLUMINATED ACRYLIC KEEPSAKE BLOCK (3D Crystal Keepsake Plaque)
   * High-reality transparent acrylic block with beveled edges, internal depth,
   * floating subject, halo chamber lighting, watercolor flora & elegant typography.
   * ========================================================================= */
  _renderArchetypeAcrylicKeepsakeBlock(ctx, w, h) {
    const cx = w / 2;
    const isTall = h >= 1400; // e.g. 9:16 story/flyer or A4 poster

    // 1. Studio Backdrop: Warm Champagne Frosted Ambient Lighting
    const studioGrad = ctx.createRadialGradient(cx, h * 0.45, 100, cx, h * 0.45, Math.max(w, h) * 0.75);
    studioGrad.addColorStop(0, '#fffbf5');
    studioGrad.addColorStop(0.45, '#faf5ed');
    studioGrad.addColorStop(0.85, '#f1e8da');
    studioGrad.addColorStop(1, '#e8dcce');
    ctx.fillStyle = studioGrad;
    ctx.fillRect(0, 0, w, h);

    // Warm top ambient glow
    const topAmbient = ctx.createLinearGradient(0, 0, 0, h * 0.4);
    topAmbient.addColorStop(0, 'rgba(255, 255, 255, 0.85)');
    topAmbient.addColorStop(1, 'rgba(255, 255, 255, 0)');
    ctx.fillStyle = topAmbient;
    ctx.fillRect(0, 0, w, h * 0.4);

    // 2. Acrylic Plaque Geometry Calculation
    const plaqueW = Math.min(w * 0.90, 960);
    const plaqueH = isTall ? Math.min(h * 0.54, 760) : Math.min(h * 0.72, 620);
    const plaqueX = (w - plaqueW) / 2;
    const plaqueY = isTall ? (h * 0.18) : (h * 0.12);
    const plaqueR = 28;

    // Contact surface shadow under the plaque (ground plane reflection)
    const tableShadowY = plaqueY + plaqueH + 18;
    ctx.save();
    const tableShadow = ctx.createRadialGradient(cx, tableShadowY, 40, cx, tableShadowY, plaqueW * 0.52);
    tableShadow.addColorStop(0, 'rgba(100, 80, 60, 0.32)');
    tableShadow.addColorStop(0.5, 'rgba(120, 100, 80, 0.14)');
    tableShadow.addColorStop(1, 'rgba(120, 100, 80, 0)');
    ctx.fillStyle = tableShadow;
    ctx.beginPath();
    ctx.ellipse(cx, tableShadowY, plaqueW * 0.52, 28, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();

    // 3. Thick 3D Acrylic Base & Lower Edge Refraction LED Glow
    ctx.save();
    const bottomLedGlow = ctx.createRadialGradient(cx, plaqueY + plaqueH, 20, cx, plaqueY + plaqueH, plaqueW * 0.48);
    bottomLedGlow.addColorStop(0, 'rgba(254, 240, 138, 0.95)');
    bottomLedGlow.addColorStop(0.35, 'rgba(253, 230, 138, 0.75)');
    bottomLedGlow.addColorStop(0.7, 'rgba(251, 191, 36, 0.35)');
    bottomLedGlow.addColorStop(1, 'rgba(251, 191, 36, 0)');
    ctx.fillStyle = bottomLedGlow;
    ctx.beginPath();
    ctx.ellipse(cx, plaqueY + plaqueH, plaqueW * 0.5, 34, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();

    // 4. Photorealistic Acrylic Block Face
    const crystalAsset = this.backgroundImage || this.studioAssetCache.bg_acrylic_crystal_block || null;
    ctx.save();
    this._roundRect(ctx, plaqueX, plaqueY, plaqueW, plaqueH, plaqueR);
    ctx.clip();

    if (crystalAsset) {
      ctx.drawImage(crystalAsset, plaqueX, plaqueY, plaqueW, plaqueH);
    } else {
      const glassGrad = ctx.createLinearGradient(plaqueX, plaqueY, plaqueX + plaqueW, plaqueY + plaqueH);
      glassGrad.addColorStop(0, 'rgba(255, 255, 255, 0.92)');
      glassGrad.addColorStop(0.35, 'rgba(255, 253, 248, 0.78)');
      glassGrad.addColorStop(0.7, 'rgba(253, 246, 235, 0.82)');
      glassGrad.addColorStop(1, 'rgba(247, 237, 222, 0.94)');
      ctx.fillStyle = glassGrad;
      ctx.fillRect(plaqueX, plaqueY, plaqueW, plaqueH);
    }

    // Rear chamber halo illumination behind subject
    const haloX = plaqueX + plaqueW * 0.46;
    const haloY = plaqueY + plaqueH * 0.52;
    const halo = ctx.createRadialGradient(haloX, haloY, 20, haloX, haloY, plaqueH * 0.44);
    halo.addColorStop(0, 'rgba(255, 255, 255, 0.95)');
    halo.addColorStop(0.4, 'rgba(255, 255, 255, 0.65)');
    halo.addColorStop(0.8, 'rgba(254, 243, 199, 0.25)');
    halo.addColorStop(1, 'rgba(254, 243, 199, 0)');
    ctx.fillStyle = halo;
    ctx.beginPath();
    ctx.arc(haloX, haloY, plaqueH * 0.44, 0, Math.PI * 2);
    ctx.fill();

    // 5. User Floating Subject Cutout (if uploaded)
    if (this.processedSubjectCanvas || this.subjectImage) {
      const sub = this.processedSubjectCanvas || this.subjectImage;
      const subMaxH = plaqueH * 0.64;
      const subMaxW = plaqueW * 0.48;
      const scale = Math.min(subMaxW / (sub.width || 1), subMaxH / (sub.height || 1));
      const drawW = (sub.width || subMaxW) * scale;
      const drawH = (sub.height || subMaxH) * scale;
      const subX = haloX - drawW / 2;
      const subY = haloY - drawH / 2;

      ctx.save();
      ctx.shadowColor = 'rgba(120, 100, 80, 0.45)';
      ctx.shadowBlur = 24;
      ctx.shadowOffsetX = 12;
      ctx.shadowOffsetY = 16;
      ctx.drawImage(sub, subX, subY, drawW, drawH);
      ctx.restore();
    }

    // Specular diagonal glare sweep across the acrylic face
    const glare = ctx.createLinearGradient(plaqueX, plaqueY, plaqueX + plaqueW * 0.7, plaqueY + plaqueH);
    glare.addColorStop(0, 'rgba(255, 255, 255, 0.45)');
    glare.addColorStop(0.18, 'rgba(255, 255, 255, 0.12)');
    glare.addColorStop(0.38, 'rgba(255, 255, 255, 0)');
    glare.addColorStop(0.75, 'rgba(255, 255, 255, 0.08)');
    glare.addColorStop(1, 'rgba(255, 255, 255, 0.28)');
    ctx.fillStyle = glare;
    ctx.fillRect(plaqueX, plaqueY, plaqueW, plaqueH);

    // Beveled internal border reflection
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.75)';
    ctx.lineWidth = 2;
    this._roundRect(ctx, plaqueX + 8, plaqueY + 8, plaqueW - 16, plaqueH - 16, plaqueR - 4);
    ctx.stroke();

    ctx.restore(); // Exit plaque clip

    // 6. Exterior Crisp Bevel & Outer Chamfer Frame
    ctx.save();
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.95)';
    ctx.lineWidth = 3.5;
    this._roundRect(ctx, plaqueX, plaqueY, plaqueW, plaqueH, plaqueR);
    ctx.stroke();

    ctx.strokeStyle = 'rgba(180, 160, 140, 0.55)';
    ctx.lineWidth = 1.5;
    this._roundRect(ctx, plaqueX + 1.5, plaqueY + 1.5, plaqueW - 3, plaqueH - 3, plaqueR);
    ctx.stroke();
    ctx.restore();

    // 7. Dynamic Inscriptions & Luxury Keepsake Typography
    ctx.save();
    const kickerText = this.data.kicker || this.data.badge || 'HAPPY 1ST BIRTHDAY';
    const titleText = this.data.title || 'ISABELLE';
    const subtitleText = this.data.subtitle || 'Illuminated 3D Acrylic Keepsake';

    // Top script inscription on plaque
    ctx.font = '700 36px "Caveat", "Playfair Display", cursive, serif';
    ctx.fillStyle = '#ec4899';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.fillText(kickerText, cx, plaqueY + 34);

    // Right-aligned or prominent title name
    ctx.font = '800 52px "Playfair Display", Georgia, serif';
    ctx.fillStyle = '#4f46e5';
    ctx.textAlign = 'right';
    ctx.fillText(titleText, plaqueX + plaqueW - 56, plaqueY + plaqueH * 0.44);

    ctx.font = '600 20px "Montserrat", sans-serif';
    ctx.fillStyle = '#64748b';
    ctx.fillText(subtitleText, plaqueX + plaqueW - 56, plaqueY + plaqueH * 0.44 + 44);
    ctx.restore();

    // 8. Event Information Sub-Card / Bottom Presentation Bar
    const infoCardY = isTall ? (plaqueY + plaqueH + 48) : (plaqueY + plaqueH + 26);
    const infoCardW = plaqueW;
    const infoCardH = isTall ? (h - infoCardY - 60) : (h - infoCardY - 24);

    if (infoCardH > 100) {
      ctx.save();
      ctx.fillStyle = 'rgba(255, 255, 255, 0.88)';
      this._roundRect(ctx, plaqueX, infoCardY, infoCardW, infoCardH, 20);
      ctx.fill();
      ctx.strokeStyle = 'rgba(212, 175, 55, 0.45)';
      ctx.lineWidth = 1.5;
      ctx.stroke();

      ctx.fillStyle = '#b45309';
      ctx.font = '800 14px "Montserrat", sans-serif';
      ctx.textAlign = 'left';
      ctx.fillText('CELEBRATION DISPATCH & RSVP DETAILS', plaqueX + 28, infoCardY + 34);

      const dateStr = [this.data.date, this.data.time].filter(Boolean).join('  •  ');
      ctx.fillStyle = '#0f172a';
      ctx.font = '700 22px "Montserrat", sans-serif';
      ctx.fillText(`📅  ${dateStr || 'Saturday 24th October 2026 • 2:00 PM'}`, plaqueX + 28, infoCardY + 72);

      const venueStr = [this.data.venue, this.data.address].filter(Boolean).join('  •  ');
      ctx.fillStyle = '#334155';
      ctx.font = '600 17px "Montserrat", sans-serif';
      this._wrapText(ctx, `📍  ${venueStr || 'The Grand Pavilion • London & Kent'}`, plaqueX + 28, infoCardY + 110, infoCardW - 180, 24);

      if (this.data.hosts) {
        ctx.fillStyle = '#64748b';
        ctx.font = 'italic 16px "Playfair Display", Georgia, serif';
        ctx.fillText(`Hosted with love by ${this.data.hosts}`, plaqueX + 28, infoCardY + infoCardH - 24);
      }

      if (this.data.qrUrl) {
        const qrSize = Math.min(infoCardH - 40, 110);
        this._renderScannableQrBadge(ctx, plaqueX + infoCardW - qrSize - 24, infoCardY + (infoCardH - qrSize) / 2, qrSize, this.data.qrUrl, 'light_compact');
      }
      ctx.restore();
    }

    // 9. Statutory Footer / Craftsmanship Watermark
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#94a3b8';
    ctx.font = '600 14px "Montserrat", sans-serif';
    const footerText = this.data.regulatoryNotice || 'PartyPlatform • Handcrafted 3D Acrylic Keepsake Edition • Studio Quality Proof';
    ctx.fillText(footerText, cx, h - 22);
    ctx.restore();
  }

  /* =========================================================================
   * ARCHETYPE 7: URGENT CLINICAL SHIFT SURGE (Healthcare — BCP Shift Broadcast)
   * ========================================================================= */
  _renderArchetypeClinicalShiftSurge(ctx, w, h) {
    const cx = w / 2;
    const isTall = h >= 1400;
    const studioBg = this.backgroundImage || this.studioAssetCache.bg_healthcare_clinical_surge;

    // 1. Photorealistic Clinical Surge Backdrop or Clinical Midnight Navy Gradient
    if (studioBg) {
      this._drawCoverImage(ctx, studioBg, w, h, 1.0);
      const wash = ctx.createLinearGradient(0, 0, 0, h);
      wash.addColorStop(0, 'rgba(2, 24, 39, 0.78)');
      wash.addColorStop(0.35, 'rgba(5, 39, 62, 0.88)');
      wash.addColorStop(0.85, 'rgba(2, 24, 39, 0.94)');
      ctx.fillStyle = wash;
      ctx.fillRect(0, 0, w, h);
    } else {
      const bgGrad = ctx.createLinearGradient(0, 0, 0, h);
      bgGrad.addColorStop(0, '#021827');
      bgGrad.addColorStop(0.35, '#05273e');
      bgGrad.addColorStop(0.75, '#031a2c');
      bgGrad.addColorStop(1, '#010d17');
      ctx.fillStyle = bgGrad;
      ctx.fillRect(0, 0, w, h);

      // Cyan ECG Pulse Wave
      ctx.save();
      ctx.strokeStyle = 'rgba(56, 189, 248, 0.40)';
      ctx.lineWidth = 3;
      ctx.shadowColor = '#38bdf8';
      ctx.shadowBlur = 14;
      ctx.beginPath();
      const ecgY = isTall ? h * 0.44 : h * 0.46;
      ctx.moveTo(0, ecgY);
      ctx.lineTo(w * 0.28, ecgY);
      ctx.lineTo(w * 0.32, ecgY - 24);
      ctx.lineTo(w * 0.35, ecgY + 36);
      ctx.lineTo(w * 0.38, ecgY - 88);
      ctx.lineTo(w * 0.42, ecgY + 54);
      ctx.lineTo(w * 0.45, ecgY);
      ctx.lineTo(w * 0.62, ecgY);
      ctx.lineTo(w * 0.65, ecgY - 48);
      ctx.lineTo(w * 0.68, ecgY + 42);
      ctx.lineTo(w * 0.72, ecgY);
      ctx.lineTo(w, ecgY);
      ctx.stroke();
      ctx.restore();
    }

    // 2. Top Emergency Alert Ribbon
    const bannerH = isTall ? 72 : 60;
    ctx.save();
    const alertGrad = ctx.createLinearGradient(0, 0, w, 0);
    alertGrad.addColorStop(0, '#9f1239');
    alertGrad.addColorStop(0.5, '#be123c');
    alertGrad.addColorStop(1, '#9f1239');
    ctx.fillStyle = alertGrad;
    ctx.fillRect(0, 0, w, bannerH);

    [cx - 360, cx + 360].forEach((bx) => {
      ctx.fillStyle = '#fef08a';
      ctx.shadowColor = '#facc15';
      ctx.shadowBlur = 14;
      ctx.beginPath();
      ctx.arc(bx, bannerH / 2, 8, 0, Math.PI * 2);
      ctx.fill();
    });

    ctx.shadowBlur = 0;
    ctx.fillStyle = '#ffffff';
    ctx.font = '900 22px "Montserrat", sans-serif';
    ctx.textAlign = 'center';
    this._drawTrackedText(ctx, (this.data.kicker || 'URGENT CLINICAL SHIFT SURGE • IMMEDIATE DEPLOYMENT').toUpperCase(), cx, bannerH / 2 + 7, 3);
    ctx.restore();

    // 3. Clinical Agency / Trust Header Badge
    const badgeY = isTall ? bannerH + 74 : bannerH + 50;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#38bdf8';
    ctx.font = '800 24px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.badge || 'BEST CARE PROFESSIONALS • RAPID CLINICAL DEPLOYMENT').toUpperCase(), cx, badgeY, 3);
    ctx.restore();

    // 4. Main Role Headline ("URGENT RGN & / ITU NURSES")
    const titleLines = String(this.data.title || "URGENT RGN &\nITU NURSES").toUpperCase().split(/\r\n|\r|\n/);
    const startY = isTall ? badgeY + 120 : badgeY + 95;
    titleLines.slice(0, 2).forEach((line, idx) => {
      this._renderCondensedBlockText(ctx, line, cx, startY + idx * 110, 104, '#ffffff', '#e0f2fe', '#0369a1', 1.0, 1.05);
    });

    const subY = startY + Math.min(2, titleLines.length) * 110 - 10;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '600 24px "Montserrat", sans-serif';
    ctx.fillStyle = '#bae6fd';
    ctx.fillText(this.data.subtitle || 'Verified CQC Reg 18/19 Compliant Care Staffing', cx, subY);
    ctx.restore();

    // 5. High-Impact Amber/Gold Hourly Pay Rate Card
    const rateCardY = isTall ? subY + 50 : subY + 36;
    const rateW = w * 0.88;
    const rateH = isTall ? 164 : 144;
    const rateX = cx - rateW / 2;

    ctx.save();
    const rateGrad = ctx.createLinearGradient(rateX, rateCardY, rateX + rateW, rateCardY + rateH);
    rateGrad.addColorStop(0, '#b45309');
    rateGrad.addColorStop(0.45, '#f59e0b');
    rateGrad.addColorStop(1, '#d97706');
    ctx.fillStyle = rateGrad;
    this._roundRect(ctx, rateX, rateCardY, rateW, rateH, 20);
    ctx.fill();

    ctx.shadowColor = 'rgba(245, 158, 11, 0.55)';
    ctx.shadowBlur = 28;
    ctx.strokeStyle = '#fef08a';
    ctx.lineWidth = 3;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#090d16';
    ctx.font = `900 ${isTall ? 64 : 54}px "Montserrat", sans-serif`;
    ctx.fillText((this.data.accentMetric || '£38.50 – £44.00 / HR').toUpperCase(), cx, rateCardY + (isTall ? 76 : 64));

    // Instant EWA Sub-badge
    ctx.fillStyle = '#090d16';
    this._roundRect(ctx, cx - 270, rateCardY + (isTall ? 104 : 88), 540, 42, 21);
    ctx.fill();

    ctx.fillStyle = '#fef08a';
    ctx.font = '800 19px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, 'INSTANT EWA SAME-DAY PAY • ZERO AGENCY CUT', cx, rateCardY + (isTall ? 131 : 115), 2);
    ctx.restore();

    // 6. Clinical Shift Rota & Facility Grid (Dark Glass Card)
    const gridY = isTall ? rateCardY + rateH + 46 : rateCardY + rateH + 30;
    const gridW = w * 0.88;
    const gridH = isTall ? 400 : 330;
    const gridX = cx - gridW / 2;

    ctx.save();
    ctx.fillStyle = 'rgba(15, 23, 42, 0.88)';
    this._roundRect(ctx, gridX, gridY, gridW, gridH, 20);
    ctx.fill();
    ctx.strokeStyle = 'rgba(56, 189, 248, 0.45)';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    // Row A: Shift Timings & Rota
    ctx.textAlign = 'left';
    ctx.font = '700 18px "Montserrat", sans-serif';
    ctx.fillStyle = '#38bdf8';
    ctx.fillText('SHIFT TIMINGS & ROTA:', gridX + 36, gridY + 48);

    ctx.font = '900 30px "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    ctx.fillText([(this.data.time || '19:00 – 07:00 ROTA'), (this.data.date || 'NIGHTS & WEEKENDS')].join('  •  ').toUpperCase(), gridX + 36, gridY + 92);

    // Divider
    ctx.strokeStyle = 'rgba(148, 163, 184, 0.2)';
    ctx.beginPath();
    ctx.moveTo(gridX + 36, gridY + 118);
    ctx.lineTo(gridX + gridW - 36, gridY + 118);
    ctx.stroke();

    // Row B: Hospital / Care Facility & Location
    ctx.font = '700 18px "Montserrat", sans-serif';
    ctx.fillStyle = '#38bdf8';
    ctx.fillText('CLINICAL FACILITY & CLUSTER:', gridX + 36, gridY + 158);

    ctx.font = '900 28px "Montserrat", sans-serif';
    ctx.fillStyle = '#f8fafc';
    ctx.fillText((this.data.venue || 'ROYAL FREE HOSPITAL & ARIA CARE HOMES').toUpperCase(), gridX + 36, gridY + 196);

    ctx.font = '600 21px "Montserrat", sans-serif';
    ctx.fillStyle = '#94a3b8';
    ctx.fillText(this.data.address || 'Greater London & Surrey Clinical Cluster', gridX + 36, gridY + 234);

    // Divider
    ctx.beginPath();
    ctx.moveTo(gridX + 36, gridY + 258);
    ctx.lineTo(gridX + gridW - 36, gridY + 258);
    ctx.stroke();

    // Row C: Clinical Governance Lead
    ctx.font = '700 18px "Montserrat", sans-serif';
    ctx.fillStyle = '#38bdf8';
    ctx.fillText('CLINICAL GOVERNANCE & OVERSIGHT:', gridX + 36, gridY + 298);

    ctx.font = '800 23px "Montserrat", sans-serif';
    ctx.fillStyle = '#fde047';
    ctx.fillText((this.data.hosts || 'Clinical Lead: Dr. Alistair Vance (Ex-Bupa / NHS Consultant)').toUpperCase(), gridX + 36, gridY + 338);
    ctx.restore();

    // 7. Clinical Ward Specialisms & Perks
    const perksArr = Array.isArray(this.data.perks)
      ? this.data.perks
      : String(this.data.perks || 'FAST-TRACK DBS, FREE UNIFORM, MILEAGE PAID, WEEKLY CPD').split(',');
    const perksY = isTall ? gridY + gridH + 54 : gridY + gridH + 30;

    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '800 21px "Montserrat", sans-serif';
    ctx.fillStyle = '#34d399';
    ctx.fillText(perksArr.map((s) => s.trim().toUpperCase()).filter(Boolean).join('   •   '), cx, perksY);
    ctx.restore();

    // 8. Contact & Shift Booking Box + Scannable QR + CQC Wax Seal
    const rsvpY = isTall ? perksY + 68 : perksY + 44;
    ctx.save();
    ctx.textAlign = 'left';
    ctx.font = '700 18px "Montserrat", sans-serif';
    ctx.fillStyle = '#94a3b8';
    ctx.fillText('DIRECT SHIFT BOOKING / FAST-TRACK ONBOARDING:', w * 0.08, rsvpY);

    ctx.font = '900 32px "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    ctx.fillText((this.data.rsvpContact || '+44 7700 900513 • BESTCAREPROFESSIONALS.CO.UK').toUpperCase(), w * 0.08, rsvpY + 42);
    ctx.restore();

    // Green CQC Compliance Seal
    this._renderWaxCrestSeal(ctx, w * 0.28, isTall ? rsvpY + 140 : rsvpY + 24, 48, 'CQC REG 18/19', 'COMPLIANT', '#047857', '#34d399');

    if (this.data.qrUrl) {
      this._renderScannableQrBadge(ctx, w - 180, isTall ? rsvpY + 70 : rsvpY - 26, 120, this.data.qrUrl, 'dark');
    }

    // 9. Mandatory CQC Regulation 18/19 Statutory Notice
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '600 16px "Montserrat", sans-serif';
    ctx.fillStyle = '#94a3b8';
    const cqcNotice = this.data.regulatoryNotice || 'Best Care Professionals Ltd. Registered & regulated by the Care Quality Commission (CQC). Staffing ratio compliant under Regulation 18/19.';
    ctx.fillText(cqcNotice, cx, h - 44);
    ctx.restore();
  }

  /* =========================================================================
   * ARCHETYPE 8: CLINICAL RECRUITMENT & OPEN DAY (Healthcare — BCP Recruitment)
   * ========================================================================= */
  _renderArchetypeClinicalRecruitment(ctx, w, h) {
    const cx = w / 2;
    const isTall = h >= 1400;
    const studioBg = this.backgroundImage || this.studioAssetCache.bg_healthcare_clinical_surge;

    if (studioBg) {
      this._drawCoverImage(ctx, studioBg, w, h, 1.0);
      const wash = ctx.createLinearGradient(0, 0, w, h);
      wash.addColorStop(0, 'rgba(4, 47, 46, 0.82)');
      wash.addColorStop(0.5, 'rgba(15, 23, 42, 0.88)');
      wash.addColorStop(1, 'rgba(2, 6, 23, 0.94)');
      ctx.fillStyle = wash;
      ctx.fillRect(0, 0, w, h);
    } else {
      const grad = ctx.createLinearGradient(0, 0, w, h);
      grad.addColorStop(0, '#042f2e');
      grad.addColorStop(0.5, '#0f172a');
      grad.addColorStop(1, '#020617');
      ctx.fillStyle = grad;
      ctx.fillRect(0, 0, w, h);
    }

    // Double frame
    ctx.save();
    ctx.strokeStyle = '#14b8a6';
    ctx.lineWidth = 3;
    ctx.strokeRect(36, 36, w - 72, h - 72);
    ctx.restore();

    // Header Pill
    ctx.save();
    ctx.fillStyle = '#0d9488';
    this._roundRect(ctx, cx - 340, 68, 680, 56, 28);
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.font = '900 22px "Montserrat", sans-serif';
    ctx.textAlign = 'center';
    this._drawTrackedText(ctx, (this.data.badge || 'CQC SINGLE ASSESSMENT EXCELLENCE').toUpperCase(), cx, 104, 3);

    ctx.fillStyle = '#2dd4bf';
    ctx.font = '700 24px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.kicker || 'HEALTHCARE CAREERS OPEN DAY').toUpperCase(), cx, 168, 4);
    ctx.restore();

    // Headline
    const titleLines = String(this.data.title || "SENIOR CARER\nOPEN DAY").toUpperCase().split(/\r\n|\r|\n/);
    const startY = isTall ? 320 : 270;
    titleLines.slice(0, 2).forEach((line, idx) => {
      this._renderCondensedBlockText(ctx, line, cx, startY + idx * 110, 104, '#ffffff', '#ccfbf1', '#0f766e', 1.0, 1.05);
    });

    // Subtitle
    const subY = startY + Math.min(2, titleLines.length) * 110 - 10;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '600 24px "Montserrat", sans-serif';
    ctx.fillStyle = '#99f6e4';
    ctx.fillText(this.data.subtitle || 'Fast-Track DBS, Mandatory Training & Preferred Pool Onboarding', cx, subY);
    ctx.restore();

    // Compensation & Perks Highlight Card
    const compY = isTall ? subY + 54 : subY + 36;
    ctx.save();
    ctx.fillStyle = '#14b8a6';
    this._roundRect(ctx, cx - 320, compY, 640, isTall ? 88 : 76, 20);
    ctx.fill();

    ctx.fillStyle = '#042f2e';
    ctx.font = `900 ${isTall ? 42 : 36}px "Montserrat", sans-serif`;
    ctx.textAlign = 'center';
    ctx.fillText((this.data.accentMetric || 'UP TO £24.50 / HR + EWA PAY').toUpperCase(), cx, compY + (isTall ? 58 : 52));
    ctx.restore();

    // Date, Time & Venue Card
    const cardY = isTall ? compY + 120 : compY + 95;
    ctx.save();
    ctx.fillStyle = 'rgba(15, 23, 42, 0.88)';
    this._roundRect(ctx, w * 0.1, cardY, w * 0.8, isTall ? 320 : 240, 20);
    ctx.fill();
    ctx.strokeStyle = '#14b8a6';
    ctx.lineWidth = 2;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#5eead4';
    ctx.font = '800 28px "Montserrat", sans-serif';
    ctx.fillText([(this.data.date || 'WEDNESDAY 25 NOVEMBER 2026'), (this.data.time || '10:00 AM – 4:00 PM')].join('  •  ').toUpperCase(), cx, cardY + 68);

    ctx.fillStyle = '#ffffff';
    ctx.font = '900 32px "Montserrat", sans-serif';
    ctx.fillText((this.data.venue || 'BCP CLINICAL TRAINING ACADEMY').toUpperCase(), cx, cardY + 128);

    ctx.fillStyle = '#cbd5e1';
    ctx.font = '600 22px "Montserrat", sans-serif';
    ctx.fillText(this.data.address || 'Canary Wharf, London E14 5AB', cx, cardY + 172);

    const perksArr = Array.isArray(this.data.perks) ? this.data.perks : String(this.data.perks || 'SAME-DAY INTERVIEW, CPD ACCREDITED, FLEXIBLE SHIFTS').split(',');
    ctx.fillStyle = '#fbbf24';
    ctx.font = '800 20px "Montserrat", sans-serif';
    ctx.fillText(perksArr.map((s) => s.trim().toUpperCase()).filter(Boolean).join('   •   '), cx, cardY + 224);
    ctx.restore();

    // CQC Recruitment Wax Seal
    this._renderWaxCrestSeal(ctx, w * 0.28, isTall ? cardY + 410 : cardY + 280, 52, 'CQC RECRUITMENT', 'EXCELLENCE', '#042f2e', '#14b8a6');

    if (this.data.qrUrl) {
      this._renderScannableQrBadge(ctx, w - 180, isTall ? cardY + 360 : cardY + 240, 120, this.data.qrUrl, 'dark');
    }

    // Statutory CQC Notice
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '600 16px "Montserrat", sans-serif';
    ctx.fillStyle = '#94a3b8';
    ctx.fillText(this.data.regulatoryNotice || 'Best Care Professionals Ltd. Registered & regulated by the Care Quality Commission (CQC). Reg 18/19 compliant.', cx, h - 56);
    ctx.restore();
  }

  /**
   * Adaptive lending small print (LoanEasy). Built from what the poster actually advertises,
   * so it stays right whatever an admin types:
   *  - an advertised rate → "Representative X% APR (variable)" using that rate;
   *  - mortgage / home / secured wording → the FCA repossession warning;
   *  - business / SME wording → business-finance wording (no consumer APR claims);
   *  - always: subject to status and affordability, plus the poster's own website.
   * Small print an admin writes is kept, and only the missing mandatory parts are appended.
   * It never asserts regulatory authorisation (the engine cannot know a firm's FRN/status).
   */
  _loanComplianceNotice(customText) {
    const d = this.data || {};
    const custom = String(customText || '').trim();
    const lowCustom = custom.toLowerCase();
    const advertised = [d.title, d.kicker, d.subtitle, d.titleSecondary, d.venue, d.address, d.date, d.hosts, d.accentMetric, d.badge, d.perks]
      .map((v) => (Array.isArray(v) ? v.join(' ') : String(v || ''))).join(' ').toLowerCase();
    const parts = custom ? [custom.replace(/\s*\.?$/, '.')] : [];

    const isBusiness = /\b(sme|business|commercial|facility|growth capital|invoice|asset finance|working capital)\b/.test(advertised);
    const isSecured = !isBusiness && /\b(mortgages?|remortgages?|remortgaging|home loans?|homeowners?|ltv|deposits?|secured|propert(?:y|ies)|buy[- ]to[- ]let)\b/.test(advertised);

    const rate = String(d.accentMetric || '').match(/(\d+(?:\.\d+)?)\s*%/) || advertised.match(/(\d+(?:\.\d+)?)\s*%\s*(?:apr|interest|rate)/);
    if (rate && !isBusiness && !/representative/.test(lowCustom)) parts.push(`Representative ${rate[1]}% APR (variable).`);
    if (isSecured && !/repossess/.test(lowCustom)) parts.push('Your home may be repossessed if you do not keep up repayments on your mortgage.');
    if (!/subject to status/.test(lowCustom)) {
      parts.push(isBusiness
        ? 'Business finance is subject to status and credit approval. Security may be required.'
        : 'Lending is subject to status and affordability.');
    }
    if (!/terms apply|t&c/.test(lowCustom)) parts.push('Terms apply.');

    let site = 'www.loaneasyfinance.com';
    try { if (d.qrUrl) site = new URL(/^https?:/i.test(d.qrUrl) ? d.qrUrl : 'https://' + d.qrUrl).hostname; } catch (e) { /* keep default */ }
    if (!lowCustom.includes(site.replace(/^www\./, ''))) parts.push(site);
    return parts.join(' ');
  }

  /** Draw small print that always fits: shrink to fit, then wrap onto two lines if still too long. */
  _fillNotice(ctx, text, x, y, maxWidth, basePx, weight, family) {
    let px = basePx;
    const setFont = () => { ctx.font = `${weight} ${px}px ${family}`; };
    setFont();
    while (px > 10 && ctx.measureText(text).width > maxWidth) { px -= 0.5; setFont(); }
    if (ctx.measureText(text).width <= maxWidth) { ctx.fillText(text, x, y); return; }
    // two lines, split at the word boundary nearest the middle
    const words = text.split(' ');
    let best = 1, bestDiff = Infinity;
    for (let i = 1; i < words.length; i++) {
      const diff = Math.abs(ctx.measureText(words.slice(0, i).join(' ')).width - ctx.measureText(words.slice(i).join(' ')).width);
      if (diff < bestDiff) { bestDiff = diff; best = i; }
    }
    const l1 = words.slice(0, best).join(' '), l2 = words.slice(best).join(' ');
    while (px > 8 && Math.max(ctx.measureText(l1).width, ctx.measureText(l2).width) > maxWidth) { px -= 0.5; setFont(); }
    ctx.fillText(l1, x, y - px * 0.6);
    ctx.fillText(l2, x, y + px * 0.6);
  }

  /* =========================================================================
   * ARCHETYPE 9: FINTECH COMMERCIAL FACILITY (LoanEasy — SME Loan & Capital)
   * ========================================================================= */
  _renderArchetypeFintechFacility(ctx, w, h) {
    const cx = w / 2;
    const isTall = h >= 1400;
    const studioBg = this.backgroundImage || this.studioAssetCache.bg_fintech_sme_emerald;

    // 1. Obsidian Emerald Gradient or Photorealistic Banknote Rosette Backdrop
    if (studioBg) {
      this._drawCoverImage(ctx, studioBg, w, h, 1.0);
      const wash = ctx.createLinearGradient(0, 0, w, h);
      wash.addColorStop(0, 'rgba(2, 44, 34, 0.72)');
      wash.addColorStop(0.5, 'rgba(1, 28, 21, 0.85)');
      wash.addColorStop(1, 'rgba(0, 15, 11, 0.94)');
      ctx.fillStyle = wash;
      ctx.fillRect(0, 0, w, h);
    } else {
      const grad = ctx.createLinearGradient(0, 0, w, h);
      grad.addColorStop(0, '#022c22');
      grad.addColorStop(0.45, '#011c15');
      grad.addColorStop(1, '#000f0b');
      ctx.fillStyle = grad;
      ctx.fillRect(0, 0, w, h);
    }

    // Mathematical Guilloche Rosettes in 4 Corners
    this._renderGuillochePattern(ctx, 110, 110, 85, 48, '#eab308', 0.28);
    this._renderGuillochePattern(ctx, w - 110, 110, 85, 48, '#eab308', 0.28);
    this._renderGuillochePattern(ctx, 110, h - 110, 85, 48, '#10b981', 0.24);
    this._renderGuillochePattern(ctx, w - 110, h - 110, 85, 48, '#10b981', 0.24);

    // Fine 24k Gold Double Border
    ctx.save();
    ctx.strokeStyle = '#eab308';
    ctx.lineWidth = 2.5;
    ctx.strokeRect(36, 36, w - 72, h - 72);
    ctx.strokeStyle = 'rgba(16, 185, 129, 0.45)';
    ctx.lineWidth = 1;
    ctx.strokeRect(48, 48, w - 96, h - 96);
    ctx.restore();

    // Top Underwriting Badge
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#10b981';
    ctx.font = '800 22px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.badge || 'LOANEASY COMMERCIAL UNDERWRITING').toUpperCase(), cx, 96, 4);

    ctx.fillStyle = '#fbbf24';
    ctx.font = '700 20px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.kicker || 'INSTANT ALGORITHMIC CREDIT DECISIONING').toUpperCase(), cx, 134, 4);
    ctx.restore();

    // Massive 3D Metallic Gold Headline ("SME GROWTH CAPITAL")
    const titleLines = String(this.data.title || "SME GROWTH\nCAPITAL").toUpperCase().split(/\r\n|\r|\n/);
    const startY = isTall ? 280 : 236;
    titleLines.slice(0, 2).forEach((line, idx) => {
      this._renderMetallicGoldText(ctx, line, cx, startY + idx * 110, 104, '"Cinzel", "Montserrat", sans-serif', 'center');
    });

    // Subtitle
    const subY = startY + Math.min(2, titleLines.length) * 110 - 10;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '600 24px "Montserrat", sans-serif';
    ctx.fillStyle = '#a7f3d0';
    ctx.fillText(this.data.subtitle || 'Working Capital, Equipment Finance & Commercial Bridging', cx, subY);
    ctx.restore();

    // Main Facility Card (Glowing Emerald & 24k Gold)
    const cardW = w * 0.88;
    const cardH = isTall ? 170 : 152;
    const cardY = isTall ? subY + 54 : subY + 36;
    const cardX = cx - cardW / 2;

    ctx.save();
    const fGrad = ctx.createLinearGradient(cardX, cardY, cardX + cardW, cardY + cardH);
    fGrad.addColorStop(0, '#064e3b');
    fGrad.addColorStop(0.5, '#043729');
    fGrad.addColorStop(1, '#022c22');
    ctx.fillStyle = fGrad;
    this._roundRect(ctx, cardX, cardY, cardW, cardH, 20);
    ctx.fill();

    ctx.strokeStyle = '#eab308';
    ctx.lineWidth = 2.5;
    ctx.shadowColor = 'rgba(234, 179, 8, 0.45)';
    ctx.shadowBlur = 24;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#fef08a';
    ctx.font = `900 ${isTall ? 58 : 50}px "Montserrat", sans-serif`;
    ctx.fillText((this.data.accentMetric || 'FROM 6.9% REP APR • UP TO £500K').toUpperCase(), cx, cardY + (isTall ? 76 : 66));

    ctx.fillStyle = '#ffffff';
    ctx.font = '800 22px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, [(this.data.date || 'SAME-DAY DECISION'), (this.data.time || '24-HOUR DISBURSEMENT')].join('  •  ').toUpperCase(), cx, cardY + (isTall ? 128 : 116), 2);
    ctx.restore();

    // Open Banking Card
    const obY = isTall ? cardY + cardH + 50 : cardY + cardH + 30;
    const obH = isTall ? 240 : 196;
    ctx.save();
    ctx.fillStyle = 'rgba(2, 44, 34, 0.88)';
    this._roundRect(ctx, w * 0.08, obY, w * 0.84, obH, 20);
    ctx.fill();
    ctx.strokeStyle = 'rgba(16, 185, 129, 0.5)';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#34d399';
    ctx.font = '900 28px "Montserrat", sans-serif';
    ctx.fillText((this.data.venue || 'OPEN BANKING INSTANT VERIFICATION').toUpperCase(), cx, obY + 60);

    ctx.fillStyle = '#e2e8f0';
    ctx.font = '600 21px "Montserrat", sans-serif';
    ctx.fillText(this.data.address || 'Zero Early Settlement Penalties • UK Registered SMEs', cx, obY + 104);

    // Default perks make no regulatory claims (the engine cannot know a firm's FCA status).
    const perksArr = Array.isArray(this.data.perks) ? this.data.perks : String(this.data.perks || 'CLEAR PRICING, FLEXIBLE TERMS, DEDICATED SUPPORT').split(',');
    ctx.fillStyle = '#fbbf24';
    ctx.font = '800 20px "Montserrat", sans-serif';
    ctx.fillText(perksArr.map((s) => s.trim().toUpperCase()).filter(Boolean).join('   •   '), cx, obY + (isTall ? 160 : 148));
    ctx.restore();

    // 3-Step SME Capital Pipeline (for tall aspect ratio)
    if (isTall) {
      const pipeY = obY + obH + 46;
      const stepW = (w * 0.84 - 24) / 3;
      const steps = [
        { num: '01', title: 'APPLY ONLINE', sub: '60 Seconds' },
        { num: '02', title: 'ALGORITHMIC', sub: 'Instant Credit' },
        { num: '03', title: 'DISBURSEMENT', sub: 'Same-Day Funds' }
      ];

      steps.forEach((step, idx) => {
        const sx = w * 0.08 + idx * (stepW + 12);
        ctx.save();
        ctx.fillStyle = 'rgba(6, 78, 59, 0.85)';
        this._roundRect(ctx, sx, pipeY, stepW, 110, 14);
        ctx.fill();
        ctx.strokeStyle = '#eab308';
        ctx.lineWidth = 1.5;
        ctx.stroke();

        ctx.textAlign = 'center';
        ctx.fillStyle = '#fde047';
        ctx.font = '900 24px "Montserrat", sans-serif';
        ctx.fillText(step.num, sx + stepW / 2, pipeY + 36);

        ctx.fillStyle = '#ffffff';
        ctx.font = '800 16px "Montserrat", sans-serif';
        ctx.fillText(step.title, sx + stepW / 2, pipeY + 68);

        ctx.fillStyle = '#a7f3d0';
        ctx.font = '600 14px "Montserrat", sans-serif';
        ctx.fillText(step.sub, sx + stepW / 2, pipeY + 92);
        ctx.restore();
      });
    }

    // FCA Gold Crest Seal on Bottom-Left
    // Previously asserted "FCA REGULATED" on every poster; the engine cannot know a firm's status.
    this._renderWaxCrestSeal(ctx, w * 0.28, isTall ? h - 230 : h * 0.77, 52, 'BUSINESS', 'CREDIT FACILITY', '#064e3b', '#eab308');

    if (this.data.qrUrl) {
      this._renderScannableQrBadge(ctx, w - 180, isTall ? h - 280 : h * 0.71, 120, this.data.qrUrl, 'dark');
    }

    // Mandatory FCA Risk Box
    ctx.save();
    const fcaY = h - 90;
    ctx.fillStyle = 'rgba(15, 23, 42, 0.94)';
    this._roundRect(ctx, w * 0.08, fcaY, w * 0.84, 56, 12);
    ctx.fill();
    ctx.strokeStyle = '#eab308';
    ctx.lineWidth = 2;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.font = '700 15px "Montserrat", sans-serif';
    ctx.fillStyle = '#fef08a';
    ctx.fillText('IMPORTANT INFORMATION:', cx, fcaY + 22);

    ctx.font = '500 13px "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    // Adaptive small print (built from what the poster advertises). Replaced a fixed fallback that
    // said "Capital at risk" (an investment warning) and asserted FCA authorisation.
    this._fillNotice(ctx, this._loanComplianceNotice(this.data.regulatoryNotice), cx, fcaY + 43, w * 0.86, 13, '500', '"Montserrat", sans-serif');
    ctx.restore();
  }

  /* =========================================================================
   * ARCHETYPE: CONSUMER LOAN CAMPAIGN (LoanEasy — HSBC Style Dynamic Wedge & Joyful Lifestyle)
   * ========================================================================= */
  _renderArchetypeConsumerLoanCampaign(ctx, w, h) {
    const isTall = h >= 1400;
    // Only a backdrop the user uploaded: the bundled bg_consumer_moving_joy.jpg is a real HSBC advert.
    const bgImg = this.backgroundImage;

    // 1. Full-Bleed Warm Lifestyle Photo
    if (bgImg && bgImg.complete && bgImg.naturalWidth > 0) {
      this._drawCoverImage(ctx, bgImg, w, h); // signature is (ctx, img, w, h, alpha); "0, 0, w, h" drew it at zero size
    } else {
      const bgGrad = ctx.createLinearGradient(0, 0, w, h);
      bgGrad.addColorStop(0, '#1e293b');
      bgGrad.addColorStop(0.5, '#0f172a');
      bgGrad.addColorStop(1, '#020617');
      ctx.fillStyle = bgGrad;
      ctx.fillRect(0, 0, w, h);
    }

    // Optional user subject cutout
    if (this.subjectImage && this.subjectImage.complete && this.subjectImage.naturalWidth > 0) {
      this._renderSmartCutoutSubject(ctx, w, h);
    }

    // 2. Dynamic Vibrant Crimson Chevron Arrow / Wedge Polygon (HSBC Brand Style)
    ctx.save();
    ctx.shadowColor = 'rgba(0, 0, 0, 0.45)';
    ctx.shadowBlur = 32;
    ctx.shadowOffsetX = 8;
    ctx.shadowOffsetY = 12;

    const wedgeGrad = ctx.createLinearGradient(0, 0, w * 0.9, h * 0.5);
    wedgeGrad.addColorStop(0, '#e11d48');   // Vibrant rose/crimson
    wedgeGrad.addColorStop(0.5, '#be123c'); // Deep scarlet
    wedgeGrad.addColorStop(1, '#9f1239');   // Rich ruby crimson

    ctx.fillStyle = wedgeGrad;
    ctx.beginPath();
    ctx.moveTo(0, 0);
    ctx.lineTo(w * (isTall ? 0.82 : 0.76), 0);
    ctx.lineTo(w * 0.98, h * (isTall ? 0.30 : 0.32)); // Apex point pointing right
    ctx.lineTo(w * (isTall ? 0.68 : 0.60), h * (isTall ? 0.56 : 0.58));
    ctx.lineTo(0, h * (isTall ? 0.56 : 0.58));
    ctx.closePath();
    ctx.fill();
    ctx.restore();

    // Subtle gloss edge along the top/apex of the chevron
    ctx.save();
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.28)';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(0, 2);
    ctx.lineTo(w * (isTall ? 0.82 : 0.76), 2);
    ctx.lineTo(w * 0.98, h * (isTall ? 0.30 : 0.32));
    ctx.lineTo(w * (isTall ? 0.68 : 0.60), h * (isTall ? 0.56 : 0.58));
    ctx.stroke();
    ctx.restore();

    // 3. Conversational High-Impact Consumer Headline on the Chevron
    ctx.save();
    ctx.textAlign = 'left';
    ctx.textBaseline = 'top';

    const textX = 64;
    let textY = isTall ? 90 : 60;

    const title = this.data.title || 'Finding the perfect home is hard. Owning it isn’t.';
    const lines = title.split(/\.\s*|\n/g).filter(s => s.trim().length > 0);

    ctx.fillStyle = '#ffffff';
    ctx.font = '700 ' + (isTall ? '54px' : '44px') + ' "Montserrat", -apple-system, sans-serif';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
    ctx.shadowBlur = 8;
    ctx.shadowOffsetY = 2;

    lines.forEach((line, idx) => {
      const cleanLine = line.trim() + (idx < lines.length - 1 ? '.' : '');
      ctx.fillText(cleanLine, textX, textY);
      textY += (isTall ? 66 : 54);
    });

    textY += (isTall ? 36 : 24);

    // 4. Interest Rate Callout Block
    const kicker = this.data.kicker || 'Interest rate starting at';
    ctx.font = '500 ' + (isTall ? '38px' : '30px') + ' "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    ctx.fillText(kicker, textX, textY);

    // Underline
    const underlineWord = 'Interest rate';
    const underlineW = ctx.measureText(underlineWord).width;
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 2.5;
    ctx.beginPath();
    ctx.moveTo(textX, textY + (isTall ? 44 : 36));
    ctx.lineTo(textX + underlineW, textY + (isTall ? 44 : 36));
    ctx.stroke();

    textY += (isTall ? 60 : 48);

    // "just 8.55%*"
    const rateText = this.data.accentMetric || '8.55%*';
    ctx.font = '400 ' + (isTall ? '46px' : '36px') + ' "Montserrat", sans-serif';
    ctx.fillText('just ', textX, textY + (isTall ? 28 : 20));
    const justW = ctx.measureText('just ').width;

    ctx.font = '900 ' + (isTall ? '94px' : '76px') + ' "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    ctx.fillText(rateText, textX + justW, textY);

    ctx.restore();

    // 5. Sleek Bottom Brand Bar (Logo + Tagline + Legal Footnote)
    const barH = isTall ? 140 : 110;
    const barY = h - barH;

    ctx.save();
    const bottomGrad = ctx.createLinearGradient(0, barY, 0, h);
    bottomGrad.addColorStop(0, 'rgba(2, 6, 23, 0.4)');
    bottomGrad.addColorStop(0.3, 'rgba(2, 6, 23, 0.88)');
    bottomGrad.addColorStop(1, 'rgba(2, 6, 23, 0.98)');
    ctx.fillStyle = bottomGrad;
    ctx.fillRect(0, barY, w, barH);

    ctx.strokeStyle = 'rgba(255, 255, 255, 0.12)';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(0, barY);
    ctx.lineTo(w, barY);
    ctx.stroke();

    const logoX = 54;
    const logoCenterY = barY + barH / 2;

    ctx.fillStyle = '#e11d48';
    ctx.beginPath();
    ctx.moveTo(logoX, logoCenterY - 18);
    ctx.lineTo(logoX + 18, logoCenterY);
    ctx.lineTo(logoX, logoCenterY + 18);
    ctx.lineTo(logoX - 18, logoCenterY);
    ctx.closePath();
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.moveTo(logoX + 22, logoCenterY - 18);
    ctx.lineTo(logoX + 40, logoCenterY);
    ctx.lineTo(logoX + 22, logoCenterY + 18);
    ctx.lineTo(logoX + 4, logoCenterY);
    ctx.closePath();
    ctx.fill();

    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.font = '900 ' + (isTall ? '28px' : '22px') + ' "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    ctx.fillText('LOANEASY', logoX + 54, logoCenterY);

    const loanEasyW = ctx.measureText('LOANEASY').width;
    ctx.font = '500 ' + (isTall ? '22px' : '18px') + ' "Montserrat", sans-serif';
    ctx.fillStyle = 'rgba(255, 255, 255, 0.7)';
    ctx.fillText('|  ' + (this.data.subtitle || 'Borrowing made simple'), logoX + 66 + loanEasyW, logoCenterY); // was HSBC's slogan

    ctx.textAlign = 'right';
    ctx.font = '500 13px -apple-system, sans-serif';
    ctx.fillStyle = 'rgba(255, 255, 255, 0.65)';
    ctx.fillText('For Persons in the UK', w - 54, logoCenterY - 12);
    this._fillNotice(ctx, this._loanComplianceNotice(this.data.footerNotice), w - 54, logoCenterY + 12, w * 0.46, 13, '500', '-apple-system, sans-serif');

    ctx.restore();
  }

  /* =========================================================================
   * ARCHETYPE: RETAIL BANKING PRODUCT GRID (LoanEasy — Virgin Money Style Card & Keys Hero)
   * ========================================================================= */
  _renderArchetypeRetailBankingFeatures(ctx, w, h) {
    const isTall = h >= 1400;
    // Only a backdrop the user uploaded: the bundled bg_consumer_family_home.jpg is a real Virgin Money advert.
    const bgImg = this.backgroundImage;

    // 1. Warm Family Homeowner Lifestyle Backdrop
    if (bgImg && bgImg.complete && bgImg.naturalWidth > 0) {
      this._drawCoverImage(ctx, bgImg, w, h); // signature is (ctx, img, w, h, alpha); "0, 0, w, h" drew it at zero size
    } else {
      const bgGrad = ctx.createLinearGradient(0, 0, w, h);
      bgGrad.addColorStop(0, '#f8fafc');
      bgGrad.addColorStop(1, '#e2e8f0');
      ctx.fillStyle = bgGrad;
      ctx.fillRect(0, 0, w, h);
    }

    // Optional user subject cutout
    if (this.subjectImage && this.subjectImage.complete && this.subjectImage.naturalWidth > 0) {
      this._renderSmartCutoutSubject(ctx, w, h);
    }

    // 2. Top Crisp White Retail Banking Card Container
    const cardMargin = 40;
    const cardX = cardMargin;
    const cardY = cardMargin;
    const cardW = w - (cardMargin * 2);
    const cardH = isTall ? 720 : 540;

    ctx.save();
    ctx.shadowColor = 'rgba(15, 23, 42, 0.22)';
    ctx.shadowBlur = 35;
    ctx.shadowOffsetY = 12;

    ctx.fillStyle = '#ffffff';
    this._roundRect(ctx, cardX, cardY, cardW, cardH, 28);
    ctx.fill();
    ctx.restore();

    ctx.save();
    ctx.strokeStyle = 'rgba(226, 232, 240, 0.8)';
    ctx.lineWidth = 2;
    this._roundRect(ctx, cardX, cardY, cardW, cardH, 28);
    ctx.stroke();
    ctx.restore();

    // 3. Card Header Bar: Brand Logo (Left) + Red Pill Badge (Right)
    const brandRed = '#dc2626';
    const darkCharcoal = '#0f172a';

    ctx.save();
    const logoCx = cardX + 64;
    const logoCy = cardY + 64;

    ctx.fillStyle = brandRed;
    ctx.beginPath();
    ctx.arc(logoCx, logoCy, 34, 0, Math.PI * 2);
    ctx.fill();

    ctx.font = 'italic 700 24px "Alex Brush", "Playfair Display", serif';
    ctx.fillStyle = '#ffffff';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('LEF', logoCx, logoCy);

    ctx.textAlign = 'left';
    ctx.font = '900 34px "Montserrat", sans-serif';
    ctx.fillStyle = brandRed;
    ctx.fillText('LOANEASY', logoCx + 48, logoCy - 4);
    ctx.font = '600 20px "Montserrat", sans-serif';
    ctx.fillStyle = darkCharcoal;
    ctx.fillText('FINANCE', logoCx + 48, logoCy + 22);

    // Top Right Pill Badge: "NEW Exclusive Products"
    const badgeText = this.data.kicker || 'NEW Exclusive Products';
    ctx.font = '800 24px "Montserrat", sans-serif';
    const badgeW = ctx.measureText(badgeText).width + 48;
    const badgeH = 54;
    const badgeX = cardX + cardW - badgeW - 32;
    const badgeY = cardY + 38;

    ctx.fillStyle = brandRed;
    this._roundRect(ctx, badgeX, badgeY, badgeW, badgeH, 27);
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(badgeText, badgeX + (badgeW / 2), badgeY + (badgeH / 2));
    ctx.restore();

    // Divider
    ctx.save();
    ctx.strokeStyle = '#f1f5f9';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(cardX + 32, cardY + 120);
    ctx.lineTo(cardX + cardW - 32, cardY + 120);
    ctx.stroke();
    ctx.restore();

    // 4. Rate Showcase Section: "From 4.40%" + Down Trend Arrows + 90%/95% LTV + Fee-Saver
    const rateSectionY = cardY + 150;

    ctx.save();
    ctx.textAlign = 'left';
    ctx.textBaseline = 'top';

    ctx.font = '700 ' + (isTall ? '48px' : '38px') + ' "Montserrat", sans-serif';
    ctx.fillStyle = brandRed;
    ctx.fillText('From', cardX + 44, rateSectionY + (isTall ? 32 : 24));
    const fromW = ctx.measureText('From').width;

    const rateValue = this.data.accentMetric || '4.40%';
    ctx.font = '900 ' + (isTall ? '106px' : '86px') + ' "Montserrat", sans-serif';
    ctx.fillText(rateValue, cardX + 54 + fromW, rateSectionY);
    const rateW = ctx.measureText(rateValue).width;

    const arrowX = cardX + 70 + fromW + rateW;
    const arrowY = rateSectionY + (isTall ? 28 : 20);

    ctx.strokeStyle = brandRed;
    ctx.lineWidth = 6;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    [0, 24].forEach((offset) => {
      ctx.beginPath();
      ctx.moveTo(arrowX + offset, arrowY);
      ctx.lineTo(arrowX + offset + 18, arrowY + 36);
      ctx.lineTo(arrowX + offset + 6, arrowY + 36);
      ctx.moveTo(arrowX + offset + 18, arrowY + 36);
      ctx.lineTo(arrowX + offset + 18, arrowY + 24);
      ctx.stroke();
    });

    const ltvColX = cardX + cardW - (isTall ? 330 : 280);

    ctx.font = '900 ' + (isTall ? '46px' : '36px') + ' "Montserrat", sans-serif';
    ctx.fillStyle = brandRed;
    ctx.fillText('90%', ltvColX, rateSectionY);

    ctx.font = '600 ' + (isTall ? '24px' : '20px') + ' "Montserrat", sans-serif';
    ctx.fillText('& 95% LTV Available', ltvColX, rateSectionY + (isTall ? 50 : 40));

    const feeSaverText = this.data.hosts || 'Fee-Saver';
    ctx.font = '800 22px "Montserrat", sans-serif';
    const fsW = ctx.measureText(feeSaverText).width + 36;
    const fsH = 46;
    const fsY = rateSectionY + (isTall ? 88 : 72);

    ctx.fillStyle = brandRed;
    this._roundRect(ctx, ltvColX, fsY, fsW, fsH, 23);
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(feeSaverText, ltvColX + (fsW / 2), fsY + (fsH / 2));

    ctx.restore();

    // 5. Lower Features Row Inside Card (Deposit, No Fee, Date)
    const featRowY = isTall ? (cardY + 330) : (cardY + 290);

    ctx.save();
    ctx.strokeStyle = '#f1f5f9';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(cardX + 32, featRowY);
    ctx.lineTo(cardX + cardW - 32, featRowY);
    ctx.stroke();
    ctx.restore();

    const f1X = cardX + 44;
    const f1Y = featRowY + 32;

    ctx.save();
    ctx.textAlign = 'left';
    ctx.textBaseline = 'top';

    ctx.font = '900 ' + (isTall ? '74px' : '58px') + ' "Montserrat", sans-serif';
    ctx.fillStyle = brandRed;
    ctx.fillText('5%', f1X, f1Y);

    ctx.font = '700 ' + (isTall ? '36px' : '28px') + ' "Montserrat", sans-serif';
    ctx.fillText('Deposit', f1X, f1Y + (isTall ? 78 : 62));

    const f2X = cardX + (cardW * 0.38);
    const iconCy = f1Y + 45;

    ctx.strokeStyle = brandRed;
    ctx.lineWidth = 4;
    ctx.beginPath();
    ctx.arc(f2X + 32, iconCy, 32, 0, Math.PI * 2);
    ctx.stroke();

    ctx.font = '800 24px -apple-system, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = brandRed;
    ctx.fillText('%', f2X + 32, iconCy);

    ctx.textAlign = 'left';
    ctx.textBaseline = 'top';
    ctx.font = '800 ' + (isTall ? '34px' : '26px') + ' "Montserrat", sans-serif';
    ctx.fillStyle = brandRed;
    ctx.fillText('No', f2X + 80, f1Y + 12);
    ctx.fillText('Arrangement', f2X + 80, f1Y + (isTall ? 50 : 40));
    ctx.fillText('Fee', f2X + 80, f1Y + (isTall ? 88 : 70));

    const f3X = cardX + cardW - 44;
    ctx.textAlign = 'right';
    ctx.font = '900 ' + (isTall ? '52px' : '40px') + ' "Montserrat", sans-serif';
    ctx.fillStyle = brandRed;
    ctx.fillText(this.data.date || 'August 2026', f3X, f1Y + (isTall ? 36 : 28));

    ctx.restore();

    // 6. Bottom Regulatory Floating Strip
    const btmBarH = 68;
    const btmBarY = h - btmBarH - 36;
    const btmBarW = w - 72;
    const btmBarX = 36;

    ctx.save();
    ctx.fillStyle = 'rgba(255, 255, 255, 0.94)';
    this._roundRect(ctx, btmBarX, btmBarY, btmBarW, btmBarH, 16);
    ctx.fill();

    ctx.strokeStyle = 'rgba(226, 232, 240, 0.8)';
    ctx.lineWidth = 1.5;
    this._roundRect(ctx, btmBarX, btmBarY, btmBarW, btmBarH, 16);
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.font = '600 15px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
    ctx.fillStyle = '#334155';
    this._fillNotice(ctx, this._loanComplianceNotice(this.data.footerNotice), w / 2, btmBarY + (btmBarH / 2), w * 0.9, 15, '600', '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif');
    ctx.restore();
  }

  /* =========================================================================
   * ARCHETYPE 10: TREASURY & TAX CLINIC (iAccount — HMRC MTD & Corporate Tax)
   * ========================================================================= */
  _renderArchetypeTaxTreasuryClinic(ctx, w, h) {
    const cx = w / 2;
    const isTall = h >= 1400;

    // 1. Swiss Slate & Prussian Blue Backdrop
    const grad = ctx.createLinearGradient(0, 0, w, h);
    grad.addColorStop(0, '#0a1128');
    grad.addColorStop(0.45, '#0f172a');
    grad.addColorStop(1, '#020617');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, w, h);

    // 2. Procedural Neoclassical Fluted Columns
    this._renderNeoclassicalColumns(ctx, w, h, 54, '#3b82f6');

    // 3. Banknote Security Guilloche Borders
    this._renderGuillochePattern(ctx, cx, 80, 70, 32, '#60a5fa', 0.18);
    this._renderGuillochePattern(ctx, cx, h - 80, 70, 32, '#60a5fa', 0.18);

    // 4. Top Header
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#60a5fa';
    ctx.font = '800 22px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.badge || 'IACCOUNT TREASURY & TAX ADVISORY').toUpperCase(), cx, 88, 4);

    ctx.fillStyle = '#f59e0b';
    ctx.font = '700 20px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.kicker || 'STATUTORY FILING DEADLINE BRIEFING').toUpperCase(), cx, 128, 4);
    ctx.restore();

    // 5. 3D Bronze & Copper Foil Headline
    const titleLines = String(this.data.title || "HMRC TAX &\nAUDIT CLINIC").toUpperCase().split(/\r\n|\r|\n/);
    const startY = isTall ? 280 : 210;
    titleLines.slice(0, 2).forEach((line, idx) => {
      this._renderMetallicGoldText(ctx, line, cx, startY + idx * 105, 98, '"Cinzel", "Montserrat", sans-serif', 'center');
    });

    // Subtitle
    const subY = startY + Math.min(2, titleLines.length) * 105 - 10;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '600 24px "Montserrat", sans-serif';
    ctx.fillStyle = '#93c5fd';
    ctx.fillText(this.data.subtitle || 'Automated Double-Entry Reconciliation, VAT & Corporation Tax', cx, subY);
    ctx.restore();

    // 6. Metric Pill (ledger status)
    const mPillY = isTall ? subY + 64 : subY + 32;
    ctx.save();
    ctx.fillStyle = '#2563eb';
    this._roundRect(ctx, cx - 320, mPillY, 640, isTall ? 80 : 68, 20);
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.font = `900 ${isTall ? 36 : 30}px "Montserrat", sans-serif`;
    ctx.textAlign = 'center';
    ctx.fillText((this.data.accentMetric || 'DIGITAL DOUBLE-ENTRY LEDGER').toUpperCase(), cx, mPillY + (isTall ? 52 : 44));
    ctx.restore();

    // 7. Main Venue / Masterclass Card
    const cardY = isTall ? mPillY + 130 : mPillY + 85;
    const cardH = isTall ? 320 : 200;
    ctx.save();
    ctx.fillStyle = 'rgba(15, 23, 42, 0.92)';
    this._roundRect(ctx, w * 0.08, cardY, w * 0.84, cardH, 20);
    ctx.fill();
    ctx.strokeStyle = '#3b82f6';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#fde047';
    ctx.font = '800 26px "Montserrat", sans-serif';
    ctx.fillText([(this.data.date || 'FRIDAY 11 DECEMBER 2026'), (this.data.time || '11:00 AM LIVE MASTERCLASS')].join('  •  ').toUpperCase(), cx, cardY + 58);

    ctx.fillStyle = '#ffffff';
    ctx.font = '900 32px "Montserrat", sans-serif';
    ctx.fillText((this.data.venue || 'IACCOUNT FINANCIAL COMMAND CENTRE').toUpperCase(), cx, cardY + 116);

    ctx.fillStyle = '#cbd5e1';
    ctx.font = '600 21px "Montserrat", sans-serif';
    ctx.fillText(this.data.address || 'Bishopsgate, City of London EC2N 4AY', cx, cardY + 162);

    const perksArr = Array.isArray(this.data.perks) ? this.data.perks : String(this.data.perks || 'VAT OPTIMISATION, R&D TAX CREDITS, YEAR-END SHIELD').split(',');
    ctx.fillStyle = '#60a5fa';
    ctx.font = '800 20px "Montserrat", sans-serif';
    ctx.fillText(perksArr.map((s) => s.trim().toUpperCase()).filter(Boolean).join('   •   '), cx, cardY + 220);
    ctx.restore();

    // 8. Three Statutory Deliverable / Capability Cards
    const gridY = isTall ? cardY + cardH + 54 : cardY + cardH + 20;
    const gridCardW = (w * 0.84 - 32) / 3;
    const gridCardH = isTall ? 260 : 130;
    const gridStartX = w * 0.08;

    const cardsData = [
      { step: '01 / CORP TAX', title: 'OPTIMISED RELIEFS', desc: 'Full Capital Allowances\n& CT600 Gateway' },
      { step: '02 / MTD DIGITAL', title: 'REAL-TIME LEDGER', desc: 'Direct HMRC API Links\n& Instant Bank Recon' },
      { step: '03 / YEAR-END', title: 'ACCOUNTANT READY', desc: 'Organised records\n& clear summaries' }
    ];

    cardsData.forEach((c, idx) => {
      const bx = gridStartX + idx * (gridCardW + 16);
      ctx.save();
      ctx.fillStyle = 'rgba(15, 23, 42, 0.90)';
      this._roundRect(ctx, bx, gridY, gridCardW, gridCardH, 16);
      ctx.fill();
      ctx.strokeStyle = idx === 1 ? '#60a5fa' : '#334155';
      ctx.lineWidth = 1.5;
      ctx.stroke();

      ctx.textAlign = 'center';
      ctx.fillStyle = '#fde047';
      ctx.font = '900 18px "Montserrat", sans-serif';
      ctx.fillText(c.step, bx + gridCardW / 2, gridY + 38);

      ctx.fillStyle = '#ffffff';
      ctx.font = '800 20px "Montserrat", sans-serif';
      ctx.fillText(c.title, bx + gridCardW / 2, gridY + 76);

      ctx.fillStyle = '#94a3b8';
      ctx.font = '600 15px "Montserrat", sans-serif';
      const lines = c.desc.split('\n');
      lines.forEach((line, lIdx) => {
        ctx.fillText(line, bx + gridCardW / 2, gridY + 118 + lIdx * 24);
      });
      ctx.restore();
    });

    // 9. Statutory Ribbon
    const actionY = isTall ? gridY + gridCardH + 54 : gridY + gridCardH + 20;
    ctx.save();
    ctx.fillStyle = 'rgba(30, 58, 138, 0.70)';
    this._roundRect(ctx, w * 0.08, actionY, w * 0.84, 58, 14);
    ctx.fill();
    ctx.strokeStyle = '#3b82f6';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#93c5fd';
    ctx.font = '800 18px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, 'DIGITAL RECORD-KEEPING  •  CLEAR TAX ESTIMATES', cx, actionY + 36, 2);
    ctx.restore();

    // 10. Direct Booking Hotline Strip
    const rsvpY = isTall ? actionY + 98 : actionY + 68;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#f59e0b';
    ctx.font = '800 18px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, 'DIRECT STATUTORY CLINIC ONBOARDING', cx, rsvpY, 2);

    ctx.fillStyle = '#ffffff';
    ctx.font = '900 28px "Montserrat", sans-serif';
    ctx.fillText('020 7946 0880   •   TAX@IACCOUNT.APP', cx, rsvpY + 38);
    ctx.restore();

    // 11. HMRC Wax Seal & Scannable QR Code
    const credY = isTall ? rsvpY + 130 : h * 0.82;
    this._renderWaxCrestSeal(ctx, w * 0.28, isTall ? credY + 20 : credY, 52, 'HMRC MTD', 'RECONCILED', '#1e3a8a', '#f59e0b');

    if (this.data.qrUrl) {
      this._renderScannableQrBadge(ctx, w - 180, isTall ? credY - 30 : h * 0.76, 120, this.data.qrUrl, 'dark');
    }

    // 12. Statutory MTD Notice
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '600 15px "Montserrat", sans-serif';
    ctx.fillStyle = '#94a3b8';
    ctx.fillText(this.data.regulatoryNotice || 'iAccount helps you keep digital records and estimate tax. Figures are estimates; check with an accountant before filing.', cx, h - 48);
    ctx.restore();
  }

  /* =========================================================================
   * ARCHETYPE 11: C-SUITE BOARDROOM SUMMIT (ExecMind — CEO AI Roundtable)
   * ========================================================================= */
  _renderArchetypeExecutiveBoardSummit(ctx, w, h) {
    const cx = w / 2;
    const isTall = h >= 1400;

    // 1. Ultra-luxury Sapphire Obsidian Radial Backdrop
    const grad = ctx.createRadialGradient(cx, h * 0.35, 40, cx, h * 0.5, h * 0.85);
    grad.addColorStop(0, '#0f172a');
    grad.addColorStop(0.45, '#060a17');
    grad.addColorStop(1, '#02040a');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, w, h);

    // 2. Procedural Constellation Network (36 nodes connected by laser hairline filaments)
    this._renderConstellationNetwork(ctx, w, h, 36, '#38bdf8', 'rgba(56, 189, 248, 0.16)');

    // 3. Fine 24k Gold Hairline Double Frame
    ctx.save();
    ctx.strokeStyle = '#eab308';
    ctx.lineWidth = 2.5;
    ctx.strokeRect(40, 40, w - 80, h - 80);
    ctx.strokeStyle = 'rgba(234, 179, 8, 0.35)';
    ctx.lineWidth = 1;
    ctx.strokeRect(52, 52, w - 104, h - 104);
    ctx.restore();

    // 4. Top Header Badge
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#38bdf8';
    ctx.font = '800 22px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.badge || 'EXECMIND SOVEREIGN LEADERSHIP SERIES').toUpperCase(), cx, 88, 4);

    ctx.fillStyle = '#fbbf24';
    ctx.font = '700 20px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.kicker || 'Q4 BOARDROOM BRIEFING').toUpperCase(), cx, 128, 4);
    ctx.restore();

    // 5. Massive Bevelled Gold Serif Headline
    const titleLines = String(this.data.title || "GLOBAL CEO\nROUNDTABLE").toUpperCase().split(/\r\n|\r|\n/);
    const startY = isTall ? 280 : 210;
    titleLines.slice(0, 2).forEach((line, idx) => {
      this._renderMetallicGoldText(ctx, line, cx, startY + idx * 105, 98, '"Cinzel", "Playfair Display", Georgia, serif', 'center');
    });

    // Subtitle
    const subY = startY + Math.min(2, titleLines.length) * 105 - 10;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '600 24px "Montserrat", sans-serif';
    ctx.fillStyle = '#cbd5e1';
    ctx.fillText(this.data.subtitle || 'Autonomous Decision Intelligence & Capital Allocation', cx, subY);
    ctx.restore();

    // 6. Smoked-Glass Dais Card
    const cardW = w * 0.84;
    const cardH = isTall ? 320 : 200;
    const cardY = isTall ? subY + 70 : subY + 32;
    const cardX = cx - cardW / 2;

    ctx.save();
    ctx.fillStyle = 'rgba(11, 19, 43, 0.94)';
    this._roundRect(ctx, cardX, cardY, cardW, cardH, 20);
    ctx.fill();
    ctx.strokeStyle = '#eab308';
    ctx.lineWidth = 2;
    ctx.shadowColor = 'rgba(234, 179, 8, 0.35)';
    ctx.shadowBlur = 20;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#fde047';
    ctx.font = '800 26px "Montserrat", sans-serif';
    ctx.fillText([(this.data.date || 'THURSDAY 19 NOVEMBER 2026'), (this.data.time || '6:30 PM CHAMPAGNE')].join('  •  ').toUpperCase(), cx, cardY + 58);

    ctx.fillStyle = '#ffffff';
    ctx.font = '900 32px "Cinzel", "Montserrat", sans-serif';
    ctx.fillText((this.data.venue || 'THE DORCHESTER PENTHOUSE, MAYFAIR').toUpperCase(), cx, cardY + 116);

    ctx.fillStyle = '#38bdf8';
    ctx.font = '700 21px "Montserrat", sans-serif';
    ctx.fillText(this.data.hosts || 'Keynote: Dr. Chen Wei & Olutobi (Deloitte)', cx, cardY + 162);

    const perksArr = Array.isArray(this.data.perks) ? this.data.perks : String(this.data.perks || 'BOARD NETWORKING, PRIVATE DINING, BESPOKE BRIEFING').split(',');
    ctx.fillStyle = '#a5b4fc';
    ctx.font = '800 18px "Montserrat", sans-serif';
    ctx.fillText(perksArr.map((s) => s.trim().toUpperCase()).filter(Boolean).join('   •   '), cx, cardY + 220);
    ctx.restore();

    // 7. Three C-Suite Leadership Agenda Cards
    const gridY = isTall ? cardY + cardH + 54 : cardY + cardH + 20;
    const gridCardW = (cardW - 32) / 3;
    const gridCardH = isTall ? 260 : 130;
    const gridStartX = cardX;

    const agendaData = [
      { step: '01 / AI GOVERNANCE', title: 'AUTONOMOUS AGENTS', desc: 'Predictive Decisioning\n& Board Audit Trail' },
      { step: '02 / CAPITAL VELOCITY', title: 'TREASURY RESILIENCE', desc: 'Dynamic Hedging\n& Liquidity Safety Gates' },
      { step: '03 / C-SUITE DEFENCE', title: 'CHATHAM INTEGRITY', desc: 'Deloitte Vetted\nStrategic Advisory Pack' }
    ];

    agendaData.forEach((a, idx) => {
      const bx = gridStartX + idx * (gridCardW + 16);
      ctx.save();
      ctx.fillStyle = 'rgba(15, 23, 42, 0.90)';
      this._roundRect(ctx, bx, gridY, gridCardW, gridCardH, 16);
      ctx.fill();
      ctx.strokeStyle = idx === 1 ? '#eab308' : '#334155';
      ctx.lineWidth = 1.5;
      ctx.stroke();

      ctx.textAlign = 'center';
      ctx.fillStyle = '#38bdf8';
      ctx.font = '900 18px "Montserrat", sans-serif';
      ctx.fillText(a.step, bx + gridCardW / 2, gridY + 38);

      ctx.fillStyle = '#ffffff';
      ctx.font = '800 20px "Cinzel", "Montserrat", sans-serif';
      ctx.fillText(a.title, bx + gridCardW / 2, gridY + 76);

      ctx.fillStyle = '#94a3b8';
      ctx.font = '600 15px "Montserrat", sans-serif';
      const lines = a.desc.split('\n');
      lines.forEach((line, lIdx) => {
        ctx.fillText(line, bx + gridCardW / 2, gridY + 118 + lIdx * 24);
      });
      ctx.restore();
    });

    // 8. Governance Accreditation Ribbon
    const actionY = isTall ? gridY + gridCardH + 54 : gridY + gridCardH + 20;
    ctx.save();
    ctx.fillStyle = 'rgba(234, 179, 8, 0.18)';
    this._roundRect(ctx, cardX, actionY, cardW, 58, 14);
    ctx.fill();
    ctx.strokeStyle = '#eab308';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#fde047';
    ctx.font = '800 18px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.accentMetric || '12 CPD CREDITS  •  CHATHAM HOUSE RULE ENFORCED  •  BOARD ACCREDITED').toUpperCase(), cx, actionY + 36, 2);
    ctx.restore();

    // 9. Executive Secretariat Booking Strip
    const rsvpY = isTall ? actionY + 98 : actionY + 68;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#38bdf8';
    ctx.font = '800 18px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, 'PRIVATE EXECUTIVE SECRETARIAT', cx, rsvpY, 2);

    ctx.fillStyle = '#ffffff';
    ctx.font = '900 28px "Montserrat", sans-serif';
    ctx.fillText('020 7946 0999   •   BOARD@EXECMIND.APP', cx, rsvpY + 38);
    ctx.restore();

    // 10. Chatham House Wax Seal & Scannable QR Code
    const credY = isTall ? rsvpY + 130 : h * 0.82;
    this._renderWaxCrestSeal(ctx, w * 0.28, isTall ? credY + 20 : credY, 52, 'CHATHAM HOUSE', 'STRICTLY PRIVATE', '#1e1b4b', '#eab308');

    if (this.data.qrUrl) {
      this._renderScannableQrBadge(ctx, w - 180, isTall ? credY - 30 : h * 0.76, 120, this.data.qrUrl, 'dark');
    }

    // 11. Statutory Chatham House Notice
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '600 15px "Montserrat", sans-serif';
    ctx.fillStyle = '#94a3b8';
    ctx.fillText(this.data.regulatoryNotice || 'Strictly held under the Chatham House Rule. Unauthorised recording or broadcasting is prohibited.', cx, h - 48);
    ctx.restore();
  }

  /* =========================================================================
   * ARCHETYPE 12: CIVIC REFERENDUM & POLL (iDecide — Verified Citizen Debate)
   * ========================================================================= */
  _renderArchetypeCivicReferendum(ctx, w, h) {
    const cx = w / 2;
    const isTall = h >= 1400;

    // 1. Parliamentary Rotunda Split Gradient (Cobalt into Crimson)
    const grad = ctx.createLinearGradient(0, 0, w, h);
    grad.addColorStop(0, '#1e3a8a');
    grad.addColorStop(0.5, '#0f172a');
    grad.addColorStop(1, '#881337');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, w, h);

    // 2. Procedural Rotunda Light Rays
    this._renderRotundaLightRays(ctx, w, h);

    // 3. Stately Frame
    ctx.save();
    ctx.strokeStyle = '#60a5fa';
    ctx.lineWidth = 2.5;
    ctx.strokeRect(36, 36, w - 72, h - 72);
    ctx.restore();

    // 4. Top Header
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#93c5fd';
    ctx.font = '800 22px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.badge || 'IDECIDE VERIFIED CIVIC CONSENSUS').toUpperCase(), cx, 88, 4);

    ctx.fillStyle = '#fde047';
    ctx.font = '700 20px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.kicker || 'LIVE PUBLIC POLICY TOWN HALL').toUpperCase(), cx, 128, 4);
    ctx.restore();

    // 5. Headline
    const titleLines = String(this.data.title || "CIVIC DEBATE\n& LIVE POLL").toUpperCase().split(/\r\n|\r|\n/);
    const startY = isTall ? 280 : 210;
    titleLines.slice(0, 2).forEach((line, idx) => {
      this._renderCondensedBlockText(ctx, line, cx, startY + idx * 105, 98, '#ffffff', '#e0e7ff', '#3730a3', 1.0, 1.05);
    });

    // Subtitle
    const subY = startY + Math.min(2, titleLines.length) * 105 - 10;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '600 24px "Montserrat", sans-serif';
    ctx.fillStyle = '#cbd5e1';
    ctx.fillText(this.data.subtitle || 'Cryptographically Verified Citizen Voting & Impact Matrix', cx, subY);
    ctx.restore();

    // 6. Metric Pill (Zero-Bot Verified Ballot)
    const pillY = isTall ? subY + 64 : subY + 32;
    ctx.save();
    ctx.fillStyle = '#4f46e5';
    this._roundRect(ctx, cx - 320, pillY, 640, isTall ? 80 : 68, 20);
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.font = `900 ${isTall ? 36 : 30}px "Montserrat", sans-serif`;
    ctx.textAlign = 'center';
    ctx.fillText((this.data.accentMetric || 'ZERO-BOT VERIFIED BALLOT').toUpperCase(), cx, pillY + (isTall ? 52 : 44));
    ctx.restore();

    // 7. Main Venue & Stream Card
    const cardY = isTall ? pillY + 130 : pillY + 85;
    const cardH = isTall ? 320 : 200;
    ctx.save();
    ctx.fillStyle = 'rgba(15, 23, 42, 0.92)';
    this._roundRect(ctx, w * 0.08, cardY, w * 0.84, cardH, 20);
    ctx.fill();
    ctx.strokeStyle = '#60a5fa';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#fde047';
    ctx.font = '800 26px "Montserrat", sans-serif';
    ctx.fillText([(this.data.date || 'TUESDAY 24 NOVEMBER 2026'), (this.data.time || '7:00 PM LIVE STREAM')].join('  •  ').toUpperCase(), cx, cardY + 58);

    ctx.fillStyle = '#ffffff';
    ctx.font = '900 32px "Montserrat", sans-serif';
    ctx.fillText((this.data.venue || 'WESTMINSTER CIVIC FORUM').toUpperCase(), cx, cardY + 116);

    ctx.fillStyle = '#cbd5e1';
    ctx.font = '600 21px "Montserrat", sans-serif';
    ctx.fillText(this.data.address || 'Great George Street, London SW1P 3AA', cx, cardY + 162);

    const perksArr = Array.isArray(this.data.perks) ? this.data.perks : String(this.data.perks || 'LIVE SENTIMENT, EXPERT PANEL, INSTANT VERDICT').split(',');
    ctx.fillStyle = '#818cf8';
    ctx.font = '800 20px "Montserrat", sans-serif';
    ctx.fillText(perksArr.map((s) => s.trim().toUpperCase()).filter(Boolean).join('   •   '), cx, cardY + 220);
    ctx.restore();

    // 8. Live Citizen Vote Breakdown Visualizer (Policy Sentiment Matrix)
    const matrixY = isTall ? cardY + cardH + 54 : cardY + cardH + 20;
    const matrixH = isTall ? 260 : 130;
    ctx.save();
    ctx.fillStyle = 'rgba(15, 23, 42, 0.92)';
    this._roundRect(ctx, w * 0.08, matrixY, w * 0.84, matrixH, 18);
    ctx.fill();
    ctx.strokeStyle = '#818cf8';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#93c5fd';
    ctx.font = '800 18px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, 'LIVE PUBLIC POLICY SENTIMENT BREAKDOWN', cx, matrixY + 40, 2);

    // Split progress bar (68% Ayes vs 32% Noes)
    const barX = w * 0.12;
    const barW = w * 0.76;
    const barY = matrixY + 68;
    const barH = 46;
    const splitW = barW * 0.68;

    // Rounded clipping container
    ctx.save();
    this._roundRect(ctx, barX, barY, barW, barH, 12);
    ctx.clip();

    // Ayes (Emerald Green)
    ctx.fillStyle = '#10b981';
    ctx.fillRect(barX, barY, splitW, barH);

    // Noes (Crimson Rose)
    ctx.fillStyle = '#f43f5e';
    ctx.fillRect(barX + splitW, barY, barW - splitW, barH);
    ctx.restore();

    ctx.save();
    ctx.fillStyle = '#ffffff';
    ctx.font = '900 20px "Montserrat", sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText('68% IN FAVOUR (AYES)', barX + 20, barY + 31);
    ctx.textAlign = 'right';
    ctx.fillText('32% OPPOSED (NOES)', barX + barW - 20, barY + 31);
    ctx.restore();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#cbd5e1';
    ctx.font = '700 17px "Montserrat", sans-serif';
    ctx.fillText('42,850 VERIFIED REGISTERED RESIDENTS  •  SHA-256 ZERO-KNOWLEDGE PROOF', cx, matrixY + (isTall ? 164 : 116));
    ctx.restore();

    // 9. Civic Action Ribbon
    const actionY = isTall ? matrixY + matrixH + 54 : matrixY + matrixH + 20;
    ctx.save();
    ctx.fillStyle = 'rgba(79, 70, 229, 0.45)';
    this._roundRect(ctx, w * 0.08, actionY, w * 0.84, 58, 14);
    ctx.fill();
    ctx.strokeStyle = '#60a5fa';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#fde047';
    ctx.font = '800 18px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, 'DEMOCRATIC VOTING OPEN TO ALL UK RESIDENTS  •  OFFICIAL TOWN HALL DEBATE', cx, actionY + 36, 2);
    ctx.restore();

    // 10. Official Citizen Voting Portal Strip
    const rsvpY = isTall ? actionY + 98 : actionY + 68;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#93c5fd';
    ctx.font = '800 18px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, 'OFFICIAL CITIZEN VOTING & VERIFICATION PORTAL', cx, rsvpY, 2);

    ctx.fillStyle = '#ffffff';
    ctx.font = '900 28px "Montserrat", sans-serif';
    ctx.fillText('020 7946 0192   •   VOTE@IDECIDE.APP', cx, rsvpY + 38);
    ctx.restore();

    // 11. Civic Ballot Seal & Scannable QR Code
    const credY = isTall ? rsvpY + 130 : h * 0.82;
    this._renderWaxCrestSeal(ctx, w * 0.28, isTall ? credY + 20 : credY, 52, 'CIVIC CONSENSUS', 'AUDITED', '#4338ca', '#60a5fa');

    if (this.data.qrUrl) {
      this._renderScannableQrBadge(ctx, w - 180, isTall ? credY - 30 : h * 0.76, 120, this.data.qrUrl, 'dark');
    }

    // 12. Statutory Notice
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '600 15px "Montserrat", sans-serif';
    ctx.fillStyle = '#94a3b8';
    ctx.fillText(this.data.regulatoryNotice || 'Cryptographically audited ballot integrity powered by iDecide Sovereign Consensus Engine.', cx, h - 48);
    ctx.restore();
  }

  /* =========================================================================
   * ARCHETYPE 13: VERIFIED RENT PASSPORT (TenantScore — PropTech & Open House)
   * ========================================================================= */
  _renderArchetypeTenantRentPassport(ctx, w, h) {
    const cx = w / 2;
    const isTall = h >= 1400;

    // 1. Slate & Emerald Backdrop
    const grad = ctx.createLinearGradient(0, 0, w, h);
    grad.addColorStop(0, '#0f172a');
    grad.addColorStop(0.45, '#064e3b');
    grad.addColorStop(1, '#022c22');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, w, h);

    // 2. Penthouse Window Mullions
    this._renderPenthouseMullions(ctx, w, h);

    // 3. Stately Emerald Frame
    ctx.save();
    ctx.strokeStyle = '#10b981';
    ctx.lineWidth = 2.5;
    ctx.strokeRect(36, 36, w - 72, h - 72);
    ctx.restore();

    // 4. Top Header
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#34d399';
    ctx.font = '800 22px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.badge || 'TENANTSCORE PROPERTY & CREDIT VERIFICATION').toUpperCase(), cx, 88, 4);

    ctx.fillStyle = '#fde047';
    ctx.font = '700 20px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, (this.data.kicker || 'EXCLUSIVE LANDLORD & TENANT SHOWCASE').toUpperCase(), cx, 128, 4);
    ctx.restore();

    // 5. Headline
    const titleLines = String(this.data.title || "VERIFIED RENT\nPASSPORT").toUpperCase().split(/\r\n|\r|\n/);
    const startY = isTall ? 280 : 210;
    titleLines.slice(0, 2).forEach((line, idx) => {
      this._renderCondensedBlockText(ctx, line, cx, startY + idx * 105, 98, '#ffffff', '#a7f3d0', '#047857', 1.0, 1.05);
    });

    // Subtitle
    const subY = startY + Math.min(2, titleLines.length) * 105 - 10;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '600 24px "Montserrat", sans-serif';
    ctx.fillStyle = '#cbd5e1';
    ctx.fillText(this.data.subtitle || 'Instant Open Banking Affordability & Reference Scoring', cx, subY);
    ctx.restore();

    // 6. Circular Trust Gauge (99.4%)
    const gaugeY = isTall ? subY + 130 : 510;
    this._renderCircularTrustGauge(ctx, cx, gaugeY, isTall ? 88 : 70, 99.4, '99.4%', 'RENT GUARANTEE SCORE');

    // 7. Property & Viewing Card
    const cardY = isTall ? gaugeY + 140 : 640;
    const cardH = isTall ? 320 : 200;
    ctx.save();
    ctx.fillStyle = 'rgba(15, 23, 42, 0.92)';
    this._roundRect(ctx, w * 0.08, cardY, w * 0.84, cardH, 20);
    ctx.fill();
    ctx.strokeStyle = '#10b981';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#fde047';
    ctx.font = '800 26px "Montserrat", sans-serif';
    ctx.fillText([(this.data.date || 'SATURDAY 21 NOVEMBER 2026'), (this.data.time || '11:00 AM – 3:00 PM VIEWINGS')].join('  •  ').toUpperCase(), cx, cardY + 58);

    ctx.fillStyle = '#ffffff';
    ctx.font = '900 32px "Montserrat", sans-serif';
    ctx.fillText((this.data.venue || 'KENSINGTON RESIDENCES SHOWCASE').toUpperCase(), cx, cardY + 116);

    ctx.fillStyle = '#cbd5e1';
    ctx.font = '600 21px "Montserrat", sans-serif';
    ctx.fillText(this.data.address || 'High Street Kensington, London W8 5SE', cx, cardY + 162);

    const perksArr = Array.isArray(this.data.perks) ? this.data.perks : String(this.data.perks || 'OPEN BANKING CHECK, ZERO DEPOSIT OPTION, INSTANT LEASE').split(',');
    ctx.fillStyle = '#34d399';
    ctx.font = '800 20px "Montserrat", sans-serif';
    ctx.fillText(perksArr.map((s) => s.trim().toUpperCase()).filter(Boolean).join('   •   '), cx, cardY + 220);
    ctx.restore();

    // 8. 3-Point Landlord Referencing Shield Matrix
    const gridY = isTall ? cardY + cardH + 54 : cardY + cardH + 20;
    const gridCardW = (w * 0.84 - 32) / 3;
    const gridCardH = isTall ? 260 : 130;
    const gridStartX = w * 0.08;

    const shieldData = [
      { step: 'AFFORDABILITY', title: '3.2X INCOME', desc: 'Open Banking Verified\nSalary & Steady Flow' },
      { step: 'RENT RECORD', title: '0 ARREARS', desc: '36-Month Flawless\nUK Tenancy Record' },
      { step: 'LEGAL STATUS', title: 'RIGHT TO RENT', desc: 'FCA Regulated ID\n& Complete Reference' }
    ];

    shieldData.forEach((s, idx) => {
      const bx = gridStartX + idx * (gridCardW + 16);
      ctx.save();
      ctx.fillStyle = 'rgba(15, 23, 42, 0.90)';
      this._roundRect(ctx, bx, gridY, gridCardW, gridCardH, 16);
      ctx.fill();
      ctx.strokeStyle = idx === 1 ? '#10b981' : '#334155';
      ctx.lineWidth = 1.5;
      ctx.stroke();

      ctx.textAlign = 'center';
      ctx.fillStyle = '#34d399';
      ctx.font = '900 18px "Montserrat", sans-serif';
      ctx.fillText(s.step, bx + gridCardW / 2, gridY + 38);

      ctx.fillStyle = '#ffffff';
      ctx.font = '800 20px "Montserrat", sans-serif';
      ctx.fillText(s.title, bx + gridCardW / 2, gridY + 76);

      ctx.fillStyle = '#94a3b8';
      ctx.font = '600 15px "Montserrat", sans-serif';
      const lines = s.desc.split('\n');
      lines.forEach((line, lIdx) => {
        ctx.fillText(line, bx + gridCardW / 2, gridY + 118 + lIdx * 24);
      });
      ctx.restore();
    });

    // 9. Pre-Qualified Fast-Track Ribbon
    const actionY = isTall ? gridY + gridCardH + 54 : gridY + gridCardH + 20;
    ctx.save();
    ctx.fillStyle = 'rgba(6, 78, 59, 0.60)';
    this._roundRect(ctx, w * 0.08, actionY, w * 0.84, 58, 14);
    ctx.fill();
    ctx.strokeStyle = '#10b981';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#a7f3d0';
    ctx.font = '800 18px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, 'PRE-QUALIFIED TENANT GUARANTEE  •  24-HOUR MOVE-IN FAST-TRACK CERTIFIED', cx, actionY + 36, 2);
    ctx.restore();

    // 10. Kensington Private Lettings Strip
    const rsvpY = isTall ? actionY + 98 : actionY + 68;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#34d399';
    ctx.font = '800 18px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, 'KENSINGTON PRIVATE LETTINGS DESK', cx, rsvpY, 2);

    ctx.fillStyle = '#ffffff';
    ctx.font = '900 28px "Montserrat", sans-serif';
    ctx.fillText('020 7946 0441   •   LETTINGS@TENANTSCORE.UK', cx, rsvpY + 38);
    ctx.restore();

    // 11. TenantScore Wax Seal & Scannable QR Code
    const credY = isTall ? rsvpY + 130 : h * 0.82;
    this._renderWaxCrestSeal(ctx, w * 0.28, isTall ? credY + 20 : credY, 52, 'TENANT PASSPORT', 'VERIFIED', '#064e3b', '#10b981');

    if (this.data.qrUrl) {
      this._renderScannableQrBadge(ctx, w - 180, isTall ? credY - 30 : h * 0.76, 120, this.data.qrUrl, 'dark');
    }

    // 12. Statutory Notice
    ctx.save();
    ctx.textAlign = 'center';
    ctx.font = '600 15px "Montserrat", sans-serif';
    ctx.fillStyle = '#94a3b8';
    ctx.fillText(this.data.regulatoryNotice || 'TenantScore UK. Compliant with the Tenant Fees Act 2019 & UK GDPR credit referencing standards.', cx, h - 48);
    ctx.restore();
  }
  /* =========================================================================
   * ARCHETYPE 14: TENANT CREDIT BOOST MOCKUP (Experian Style)
   * Inspired by 1250 Experian score badge, phone frame mockup, rocket boost celebration
   * ========================================================================= */
  _renderArchetypeTenantCreditBoost(ctx, w, h) {
    const cx = w / 2;
    const isTall = h >= 1400;

    // 1. Soft Sky & Mint Studio Backdrop
    const grad = ctx.createLinearGradient(0, 0, w, h);
    grad.addColorStop(0, '#f0fdfa');
    grad.addColorStop(0.5, '#e0f2fe');
    grad.addColorStop(1, '#f1f5f9');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, w, h);

    // Decorative Ambient Circular Rings
    ctx.save();
    ctx.strokeStyle = 'rgba(56, 189, 248, 0.18)';
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.arc(w * 0.15, h * 0.18, 220, 0, Math.PI * 2);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(w * 0.85, h * 0.75, 320, 0, Math.PI * 2);
    ctx.stroke();
    ctx.restore();

    // 2. Phone Device Sizing & Coordinates
    const phoneW = Math.min(840, w * 0.84);
    const phoneH = isTall ? Math.min(1560, h * 0.83) : Math.min(1120, h * 0.85);
    const phoneX = (w - phoneW) / 2;
    const phoneY = isTall ? Math.max(90, (h - phoneH) / 2 - 25) : Math.max(40, (h - phoneH) / 2 - 20);

    // 3. Render 3D Rocket Blasting Off (Overlapping Top-Left of Phone Frame)
    const rocketX = phoneX + 45;
    const rocketY = phoneY + 45;
    this._renderRocketIllustration(ctx, rocketX, rocketY, isTall ? 0.95 : 0.82);

    // 4. Render Smartphone Outer Frame
    this._renderPhoneDeviceFrame(ctx, phoneX, phoneY, phoneW, phoneH, {
      radius: 52,
      bezelColor: '#bae6fd',
      bezelWidth: 10
    });

    // 5. Clip Inside Phone Screen
    ctx.save();
    this._roundRect(ctx, phoneX + 8, phoneY + 8, phoneW - 16, phoneH - 16, 44);
    ctx.clip();

    // Screen Upper Surface (Crisp White)
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(phoneX, phoneY, phoneW, phoneH);

    // Confetti Ribbon Particles
    this._renderConfettiParticles(ctx, phoneX + 10, phoneY + 10, phoneW - 20, isTall ? 580 : 420);

    // Kicker & Hero Boost Headline
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#64748b';
    ctx.font = '700 18px "Montserrat", sans-serif';
    const kickerText = (this.data.kicker || 'TENANT RENT REPORTING BOOST').toUpperCase();
    this._drawTrackedText(ctx, kickerText, cx, phoneY + (isTall ? 95 : 68), 3);

    ctx.fillStyle = '#1e293b';
    const headFontSize = isTall ? 44 : 34;
    ctx.font = `900 ${headFontSize}px "Montserrat", sans-serif`;
    const titleText = this.data.title || 'You got a 45\npoint Boost!';
    const titleLines = String(titleText).split('\n');
    titleLines.forEach((line, lIdx) => {
      ctx.fillText(line, cx, phoneY + (isTall ? 148 : 110) + lIdx * (headFontSize + 8));
    });
    ctx.restore();

    // 6. Experian-Style Segmented Credit Gauge
    const gaugeR = isTall ? Math.min(185, phoneW * 0.24) : Math.min(150, phoneW * 0.22);
    const gaugeY = isTall ? phoneY + 475 : phoneY + 335;
    const scoreVal = this.data.accentMetric ? String(this.data.accentMetric).replace(/[^0-9]/g, '') || '1250' : '1250';
    this._renderSegmentedCreditGauge(ctx, cx, gaugeY, gaugeR, scoreVal, '1250', 'tenantscore', ['#3b82f6', '#8b5cf6', '#ec4899', '#f43f5e']);

    // Subtitle caption under gauge
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#475569';
    ctx.font = '600 17px "Montserrat", sans-serif';
    const subText = this.data.subtitle || 'Your boosted score means access to more and better rental homes with zero deposit';
    this._wrapText(ctx, subText, cx, gaugeY + gaugeR + (isTall ? 42 : 28), phoneW - 120, 24);
    ctx.restore();

    // 7. Organic Curved Deep Navy Wave (Experian Dark Lower Half)
    const waveY = isTall ? phoneY + 770 : phoneY + 540;
    ctx.save();
    const waveGrad = ctx.createLinearGradient(phoneX, waveY, phoneX, phoneY + phoneH);
    waveGrad.addColorStop(0, '#1e293b');
    waveGrad.addColorStop(0.35, '#0f172a');
    waveGrad.addColorStop(1, '#020617');
    ctx.fillStyle = waveGrad;

    ctx.beginPath();
    ctx.moveTo(phoneX, waveY);
    ctx.bezierCurveTo(phoneX + phoneW * 0.32, waveY - 48, phoneX + phoneW * 0.68, waveY + 36, phoneX + phoneW, waveY - 14);
    ctx.lineTo(phoneX + phoneW, phoneY + phoneH);
    ctx.lineTo(phoneX, phoneY + phoneH);
    ctx.closePath();
    ctx.fill();
    ctx.restore();

    // 8. Interactive Metric Impact Chips (Glassmorphic Pills)
    const chipsY = isTall ? waveY + 55 : waveY + 32;
    const chips = [
      { icon: '↗', label: 'Score up by 45 pts', color: '#34d399' },
      { icon: '🔄', label: '36 mos on-time rent', color: '#38bdf8' },
      { icon: '🔒', label: 'Passport verified', color: '#facc15' }
    ];

    const chipGap = 12;
    const chipW = (phoneW - 70 - chipGap * 2) / 3;
    const chipH = isTall ? 48 : 40;

    chips.forEach((chip, cIdx) => {
      const chipX = phoneX + 35 + cIdx * (chipW + chipGap);
      ctx.save();
      ctx.fillStyle = 'rgba(255, 255, 255, 0.10)';
      this._roundRect(ctx, chipX, chipsY, chipW, chipH, 24);
      ctx.fill();
      ctx.strokeStyle = 'rgba(255, 255, 255, 0.18)';
      ctx.lineWidth = 1;
      ctx.stroke();

      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillStyle = chip.color;
      ctx.font = '700 16px "Montserrat", sans-serif';
      ctx.fillText(`${chip.icon}  ${chip.label}`, chipX + chipW / 2, chipsY + chipH / 2);
      ctx.restore();
    });

    // 9. Floating White Elevated Card (Experian CreditExpert Style)
    const cardMarginX = 35;
    const cardX = phoneX + cardMarginX;
    const cardW = phoneW - cardMarginX * 2;
    const cardH = isTall ? 280 : 210;
    const cardY = isTall ? chipsY + 70 : chipsY + 52;

    ctx.save();
    ctx.shadowColor = 'rgba(0, 0, 0, 0.35)';
    ctx.shadowBlur = 24;
    ctx.shadowOffsetY = 10;
    ctx.fillStyle = '#ffffff';
    this._roundRect(ctx, cardX, cardY, cardW, cardH, 20);
    ctx.fill();
    ctx.restore();

    // Inside Floating Card
    ctx.save();
    // Card Title
    ctx.fillStyle = '#0f172a';
    ctx.font = '800 22px "Montserrat", sans-serif';
    ctx.fillText('Landlord Instant Referencing Passport', cardX + 28, cardY + 38);

    // Search Icon & Explainer Body
    ctx.fillStyle = '#64748b';
    ctx.font = '500 16px "Montserrat", sans-serif';
    const cardDesc = 'Pre-approved for prime UK residential tenancies with zero upfront deposit. Verified salary, ID & tenancy history.';
    this._wrapText(ctx, cardDesc, cardX + 28, cardY + 72, cardW - 170, 22);

    // Call-to-Action Link
    ctx.fillStyle = '#2563eb';
    ctx.font = '800 18px "Montserrat", sans-serif';
    ctx.fillText('View Verified Passport  ›', cardX + 28, cardY + (isTall ? 170 : 140));

    // Scannable Verification QR Code on Right
    if (this.data.qrUrl) {
      const qrSize = isTall ? 108 : 88;
      this._renderScannableQrBadge(ctx, cardX + cardW - qrSize - 20, cardY + (isTall ? 32 : 24), qrSize, this.data.qrUrl, 'light_compact');
    }
    ctx.restore();

    // 10. Secondary Status Pill (Zero CCJs / Open Banking Guarantee)
    if (isTall) {
      const statusPillY = cardY + cardH + 26;
      ctx.save();
      ctx.fillStyle = 'rgba(16, 185, 129, 0.18)';
      this._roundRect(ctx, cardX, statusPillY, cardW, 46, 12);
      ctx.fill();
      ctx.strokeStyle = '#10b981';
      ctx.lineWidth = 1;
      ctx.stroke();

      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillStyle = '#6ee7b7';
      ctx.font = '700 16px "Montserrat", sans-serif';
      ctx.fillText('✓  FCA OPEN BANKING CERTIFIED  •  ZERO ARREARS  •  PRE-QUALIFIED', cx, statusPillY + 23);
      ctx.restore();
    }

    // End Screen Clip
    ctx.restore();

    // 11. Bottom Exterior Statutory Notice (Outside Phone Mockup)
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#64748b';
    ctx.font = '600 15px "Montserrat", sans-serif';
    const noticeText = this.data.regulatoryNotice || 'TenantScore UK • Compliant with Tenant Fees Act 2019 & Open Banking Standards • www.scoretenant.com';
    ctx.fillText(noticeText, cx, h - 30);
    ctx.restore();
  }

  /* =========================================================================
   * ARCHETYPE 15: TAX HEALTH SCORE MOCKUP (Experian Style for iAccount)
   * Real-time Tax Health Score, HMRC MTD compliance meter, estimated tax savings card
   * ========================================================================= */
  _renderArchetypeTaxHealthScore(ctx, w, h) {
    const cx = w / 2;
    const isTall = h >= 1400;

    // 1. Corporate Sapphire & Deep Navy Studio Backdrop
    const grad = ctx.createLinearGradient(0, 0, w, h);
    grad.addColorStop(0, '#020617');
    grad.addColorStop(0.5, '#0f172a');
    grad.addColorStop(1, '#091e3a');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, w, h);

    // Decorative Ambient Emerald & Cyan Lighting
    ctx.save();
    const glow1 = ctx.createRadialGradient(w * 0.2, h * 0.15, 20, w * 0.2, h * 0.15, 340);
    glow1.addColorStop(0, 'rgba(16, 185, 129, 0.22)');
    glow1.addColorStop(1, 'rgba(16, 185, 129, 0)');
    ctx.fillStyle = glow1;
    ctx.fillRect(0, 0, w, h);

    const glow2 = ctx.createRadialGradient(w * 0.8, h * 0.8, 30, w * 0.8, h * 0.8, 420);
    glow2.addColorStop(0, 'rgba(37, 99, 235, 0.25)');
    glow2.addColorStop(1, 'rgba(37, 99, 235, 0)');
    ctx.fillStyle = glow2;
    ctx.fillRect(0, 0, w, h);
    ctx.restore();

    // 2. Phone Device Sizing & Coordinates
    const phoneW = Math.min(840, w * 0.84);
    const phoneH = isTall ? Math.min(1560, h * 0.83) : Math.min(1120, h * 0.85);
    const phoneX = (w - phoneW) / 2;
    const phoneY = isTall ? Math.max(90, (h - phoneH) / 2 - 25) : Math.max(40, (h - phoneH) / 2 - 20);

    // 3. Render Smartphone Outer Frame (Sleek Metallic Emerald Bezel)
    this._renderPhoneDeviceFrame(ctx, phoneX, phoneY, phoneW, phoneH, {
      radius: 52,
      bezelColor: '#6ee7b7',
      bezelWidth: 10
    });

    // 4. Clip Inside Phone Screen
    ctx.save();
    this._roundRect(ctx, phoneX + 8, phoneY + 8, phoneW - 16, phoneH - 16, 44);
    ctx.clip();

    // Screen Upper Surface (Crisp White)
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(phoneX, phoneY, phoneW, phoneH);

    // Kicker & Hero Tax Readiness Headline
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#059669';
    ctx.font = '800 18px "Montserrat", sans-serif';
    const kickerText = (this.data.kicker || 'REAL-TIME STATUTORY READINESS SCORE').toUpperCase();
    this._drawTrackedText(ctx, kickerText, cx, phoneY + (isTall ? 95 : 68), 3);

    ctx.fillStyle = '#0f172a';
    const headFontSize = isTall ? 44 : 34;
    ctx.font = `900 ${headFontSize}px "Montserrat", sans-serif`;
    const titleText = this.data.title || 'You got a 980\nTax Health Score';
    const titleLines = String(titleText).split('\n');
    titleLines.forEach((line, lIdx) => {
      ctx.fillText(line, cx, phoneY + (isTall ? 148 : 110) + lIdx * (headFontSize + 8));
    });
    ctx.restore();

    // 5. Experian-Style Segmented Tax Health Gauge (980 out of 1000)
    const gaugeR = isTall ? Math.min(185, phoneW * 0.24) : Math.min(150, phoneW * 0.22);
    const gaugeY = isTall ? phoneY + 475 : phoneY + 335;
    const scoreVal = this.data.accentMetric ? String(this.data.accentMetric).replace(/[^0-9]/g, '') || '980' : '980';
    this._renderSegmentedCreditGauge(ctx, cx, gaugeY, gaugeR, scoreVal, '1000', 'iaccount', ['#0284c7', '#2563eb', '#10b981', '#059669']);

    // Subtitle caption under gauge
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#475569';
    ctx.font = '600 17px "Montserrat", sans-serif';
    const subText = this.data.subtitle || 'A quick health check of your records, receipts and upcoming tax deadlines';
    this._wrapText(ctx, subText, cx, gaugeY + gaugeR + (isTall ? 42 : 28), phoneW - 120, 24);
    ctx.restore();

    // 6. Organic Curved Royal Navy Wave (Dark Lower Half)
    const waveY = isTall ? phoneY + 770 : phoneY + 540;
    ctx.save();
    const waveGrad = ctx.createLinearGradient(phoneX, waveY, phoneX, phoneY + phoneH);
    waveGrad.addColorStop(0, '#0f172a');
    waveGrad.addColorStop(0.35, '#064e3b');
    waveGrad.addColorStop(1, '#022c22');
    ctx.fillStyle = waveGrad;

    ctx.beginPath();
    ctx.moveTo(phoneX, waveY);
    ctx.bezierCurveTo(phoneX + phoneW * 0.32, waveY - 48, phoneX + phoneW * 0.68, waveY + 36, phoneX + phoneW, waveY - 14);
    ctx.lineTo(phoneX + phoneW, phoneY + phoneH);
    ctx.lineTo(phoneX, phoneY + phoneH);
    ctx.closePath();
    ctx.fill();
    ctx.restore();

    // 7. Interactive Metric Impact Chips (Glassmorphic Emerald/Sapphire Pills)
    const chipsY = isTall ? waveY + 55 : waveY + 32;
    const chips = [
      { icon: '↗', label: 'Tax saved +18%', color: '#34d399' },
      { icon: '🔄', label: 'Digital records kept', color: '#38bdf8' },
      { icon: '🔔', label: 'Deadline reminders', color: '#facc15' }
    ];

    const chipGap = 12;
    const chipW = (phoneW - 70 - chipGap * 2) / 3;
    const chipH = isTall ? 48 : 40;

    chips.forEach((chip, cIdx) => {
      const chipX = phoneX + 35 + cIdx * (chipW + chipGap);
      ctx.save();
      ctx.fillStyle = 'rgba(255, 255, 255, 0.10)';
      this._roundRect(ctx, chipX, chipsY, chipW, chipH, 24);
      ctx.fill();
      ctx.strokeStyle = 'rgba(255, 255, 255, 0.18)';
      ctx.lineWidth = 1;
      ctx.stroke();

      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillStyle = chip.color;
      ctx.font = '700 16px "Montserrat", sans-serif';
      ctx.fillText(`${chip.icon}  ${chip.label}`, chipX + chipW / 2, chipsY + chipH / 2);
      ctx.restore();
    });

    // 8. Floating White Elevated Card (estimated tax savings summary)
    const cardMarginX = 35;
    const cardX = phoneX + cardMarginX;
    const cardW = phoneW - cardMarginX * 2;
    const cardH = isTall ? 280 : 210;
    const cardY = isTall ? chipsY + 70 : chipsY + 52;

    ctx.save();
    ctx.shadowColor = 'rgba(0, 0, 0, 0.35)';
    ctx.shadowBlur = 24;
    ctx.shadowOffsetY = 10;
    ctx.fillStyle = '#ffffff';
    this._roundRect(ctx, cardX, cardY, cardW, cardH, 20);
    ctx.fill();
    ctx.restore();

    // Inside Floating Card
    ctx.save();
    // Card Title
    ctx.fillStyle = '#065f46';
    ctx.font = '800 22px "Montserrat", sans-serif';
    ctx.fillText('Estimated Tax Optimisation: £14,850', cardX + 28, cardY + 38);

    // Explainer Body
    ctx.fillStyle = '#475569';
    ctx.font = '500 16px "Montserrat", sans-serif';
    const cardDesc = 'VAT, Corporation Tax provisions & R&D credits estimated from your reconciled records. Check with your accountant before filing.';
    this._wrapText(ctx, cardDesc, cardX + 28, cardY + 72, cardW - 170, 22);

    // Call-to-Action Link
    ctx.fillStyle = '#059669';
    ctx.font = '800 18px "Montserrat", sans-serif';
    ctx.fillText('Download your tax summary  ›', cardX + 28, cardY + (isTall ? 170 : 140));

    // Scannable Verification QR Code on Right
    if (this.data.qrUrl) {
      const qrSize = isTall ? 108 : 88;
      this._renderScannableQrBadge(ctx, cardX + cardW - qrSize - 20, cardY + (isTall ? 32 : 24), qrSize, this.data.qrUrl, 'light_compact');
    }
    ctx.restore();

    // 9. Secondary Status Pill (record-keeping status)
    if (isTall) {
      const statusPillY = cardY + cardH + 26;
      ctx.save();
      ctx.fillStyle = 'rgba(16, 185, 129, 0.18)';
      this._roundRect(ctx, cardX, statusPillY, cardW, 46, 12);
      ctx.fill();
      ctx.strokeStyle = '#10b981';
      ctx.lineWidth = 1;
      ctx.stroke();

      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillStyle = '#6ee7b7';
      ctx.font = '700 16px "Montserrat", sans-serif';
      ctx.fillText('✓  RECEIPTS MATCHED  •  BANK RECONCILED  •  DEADLINES TRACKED', cx, statusPillY + 23);
      ctx.restore();
    }

    // End Screen Clip
    ctx.restore();

    // 10. Bottom Exterior Statutory Notice
    ctx.save();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#94a3b8';
    ctx.font = '600 15px "Montserrat", sans-serif';
    const noticeText = this.data.regulatoryNotice || 'iAccount • Automated double-entry reconciliation • Tax figures are estimates';
    ctx.fillText(noticeText, cx, h - 30);
    ctx.restore();
  }

  /**
   * Render Bank-Grade Mathematical Guilloche Rosette (Spirograph Security Curves)
   */
  _renderGuillochePattern(ctx, cx, cy, radius, loops = 64, strokeStyle = '#eab308', alpha = 0.35) {
    ctx.save();
    ctx.globalAlpha = alpha;
    ctx.strokeStyle = strokeStyle;
    ctx.lineWidth = 1;
    ctx.beginPath();
    const R = radius;
    const r = radius * 0.38;
    const p = radius * 0.45;
    const totalSteps = loops * 16;
    for (let i = 0; i <= totalSteps; i++) {
      const theta = (i * Math.PI * 2) / 64;
      const x = cx + (R - r) * Math.cos(theta) + p * Math.cos(((R - r) / r) * theta);
      const y = cy + (R - r) * Math.sin(theta) - p * Math.sin(((R - r) / r) * theta);
      if (i === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    }
    ctx.stroke();
    ctx.restore();
  }

  /**
   * Render Constellation Network (Interconnected AI Data Nodes for C-Suite Execs)
   */
  _renderConstellationNetwork(ctx, w, h, nodeCount = 28, nodeColor = '#38bdf8', lineColor = 'rgba(56, 189, 248, 0.16)') {
    ctx.save();
    const nodes = [];
    for (let i = 0; i < nodeCount; i++) {
      const nx = ((i * 137.5 + 43) % (w - 120)) + 60;
      const ny = ((i * 211.3 + 97) % (h * 0.88)) + 60;
      nodes.push({ x: nx, y: ny, r: (i % 3) + 2 });
    }
    ctx.strokeStyle = lineColor;
    ctx.lineWidth = 1;
    ctx.beginPath();
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const dx = nodes[i].x - nodes[j].x;
        const dy = nodes[i].y - nodes[j].y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < 260) {
          ctx.moveTo(nodes[i].x, nodes[i].y);
          ctx.lineTo(nodes[j].x, nodes[j].y);
        }
      }
    }
    ctx.stroke();

    nodes.forEach((n) => {
      ctx.fillStyle = nodeColor;
      ctx.shadowColor = nodeColor;
      ctx.shadowBlur = 8;
      ctx.beginPath();
      ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2);
      ctx.fill();
    });
    ctx.restore();
  }

  /**
   * Render Circular 3D Trust Score Gauge (e.g. 99.4% Rent Guarantee Score)
   */
  _renderCircularTrustGauge(ctx, cx, cy, radius, percent = 99.4, scoreText = '99.4%', label = 'RENT GUARANTEE SCORE') {
    ctx.save();
    ctx.lineWidth = 14;
    ctx.lineCap = 'round';
    ctx.strokeStyle = 'rgba(16, 185, 129, 0.20)';
    ctx.beginPath();
    ctx.arc(cx, cy, radius, Math.PI * 0.75, Math.PI * 2.25);
    ctx.stroke();

    const startAngle = Math.PI * 0.75;
    const totalSweep = Math.PI * 1.5;
    const endAngle = startAngle + (percent / 100) * totalSweep;

    const arcGrad = ctx.createLinearGradient(cx - radius, cy - radius, cx + radius, cy + radius);
    arcGrad.addColorStop(0, '#10b981');
    arcGrad.addColorStop(0.5, '#34d399');
    arcGrad.addColorStop(1, '#fbbf24');

    ctx.strokeStyle = arcGrad;
    ctx.shadowColor = '#10b981';
    ctx.shadowBlur = 22;
    ctx.beginPath();
    ctx.arc(cx, cy, radius, startAngle, endAngle);
    ctx.stroke();

    ctx.shadowBlur = 0;
    ctx.fillStyle = 'rgba(2, 44, 34, 0.94)';
    ctx.beginPath();
    ctx.arc(cx, cy, radius - 20, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = '#eab308';
    ctx.lineWidth = 2.5;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#ffffff';
    ctx.font = '900 50px "Montserrat", sans-serif';
    ctx.fillText(scoreText, cx, cy + 18);

    ctx.fillStyle = '#a7f3d0';
    ctx.font = '800 18px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, label.toUpperCase(), cx, cy + radius + 32, 3);
    ctx.restore();
  }

  /**
   * Render High-Relief Embossed Wax Seal with Scalloped Edge & Trailing Gilded Ribbon
   */
  _renderWaxCrestSeal(ctx, cx, cy, radius, titleText, subText, waxColor = '#991b1b', ribbonColor = '#eab308') {
    ctx.save();
    // Dual draped ribbons hanging from seal
    const ribL = radius * 1.6;
    ctx.fillStyle = ribbonColor;
    ctx.beginPath();
    ctx.moveTo(cx - radius * 0.35, cy);
    ctx.lineTo(cx - radius * 0.55, cy + ribL);
    ctx.lineTo(cx - radius * 0.35, cy + ribL * 0.88);
    ctx.lineTo(cx - radius * 0.15, cy + ribL);
    ctx.lineTo(cx - radius * 0.10, cy);
    ctx.fill();

    ctx.beginPath();
    ctx.moveTo(cx + radius * 0.10, cy);
    ctx.lineTo(cx + radius * 0.15, cy + ribL);
    ctx.lineTo(cx + radius * 0.35, cy + ribL * 0.88);
    ctx.lineTo(cx + radius * 0.55, cy + ribL);
    ctx.lineTo(cx + radius * 0.35, cy);
    ctx.fill();

    // Scalloped wax edge
    ctx.fillStyle = waxColor;
    ctx.shadowColor = 'rgba(0, 0, 0, 0.65)';
    ctx.shadowBlur = 18;
    ctx.shadowOffsetY = 6;
    ctx.beginPath();
    const scallops = 24;
    for (let i = 0; i <= scallops; i++) {
      const a = (i * Math.PI * 2) / scallops;
      const r = radius + (i % 2 === 0 ? 5 : -3);
      const px = cx + r * Math.cos(a);
      const py = cy + r * Math.sin(a);
      if (i === 0) ctx.moveTo(px, py);
      else ctx.lineTo(px, py);
    }
    ctx.closePath();
    ctx.fill();

    // Gold inner stamp ring
    ctx.shadowBlur = 0;
    ctx.strokeStyle = '#eab308';
    ctx.lineWidth = 2.5;
    ctx.beginPath();
    ctx.arc(cx, cy, radius * 0.78, 0, Math.PI * 2);
    ctx.stroke();

    // Stamped Gold Text
    ctx.textAlign = 'center';
    ctx.fillStyle = '#fef08a';
    ctx.font = '800 13px "Montserrat", sans-serif';
    this._drawTrackedText(ctx, titleText.toUpperCase(), cx, cy - 8, 2);

    ctx.font = '900 16px "Montserrat", sans-serif';
    ctx.fillStyle = '#ffffff';
    this._drawTrackedText(ctx, subText.toUpperCase(), cx, cy + 14, 2);
    ctx.restore();
  }

  /**
   * Render Neoclassical Fluted Columns (Statutory Architecture for iAccount)
   */
  _renderNeoclassicalColumns(ctx, w, h, colW = 54, accent = '#3b82f6') {
    ctx.save();
    [0, w - colW].forEach((xPos) => {
      const colGrad = ctx.createLinearGradient(xPos, 0, xPos + colW, 0);
      colGrad.addColorStop(0, 'rgba(15, 23, 42, 0.95)');
      colGrad.addColorStop(0.3, 'rgba(30, 41, 59, 0.90)');
      colGrad.addColorStop(0.7, 'rgba(51, 65, 85, 0.90)');
      colGrad.addColorStop(1, 'rgba(15, 23, 42, 0.95)');
      ctx.fillStyle = colGrad;
      ctx.fillRect(xPos, 40, colW, h - 80);

      ctx.strokeStyle = accent;
      ctx.lineWidth = 1;
      ctx.globalAlpha = 0.35;
      for (let f = 10; f < colW; f += 9) {
        ctx.beginPath();
        ctx.moveTo(xPos + f, 60);
        ctx.lineTo(xPos + f, h - 100);
        ctx.stroke();
      }

      ctx.globalAlpha = 0.9;
      ctx.fillStyle = accent;
      ctx.fillRect(xPos - 6, 40, colW + 12, 16);
      ctx.fillRect(xPos - 12, 28, colW + 24, 12);

      ctx.fillRect(xPos - 6, h - 56, colW + 12, 16);
      ctx.fillRect(xPos - 12, h - 40, colW + 24, 16);
    });
    ctx.restore();
  }

  /**
   * Render Volumetric Light Rays (Parliamentary Rotunda Atmosphere for iDecide)
   */
  _renderRotundaLightRays(ctx, w, h) {
    ctx.save();
    const cx = w / 2;
    const topY = 0;
    const rayCount = 9;
    ctx.globalCompositeOperation = 'screen';
    for (let i = 0; i < rayCount; i++) {
      const spreadX = (i - Math.floor(rayCount / 2)) * (w * 0.22);
      const rayGrad = ctx.createLinearGradient(cx, topY, cx + spreadX, h);
      rayGrad.addColorStop(0, 'rgba(255, 255, 255, 0.28)');
      rayGrad.addColorStop(0.35, 'rgba(147, 197, 253, 0.12)');
      rayGrad.addColorStop(0.75, 'rgba(99, 102, 241, 0.04)');
      rayGrad.addColorStop(1, 'rgba(0, 0, 0, 0)');

      ctx.fillStyle = rayGrad;
      ctx.beginPath();
      ctx.moveTo(cx - 15, topY);
      ctx.lineTo(cx + 15, topY);
      ctx.lineTo(cx + spreadX + 90, h);
      ctx.lineTo(cx + spreadX - 90, h);
      ctx.closePath();
      ctx.fill();
    }
    ctx.restore();
  }

  /**
   * Render Architectural Penthouse Window Mullions (Luxury Skyline for ScoreTenant)
   */
  _renderPenthouseMullions(ctx, w, h) {
    ctx.save();
    ctx.strokeStyle = 'rgba(15, 23, 42, 0.75)';
    ctx.lineWidth = 14;
    [w * 0.28, w * 0.72].forEach((mx) => {
      ctx.beginPath();
      ctx.moveTo(mx, 0);
      ctx.lineTo(mx, h);
      ctx.stroke();

      ctx.strokeStyle = 'rgba(52, 211, 153, 0.25)';
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.moveTo(mx + 6, 0);
      ctx.lineTo(mx + 6, h);
      ctx.stroke();
    });

    ctx.strokeStyle = 'rgba(15, 23, 42, 0.75)';
    ctx.lineWidth = 12;
    ctx.beginPath();
    ctx.moveTo(0, h * 0.38);
    ctx.lineTo(w, h * 0.38);
    ctx.stroke();
    ctx.restore();
  }

  /* =========================================================================
   * SHARED COMPOSITING, SHADER & TYPOGRAPHY PRIMITIVES
   * ========================================================================= */

  /**
   * Determine if an asset is a built-in pre-rendered studio asset
   */
  _isBuiltInAsset(src) {
    if (!src) return false;
    const str = typeof src === 'string' ? src : (src.src || (src.getAttribute && src.getAttribute('src')) || '');
    if (!str) return false;
    return str.includes('/poster-studio?asset=') || 
           str.includes('bg_pool_palm_frame') || 
           str.includes('bg_tropical_purple') || 
           str.includes('bg_vip_gold_lounge') || 
           str.includes('bg_crimson_velvet') || 
           str.includes('bg_club_tunnel') || 
           str.includes('bg_family_golden_jubilee') || 
           str.includes('bg_fintech_sme_emerald') || 
           str.includes('bg_consumer_family_home') || 
           str.includes('bg_consumer_moving_joy') || 
           str.includes('bg_healthcare_clinical_surge') || 
           str.includes('subject_pool_party') || 
           str.includes('subject_vip_man') || 
           str.includes('subject_birthday_woman') || 
           str.includes('subject_club_dj');
  }

  /**
   * Retrieve color grading and atmospheric lighting specs per archetype
   */
  _getArchetypeAtmosphere(archetype) {
    const palMap = {
      vip_black_gold: {
        shade: [10, 8, 4],
        tint: '#d4af37',
        rim: '#fde047',
        light: false,
        contrast: 1.12,
        sat: 0.82,
        bright: 0.88,
        vignetteAlpha: 0.75
      },
      family_heirloom_gold: {
        shade: [6, 26, 16],
        tint: '#eab308',
        rim: '#fef08a',
        light: false,
        contrast: 1.10,
        sat: 0.85,
        bright: 0.90,
        vignetteAlpha: 0.70
      },
      neon_script_clash: {
        shade: [18, 4, 38],
        tint: '#c026d3',
        rim: '#38bdf8',
        light: false,
        contrast: 1.16,
        sat: 0.86,
        bright: 0.86,
        vignetteAlpha: 0.75
      },
      pool_tropical_split: {
        shade: [6, 44, 51],
        tint: '#0d9488',
        rim: '#38bdf8',
        light: true,
        contrast: 1.05,
        sat: 0.92,
        bright: 1.02,
        vignetteAlpha: 0.40
      },
      floral_chrome_overlap: {
        shade: [40, 6, 16],
        tint: '#e11d48',
        rim: '#fb7185',
        light: false,
        contrast: 1.12,
        sat: 0.85,
        bright: 0.88,
        vignetteAlpha: 0.72
      },
      kinetic_club_tunnel: {
        shade: [5, 8, 24],
        tint: '#6366f1',
        rim: '#f59e0b',
        light: false,
        contrast: 1.20,
        sat: 0.85,
        bright: 0.82,
        vignetteAlpha: 0.78
      },
      clinical_shift_surge: {
        shade: [15, 23, 42],
        tint: '#0284c7',
        rim: '#38bdf8',
        light: false,
        contrast: 1.06,
        sat: 0.90,
        bright: 0.92,
        vignetteAlpha: 0.65
      },
      clinical_recruitment_day: {
        shade: [15, 23, 42],
        tint: '#0284c7',
        rim: '#38bdf8',
        light: false,
        contrast: 1.06,
        sat: 0.90,
        bright: 0.92,
        vignetteAlpha: 0.65
      },
      fintech_facility_offer: {
        shade: [6, 44, 32],
        tint: '#10b981',
        rim: '#34d399',
        light: false,
        contrast: 1.10,
        sat: 0.88,
        bright: 0.90,
        vignetteAlpha: 0.70
      },
      consumer_loan_campaign: {
        shade: [15, 23, 42],
        tint: '#dc2626',
        rim: '#f87171',
        light: true,
        contrast: 1.05,
        sat: 0.95,
        bright: 1.00,
        vignetteAlpha: 0.45
      },
      retail_banking_features: {
        shade: [15, 23, 42],
        tint: '#dc2626',
        rim: '#f87171',
        light: true,
        contrast: 1.05,
        sat: 0.95,
        bright: 1.00,
        vignetteAlpha: 0.40
      },
      tax_statutory_clinic: {
        shade: [2, 6, 23],
        tint: '#059669',
        rim: '#6ee7b7',
        light: false,
        contrast: 1.10,
        sat: 0.85,
        bright: 0.88,
        vignetteAlpha: 0.72
      },
      tax_health_score_app: {
        shade: [2, 6, 23],
        tint: '#059669',
        rim: '#6ee7b7',
        light: false,
        contrast: 1.10,
        sat: 0.85,
        bright: 0.88,
        vignetteAlpha: 0.72
      },
      executive_board_summit: {
        shade: [3, 7, 18],
        tint: '#0284c7',
        rim: '#38bdf8',
        light: false,
        contrast: 1.12,
        sat: 0.80,
        bright: 0.88,
        vignetteAlpha: 0.75
      },
      civic_referendum_poll: {
        shade: [15, 7, 30],
        tint: '#9333ea',
        rim: '#c084fc',
        light: false,
        contrast: 1.10,
        sat: 0.85,
        bright: 0.90,
        vignetteAlpha: 0.70
      },
      tenant_rent_passport: {
        shade: [6, 78, 59],
        tint: '#10b981',
        rim: '#6ee7b7',
        light: false,
        contrast: 1.08,
        sat: 0.90,
        bright: 0.92,
        vignetteAlpha: 0.70
      },
      tenant_credit_boost_mockup: {
        shade: [15, 23, 42],
        tint: '#0284c7',
        rim: '#38bdf8',
        light: true,
        contrast: 1.05,
        sat: 0.92,
        bright: 1.00,
        vignetteAlpha: 0.45
      }
    };
    return palMap[archetype] || palMap.vip_black_gold;
  }

  _getFilmGrainTile() {
    if (this._grainTile) return this._grainTile;
    const can = (typeof document !== 'undefined') ? document.createElement('canvas') : null;
    if (!can) return null;
    can.width = 160;
    can.height = 160;
    const ctx = can.getContext('2d');
    if (!ctx) return null;
    const imgData = ctx.createImageData(160, 160);
    const d = imgData.data;
    for (let i = 0; i < d.length; i += 4) {
      const v = Math.floor(Math.random() * 255);
      d[i] = v;
      d[i + 1] = v;
      d[i + 2] = v;
      d[i + 3] = 255;
    }
    ctx.putImageData(imgData, 0, 0);
    this._grainTile = can;
    return can;
  }

  /**
   * Harmonise and tone an uploaded or external background image into the poster
   */
  _meshGradeBackdrop(img, archetype, targetW, targetH) {
    if (!img) return img;
    img.__meshBgCache = img.__meshBgCache || {};
    const cacheKey = `${archetype}_${targetW}_${targetH}`;
    if (img.__meshBgCache[cacheKey]) {
      return img.__meshBgCache[cacheKey];
    }

    const off = (typeof document !== 'undefined') ? document.createElement('canvas') : null;
    if (!off) return img;
    off.width = targetW;
    off.height = targetH;
    const g = off.getContext('2d');
    if (!g) return img;

    const pal = this._getArchetypeAtmosphere(archetype);
    const [r, gg, b] = pal.shade;

    const iw = img.naturalWidth || img.width || targetW;
    const ih = img.naturalHeight || img.height || targetH;
    const scale = Math.max(targetW / iw, targetH / ih);
    const nw = iw * scale;
    const nh = ih * scale;
    const nx = (targetW - nw) / 2;
    const ny = (targetH - nh) / 2;

    if ('filter' in g) {
      g.filter = `saturate(${pal.sat}) contrast(${pal.contrast}) brightness(${pal.bright})`;
    }
    g.drawImage(img, nx, ny, nw, nh);
    if ('filter' in g) {
      g.filter = 'none';
    }

    g.globalCompositeOperation = 'soft-light';
    g.globalAlpha = pal.light ? 0.28 : 0.45;
    g.fillStyle = pal.tint;
    g.fillRect(0, 0, targetW, targetH);

    g.globalCompositeOperation = 'multiply';
    g.globalAlpha = 1.0;
    const tone = g.createLinearGradient(0, 0, 0, targetH);
    tone.addColorStop(0, `rgba(${r},${gg},${b},${pal.light ? 0.25 : 0.65})`);
    tone.addColorStop(0.35, `rgba(${r},${gg},${b},${pal.light ? 0.05 : 0.20})`);
    tone.addColorStop(0.70, `rgba(${r},${gg},${b},${pal.light ? 0.15 : 0.40})`);
    tone.addColorStop(1, `rgba(${r},${gg},${b},${pal.light ? 0.35 : 0.85})`);
    g.fillStyle = tone;
    g.fillRect(0, 0, targetW, targetH);

    const vig = g.createRadialGradient(
      targetW / 2, targetH / 2, Math.min(targetW, targetH) * 0.28,
      targetW / 2, targetH / 2, Math.max(targetW, targetH) * 0.75
    );
    vig.addColorStop(0, 'rgba(0, 0, 0, 0)');
    vig.addColorStop(1, `rgba(${r},${gg},${b},${pal.vignetteAlpha})`);
    g.fillStyle = vig;
    g.fillRect(0, 0, targetW, targetH);

    const grainTile = this._getFilmGrainTile();
    if (grainTile) {
      g.globalCompositeOperation = 'overlay';
      g.globalAlpha = 0.055;
      g.fillStyle = g.createPattern(grainTile, 'repeat');
      g.fillRect(0, 0, targetW, targetH);
    }

    g.globalCompositeOperation = 'source-over';
    g.globalAlpha = 1.0;

    img.__meshBgCache[cacheKey] = off;
    return off;
  }

  /**
   * Seamlessly tone, feather, dissolve, and rim-light an uploaded portrait/subject
   */
  _meshGradeSubject(img, archetype, cfg = {}) {
    if (!img) return img;
    img.__meshSubCache = img.__meshSubCache || {};
    const cacheKey = `${archetype}_mesh`;
    if (img.__meshSubCache[cacheKey]) {
      return img.__meshSubCache[cacheKey];
    }

    const sw = img.naturalWidth || img.width || 687;
    const sh = img.naturalHeight || img.height || 920;
    const maxDim = 1400;
    const k = Math.min(1.0, maxDim / Math.max(sw, sh));
    const targetW = Math.max(1, Math.round(sw * k));
    const targetH = Math.max(1, Math.round(sh * k));

    const off = (typeof document !== 'undefined') ? document.createElement('canvas') : null;
    if (!off) return img;
    off.width = targetW;
    off.height = targetH;
    const g = off.getContext('2d');
    if (!g) return img;

    const pal = this._getArchetypeAtmosphere(archetype);

    if ('filter' in g) {
      g.filter = 'contrast(1.04) brightness(1.02) saturate(1.06)';
    }
    g.drawImage(img, 0, 0, targetW, targetH);
    if ('filter' in g) {
      g.filter = 'none';
    }

    let isOpaque = true;
    try {
      const p1 = g.getImageData(2, 2, 1, 1).data[3];
      const p2 = g.getImageData(targetW - 3, 2, 1, 1).data[3];
      isOpaque = (p1 + p2) > 400;
    } catch (_) {}

    // Subtle warm rim highlight on the outer contour
    g.globalCompositeOperation = 'source-atop';
    g.globalAlpha = 0.55;
    const rimGrad = g.createLinearGradient(targetW, 0, targetW * 0.70, 0);
    rimGrad.addColorStop(0, pal.rim + '55');
    rimGrad.addColorStop(1, pal.rim + '00');
    g.fillStyle = rimGrad;
    g.fillRect(0, 0, targetW, targetH);

    // Fade bottom edge seamlessly into poster base (only the bottom 20%)
    g.globalCompositeOperation = 'destination-out';
    const fadeBottom = g.createLinearGradient(0, targetH * 0.80, 0, targetH);
    fadeBottom.addColorStop(0, 'rgba(0, 0, 0, 0)');
    fadeBottom.addColorStop(1, 'rgba(0, 0, 0, 1)');
    g.fillStyle = fadeBottom;
    g.fillRect(0, 0, targetW, targetH);

    if (isOpaque) {
      // Subtle feathering for rectangular photos so there are NO sharp box borders
      const leftW = targetW * 0.12;
      const rightW = targetW * 0.08;
      const topH = targetH * 0.06;

      const fadeLeft = g.createLinearGradient(0, 0, leftW, 0);
      fadeLeft.addColorStop(0, 'rgba(0, 0, 0, 1)');
      fadeLeft.addColorStop(1, 'rgba(0, 0, 0, 0)');
      g.fillStyle = fadeLeft;
      g.fillRect(0, 0, leftW, targetH);

      const fadeRight = g.createLinearGradient(targetW, 0, targetW - rightW, 0);
      fadeRight.addColorStop(0, 'rgba(0, 0, 0, 1)');
      fadeRight.addColorStop(1, 'rgba(0, 0, 0, 0)');
      g.fillStyle = fadeRight;
      g.fillRect(targetW - rightW, 0, rightW, targetH);

      const fadeTop = g.createLinearGradient(0, 0, 0, topH);
      fadeTop.addColorStop(0, 'rgba(0, 0, 0, 1)');
      fadeTop.addColorStop(1, 'rgba(0, 0, 0, 0)');
      g.fillStyle = fadeTop;
      g.fillRect(0, 0, targetW, topH);
    } else {
      // For cutouts, provide a soft atmospheric blend on the far-left boundary
      // so any subject edges melt completely into the background without a sharp cut
      const leftDissolveW = Math.max(1, Math.round(targetW * 0.16));
      const dissolveLeft = g.createLinearGradient(0, 0, leftDissolveW, 0);
      dissolveLeft.addColorStop(0, 'rgba(0, 0, 0, 0.95)');
      dissolveLeft.addColorStop(0.40, 'rgba(0, 0, 0, 0.45)');
      dissolveLeft.addColorStop(1, 'rgba(0, 0, 0, 0)');
      g.fillStyle = dissolveLeft;
      g.fillRect(0, 0, leftDissolveW, targetH);
    }

    g.globalCompositeOperation = 'source-over';
    g.globalAlpha = 1.0;

    img.__meshSubCache[cacheKey] = off;
    return off;
  }

  /**
   * Render staged subject cutout (user-uploaded OR built-in photorealistic default)
   * with optional monochrome double-exposure ghost echo (Sample #3)
   */
  _renderStagedSubject(ctx, w, h, cfg) {
    const rawSource = this.processedSubjectCanvas || this.subjectImage || (this.data.showDefaultSubject !== false ? cfg.defaultAsset : null);
    if (!rawSource) return;

    // Harmonise and mesh user-uploaded or custom real photos seamlessly
    const rawSrcStr = (rawSource.src || (rawSource.getAttribute && rawSource.getAttribute('src')) || '');
    const isCustom = !this._isBuiltInAsset(rawSrcStr);

    let activeSource = rawSource;
    if (isCustom) {
      activeSource = this._meshGradeSubject(rawSource, this.archetype, cfg);
    }

    const scale = Number(this.data.subjectScale || 1.0);
    const offX = Number(this.data.subjectOffsetX || 0);
    const offY = Number(this.data.subjectOffsetY || 0);

    const srcW = activeSource.naturalWidth || activeSource.width || 687;
    const srcH = activeSource.naturalHeight || activeSource.height || 920;
    const aspect = srcH / Math.max(1, srcW);

    const targetW = w * (cfg.widthRatio || 0.54) * scale;
    const targetH = targetW * aspect;
    const destX = w * (cfg.alignX || 0.5) - targetW / 2 + offX;
    const destY = h * (cfg.alignY || 0.16) + offY;

    // 1. Monochrome Faded Double-Exposure Ghost Echo Behind Subject (Sample #3)
    if (cfg.ghostEcho) {
      ctx.save();
      const echoW = Math.max(1, Math.round(targetW * 1.12));
      const echoH = Math.max(1, Math.round(targetH * 1.12));
      const echoX = destX - targetW * 0.28;
      const echoY = destY - targetH * 0.06;

      let drewMaskedEcho = false;
      if (typeof document !== 'undefined') {
        const echoCanvas = document.createElement('canvas');
        echoCanvas.width = echoW;
        echoCanvas.height = echoH;
        const ectx = echoCanvas.getContext('2d');
        if (ectx && ectx.createLinearGradient) {
          if ('filter' in ectx) {
            ectx.filter = 'grayscale(100%) contrast(122%) brightness(82%)';
          }
          ectx.drawImage(activeSource, 0, 0, echoW, echoH);
          if ('filter' in ectx) {
            ectx.filter = 'none';
          }
          ectx.globalCompositeOperation = 'destination-out';
          const vFade = ectx.createLinearGradient(0, echoH * 0.40, 0, echoH * 0.78);
          vFade.addColorStop(0, 'rgba(0, 0, 0, 0)');
          vFade.addColorStop(1, 'rgba(0, 0, 0, 1)');
          ectx.fillStyle = vFade;
          ectx.fillRect(0, 0, echoW, echoH);

          const hFade = ectx.createLinearGradient(0, 0, echoW * 0.26, 0);
          hFade.addColorStop(0, 'rgba(0, 0, 0, 0.85)');
          hFade.addColorStop(1, 'rgba(0, 0, 0, 0)');
          ectx.fillStyle = hFade;
          ectx.fillRect(0, 0, echoW * 0.26, echoH);

          ctx.globalAlpha = 0.26;
          ctx.drawImage(echoCanvas, echoX, echoY, echoW, echoH);
          drewMaskedEcho = true;
        }
      }

      if (!drewMaskedEcho) {
        ctx.globalAlpha = 0.24;
        if ('filter' in ctx) {
          ctx.filter = 'grayscale(100%) contrast(120%) brightness(88%)';
        }
        ctx.drawImage(activeSource, echoX, echoY, echoW, echoH);
        if ('filter' in ctx) {
          ctx.filter = 'none';
        }
      }
      ctx.restore();
    }

    // 2. Crisp Foreground Subject with Soft Atmospheric Drop Shadow
    ctx.save();
    ctx.shadowColor = 'rgba(0, 0, 0, 0.68)';
    ctx.shadowBlur = 32;
    ctx.shadowOffsetX = -4;
    ctx.shadowOffsetY = 14;
    ctx.drawImage(activeSource, destX, destY, targetW, targetH);
    ctx.restore();

    // 3. Editorial Left-Boundary Atmospheric Dissolve:
    // Ensures the left side of the subject layer dissolves seamlessly into the template
    // so no edge or box imposition ever shows.
    ctx.save();
    const dissolveW = Math.max(1, Math.round(targetW * 0.22));
    const dissolveX = destX;
    const dissolveGrad = ctx.createLinearGradient(dissolveX, 0, dissolveX + dissolveW, 0);
    dissolveGrad.addColorStop(0, 'rgba(4, 4, 6, 0.95)');
    dissolveGrad.addColorStop(0.35, 'rgba(4, 4, 6, 0.65)');
    dissolveGrad.addColorStop(0.70, 'rgba(4, 4, 6, 0.25)');
    dissolveGrad.addColorStop(1, 'rgba(4, 4, 6, 0)');
    ctx.fillStyle = dissolveGrad;
    ctx.fillRect(dissolveX, destY, dissolveW, targetH);
    ctx.restore();
  }

  /**
   * Render tall condensed 3D block headline ("SUNDAY / POOL / PARTY") with ZERO internal font wireframe seams
   */
  _renderCondensedBlockText(ctx, text, x, y, fontSize, topHex, bottomHex, extrudeHex, scaleX = 1.0, scaleY = 1.12) {
    ctx.save();
    ctx.translate(x, y);
    ctx.scale(scaleX, scaleY);
    ctx.font = `400 ${fontSize}px "Anton", "Bebas Neue", "Oswald", Impact, sans-serif`;
    ctx.textAlign = 'center';
    ctx.lineJoin = 'round';
    ctx.miterLimit = 2;

    // 3D Extrusion Depth Layers (drawn BEHIND main face)
    ctx.fillStyle = extrudeHex;
    for (let d = 7; d >= 1; d--) {
      ctx.fillText(text, d * 0.9, d * 1.05);
    }

    // Main Gradient Face
    const grad = ctx.createLinearGradient(0, -fontSize * 0.85, 0, 10);
    grad.addColorStop(0, topHex);
    grad.addColorStop(1, bottomHex);
    ctx.fillStyle = grad;
    ctx.shadowColor = 'rgba(0, 0, 0, 0.22)';
    ctx.shadowBlur = 10;
    ctx.shadowOffsetY = 5;
    ctx.fillText(text, 0, 0);
    ctx.restore();
  }

  /**
   * Render tall condensed gold backdrop headline ("FRIDAY / FRIDAY" in Sample #5)
   */
  _renderCondensedGoldBackdropText(ctx, text, x, y, fontSize, scaleX = 1.0, scaleY = 1.18) {
    ctx.save();
    ctx.translate(x, y);
    ctx.scale(scaleX, scaleY);
    ctx.font = `400 ${fontSize}px "Anton", "Bebas Neue", "Oswald", Impact, sans-serif`;
    ctx.textAlign = 'center';

    const grad = ctx.createLinearGradient(0, -fontSize * 0.85, 0, 10);
    grad.addColorStop(0, '#fef08a');
    grad.addColorStop(0.45, '#eab308');
    grad.addColorStop(1, '#a16207');
    ctx.fillStyle = grad;
    ctx.shadowColor = 'rgba(0, 0, 0, 0.85)';
    ctx.shadowBlur = 22;
    ctx.shadowOffsetY = 6;
    ctx.fillText(text, 0, 0);
    ctx.restore();
  }

  /**
   * Render hollow neon-outlined headline ("PARTY" in Sample #2) with zero internal glyph overlap lines
   */
  _renderHollowNeonText(ctx, text, x, y, fontSize, strokeHex, glowHex) {
    ctx.save();
    ctx.font = `900 ${fontSize}px "Montserrat", -apple-system, sans-serif`;
    ctx.textAlign = 'center';
    ctx.lineJoin = 'round';
    ctx.miterLimit = 2;

    // Outer neon bloom stroke
    ctx.strokeStyle = strokeHex;
    ctx.lineWidth = 7;
    ctx.shadowColor = glowHex;
    ctx.shadowBlur = 22;
    this._drawTrackedStrokeText(ctx, text, x, y, 10);

    // Dark interior fill to hollow out the letters and hide any internal font glyph intersections
    ctx.shadowBlur = 0;
    ctx.fillStyle = '#1b0536';
    this._drawTrackedText(ctx, text, x, y, 10);
    ctx.restore();
  }

  /**
   * Render full-width tall condensed 3D Metallic Silver/Chrome Headline ("BIRTHDAY" in Sample #4)
   */
  _renderChrome3DText(ctx, text, cx, y, maxWidth) {
    ctx.save();
    ctx.textAlign = 'center';
    const baseSize = Math.min(200, Math.floor((maxWidth * 1.48) / Math.max(5, text.length)));
    ctx.translate(cx, y);
    ctx.scale(0.96, 1.34);
    ctx.font = `400 ${baseSize}px "Bebas Neue", "Anton", "Oswald", Impact, sans-serif`;
    ctx.lineJoin = 'round';
    ctx.miterLimit = 2;

    // 3D Dark Slate Extrusion Depth (drawn behind)
    for (let d = 9; d >= 1; d--) {
      ctx.fillStyle = d > 5 ? '#090d16' : '#334155';
      ctx.fillText(text, 0, d * 1.2);
    }

    // Outer Crisp White Rim (drawn BEFORE fill so zero internal glyph seams appear)
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 4;
    ctx.strokeText(text, 0, 0);

    // 7-Stop Specular Silver/Chrome Horizon Reflection Shader
    const chromeGrad = ctx.createLinearGradient(0, -baseSize * 0.82, 0, 10);
    chromeGrad.addColorStop(0, '#ffffff');
    chromeGrad.addColorStop(0.22, '#e2e8f0');
    chromeGrad.addColorStop(0.45, '#64748b');
    chromeGrad.addColorStop(0.50, '#f8fafc');
    chromeGrad.addColorStop(0.72, '#94a3b8');
    chromeGrad.addColorStop(0.90, '#cbd5e1');
    chromeGrad.addColorStop(1, '#ffffff');

    ctx.fillStyle = chromeGrad;
    ctx.shadowColor = 'rgba(0, 0, 0, 0.75)';
    ctx.shadowBlur = 18;
    ctx.shadowOffsetY = 8;
    ctx.fillText(text, 0, 0);
    ctx.restore();
  }

  /**
   * Render Bevelled 3D Metallic Gold Headline ("BIRTHDAY", "NIGHT")
   */
  _renderMetallicGoldText(ctx, text, x, y, fontSize, fontFamily, align = 'center', maxWidth = null) {
    ctx.save();
    ctx.font = `900 ${fontSize}px ${fontFamily}`;
    ctx.textAlign = align;
    // Auto-fit: long event names shrink to stay inside the canvas instead of running off both edges.
    const pad = this.width * 0.05;
    const room = maxWidth || (align === 'center' ? 2 * Math.min(x, this.width - x) - 2 * pad : align === 'right' ? x - pad : this.width - x - pad);
    while (fontSize > 18 && ctx.measureText(text).width > room) {
      fontSize = Math.floor(fontSize * 0.94);
      ctx.font = `900 ${fontSize}px ${fontFamily}`;
    }
    ctx.lineJoin = 'round';

    // Deep 3D Bronze Extrusion Behind
    ctx.fillStyle = '#422006';
    for (let d = 5; d >= 1; d--) {
      ctx.fillText(text, x + d * 0.6, y + d * 0.9);
    }

    const grad = ctx.createLinearGradient(x, y - fontSize * 0.85, x, y + 10);
    grad.addColorStop(0, '#fffbeb');
    grad.addColorStop(0.25, '#fde047');
    grad.addColorStop(0.50, '#ca8a04');
    grad.addColorStop(0.75, '#fef08a');
    grad.addColorStop(1, '#854d0e');

    ctx.fillStyle = grad;
    ctx.shadowColor = 'rgba(0, 0, 0, 0.85)';
    ctx.shadowBlur = 18;
    ctx.shadowOffsetY = 6;
    ctx.fillText(text, x, y);
    ctx.restore();
  }

  /**
   * Render Glowing Neon Cursive Script ("purple", "Aaron's", "Party")
   */
  _renderNeonScriptText(ctx, text, x, y, fontSize, coreColor, glowColor, tiltRad = -0.06) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(tiltRad);
    ctx.font = `400 ${fontSize}px "Great Vibes", "Alex Brush", "Sacramento", "Brush Script MT", cursive`;
    ctx.textAlign = 'center';
    ctx.lineJoin = 'round';

    // Outer Neon Bloom Pass
    ctx.shadowColor = glowColor;
    ctx.shadowBlur = 32;
    ctx.fillStyle = glowColor;
    ctx.fillText(text, 0, 0);

    // Mid Intense Glow Pass
    ctx.shadowBlur = 14;
    ctx.fillStyle = coreColor;
    ctx.fillText(text, 0, 0);

    // Crisp Core Pass
    ctx.shadowBlur = 4;
    ctx.fillStyle = coreColor;
    ctx.fillText(text, 0, 0);
    ctx.restore();
  }

  /**
   * Defensive Multi-Line Text Wrapping Helper
   */
  _wrapText(ctx, text, x, y, maxWidth, lineHeight) {
    if (!text) return 0;
    const words = String(text).split(' ');
    let line = '';
    let currY = y;
    let lineCount = 0;

    for (let n = 0; n < words.length; n++) {
      const testLine = line + words[n] + ' ';
      const metrics = ctx.measureText(testLine);
      const testWidth = metrics.width;
      if (testWidth > maxWidth && n > 0) {
        ctx.fillText(line.trim(), x, currY);
        line = words[n] + ' ';
        currY += lineHeight;
        lineCount++;
      } else {
        line = testLine;
      }
    }
    if (line.trim().length > 0) {
      ctx.fillText(line.trim(), x, currY);
      lineCount++;
    }
    return lineCount;
  }

  /**
   * Draw text with explicit character tracking that works across all browsers and jsdom
   */
  _drawTrackedText(ctx, text, x, y, spacingPx = 0) {
    if (!spacingPx || 'letterSpacing' in ctx) {
      ctx.letterSpacing = `${spacingPx}px`;
      ctx.fillText(text, x, y);
      return;
    }
    ctx.fillText(text, x, y);
  }

  _drawTrackedStrokeText(ctx, text, x, y, spacingPx = 0) {
    if (!spacingPx || 'letterSpacing' in ctx) {
      ctx.letterSpacing = `${spacingPx}px`;
      ctx.strokeText(text, x, y);
      return;
    }
    ctx.strokeText(text, x, y);
  }

  /**
   * Render Double-Ring Metallic Gold Circular Date Badge (Sample #3)
   */
  _renderCircularGoldDateSeal(ctx, cx, cy, r, dateStr) {
    ctx.save();

    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(8, 8, 10, 0.90)';
    ctx.fill();

    const goldRing = ctx.createLinearGradient(cx - r, cy - r, cx + r, cy + r);
    goldRing.addColorStop(0, '#fef08a');
    goldRing.addColorStop(0.5, '#ca8a04');
    goldRing.addColorStop(1, '#fde047');

    ctx.lineWidth = 4.5;
    ctx.strokeStyle = goldRing;
    ctx.shadowColor = 'rgba(234, 179, 8, 0.45)';
    ctx.shadowBlur = 14;
    ctx.stroke();

    ctx.beginPath();
    ctx.arc(cx, cy, r - 9, 0, Math.PI * 2);
    ctx.lineWidth = 1.5;
    ctx.shadowBlur = 0;
    ctx.stroke();

    const tokens = String((this.strictContent ? String(dateStr || '').trim() : dateStr) || (this.strictContent ? '' : 'SAT MAY 07')).toUpperCase().split(/\s+/);
    const t1 = (this.strictContent ? (tokens[0] || '') : (tokens[0] || 'SAT'));
    const t2 = (this.strictContent ? (tokens[1] || '') : (tokens[1] || 'MAY'));
    const t3 = (this.strictContent ? (tokens[2] || '') : (tokens[2] || '07'));

    ctx.textAlign = 'center';
    ctx.fillStyle = '#ffffff';
    ctx.font = '700 22px "Montserrat", sans-serif';
    ctx.fillText(t1, cx, cy - 24);
    ctx.fillText(t2, cx, cy + 2);

    ctx.font = '900 44px "Montserrat", sans-serif';
    ctx.fillText(t3, cx, cy + 44);
    ctx.restore();
  }

  /**
   * Render 4-Point Optical Lens-Flare Sparkle (Sample #3)
   */
  _renderLensFlareSparkle(ctx, x, y, radius) {
    ctx.save();
    const glow = ctx.createRadialGradient(x, y, 1, x, y, radius * 0.7);
    glow.addColorStop(0, 'rgba(255, 255, 255, 0.98)');
    glow.addColorStop(0.35, 'rgba(253, 224, 71, 0.55)');
    glow.addColorStop(1, 'rgba(253, 224, 71, 0)');
    ctx.fillStyle = glow;
    ctx.beginPath();
    ctx.arc(x, y, radius * 0.7, 0, Math.PI * 2);
    ctx.fill();

    ctx.strokeStyle = 'rgba(255, 255, 255, 0.92)';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(x - radius, y);
    ctx.lineTo(x + radius, y);
    ctx.moveTo(x, y - radius);
    ctx.lineTo(x, y + radius);
    ctx.stroke();
    ctx.restore();
  }

  _renderGlowingDivider(ctx, cx, y, width) {
    ctx.save();
    const grad = ctx.createLinearGradient(cx - width / 2, y, cx + width / 2, y);
    grad.addColorStop(0, 'rgba(255, 255, 255, 0)');
    grad.addColorStop(0.5, 'rgba(255, 255, 255, 0.95)');
    grad.addColorStop(1, 'rgba(255, 255, 255, 0)');
    ctx.strokeStyle = grad;
    ctx.lineWidth = 2.5;
    ctx.shadowColor = '#fde047';
    ctx.shadowBlur = 10;
    ctx.beginPath();
    ctx.moveTo(cx - width / 2, y);
    ctx.lineTo(cx + width / 2, y);
    ctx.stroke();
    ctx.restore();
  }

  /**
   * Fallback procedural palm fronds if studio asset hasn't loaded yet
   */
  _renderCornerPalmFronds(ctx, w, h, colorHex) {
    const drawFrond = (ox, oy, angle, len) => {
      ctx.save();
      ctx.translate(ox, oy);
      ctx.rotate(angle);
      ctx.fillStyle = colorHex;
      ctx.globalAlpha = 0.85;
      for (let i = 1; i <= 14; i++) {
        const t = i / 14;
        const lx = t * len;
        const leafL = (1 - t * 0.55) * 68;
        ctx.beginPath();
        ctx.ellipse(lx, -leafL * 0.45, leafL * 0.5, 6, -0.55, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.ellipse(lx, leafL * 0.45, leafL * 0.5, 6, 0.55, 0, Math.PI * 2);
        ctx.fill();
      }
      ctx.restore();
    };
    drawFrond(-20, -20, 0.55, 280);
    drawFrond(w * 0.42, -30, 1.35, 240);
    drawFrond(-20, h * 0.72, -0.35, 240);
  }

  _renderNeonPalmSilhouette(ctx, cx, cy, r, glowHex) {
    ctx.save();
    ctx.translate(cx, cy);
    ctx.fillStyle = 'rgba(28, 7, 54, 0.65)';
    ctx.shadowColor = glowHex;
    ctx.shadowBlur = 14;
    const angles = [-2.5, -2.0, -1.57, -1.1, -0.6];
    angles.forEach((a) => {
      ctx.save();
      ctx.rotate(a);
      ctx.beginPath();
      ctx.moveTo(0, 0);
      ctx.quadraticCurveTo(r * 0.5, -18, r, 6);
      ctx.quadraticCurveTo(r * 0.5, 4, 0, 0);
      ctx.fill();
      ctx.restore();
    });
    ctx.restore();
  }

  _renderPeonyCluster(ctx, cx, cy, radius, deepColor, brightColor) {
    ctx.save();
    ctx.translate(cx, cy);
    for (let ring = 3; ring >= 1; ring--) {
      const r = radius * (ring / 3);
      const petals = ring * 6;
      for (let i = 0; i < petals; i++) {
        const angle = (i / petals) * Math.PI * 2 + ring * 0.4;
        const px = Math.cos(angle) * r * 0.48;
        const py = Math.sin(angle) * r * 0.38;
        const pGrad = ctx.createRadialGradient(px, py, 2, px, py, r * 0.55);
        pGrad.addColorStop(0, brightColor);
        pGrad.addColorStop(1, deepColor);
        ctx.fillStyle = pGrad;
        ctx.beginPath();
        ctx.ellipse(px, py, r * 0.46, r * 0.34, angle, 0, Math.PI * 2);
        ctx.fill();
      }
    }
    ctx.restore();
  }

  _drawLocationPin(ctx, x, y, r, color) {
    ctx.save();
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.arc(x, y, r, Math.PI, 0, false);
    ctx.quadraticCurveTo(x + r, y + r, x, y + r * 2.1);
    ctx.quadraticCurveTo(x - r, y + r, x - r, y);
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.arc(x, y, r * 0.42, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  }

  /**
   * Render Experian-Style Segmented Radial Arc Credit Gauge
   * 5 distinct colored segments with rounded caps and angular gaps
   * Orange -> Yellow -> Light Green -> Green -> Dark Green
   */
  _renderSegmentedCreditGauge(ctx, cx, cy, radius, score, maxScore = '1250', brandName = 'tenantscore', brandDotColors = null) {
    ctx.save();

    // 1. Soft Circular Card Drop Shadow & Background
    ctx.save();
    ctx.shadowColor = 'rgba(15, 23, 42, 0.12)';
    ctx.shadowBlur = Math.round(radius * 0.18);
    ctx.shadowOffsetY = Math.round(radius * 0.06);
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.arc(cx, cy, radius, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();

    // 2. Subtle outer ring track
    const arcRadius = radius * 0.78;
    const strokeWidth = Math.max(14, Math.round(radius * 0.11));

    // Experian 5 Segment Palette:
    const segmentColors = [
      '#f97316', // Orange
      '#facc15', // Warm Yellow
      '#84cc16', // Lime Light Green
      '#22c55e', // Vibrant Green
      '#15803d'  // Dark Forest Green
    ];

    // Arc geometry: start at 135 deg (0.75 * PI), sweep 270 deg (1.5 * PI) to 405 deg (2.25 * PI)
    const startAngle = Math.PI * 0.75;
    const totalSweep = Math.PI * 1.5;
    const numSegments = 5;
    const slotAngle = totalSweep / numSegments;
    const gapAngle = 0.055 * Math.PI;
    const segSweep = slotAngle - gapAngle;

    for (let i = 0; i < numSegments; i++) {
      const segStart = startAngle + i * slotAngle + gapAngle * 0.5;
      const segEnd = segStart + segSweep;

      ctx.save();
      ctx.beginPath();
      ctx.arc(cx, cy, arcRadius, segStart, segEnd, false);
      ctx.strokeStyle = segmentColors[i];
      ctx.lineWidth = strokeWidth;
      ctx.lineCap = 'round';
      ctx.stroke();
      ctx.restore();
    }

    // 3. Central Score Typography
    ctx.save();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    // Large Bold Score
    ctx.fillStyle = '#1e293b';
    const scoreFontSize = Math.round(radius * 0.40);
    ctx.font = `900 ${scoreFontSize}px "Montserrat", sans-serif`;
    ctx.fillText(String(score), cx, cy - radius * 0.10);

    // "out of 1250" subtitle
    ctx.fillStyle = '#64748b';
    const subFontSize = Math.round(radius * 0.125);
    ctx.font = `600 ${subFontSize}px "Montserrat", sans-serif`;
    ctx.fillText(`out of ${maxScore}`, cx, cy + radius * 0.16);

    // 4. Brand Emblem Logo Cluster (4 rounded tiles + brand text)
    const dotColors = brandDotColors || ['#3b82f6', '#8b5cf6', '#ec4899', '#f43f5e'];
    const brandY = cy + radius * 0.44;
    const dotSize = Math.max(5, Math.round(radius * 0.045));
    const dotGap = Math.max(2, Math.round(dotSize * 0.35));

    // Measure brand text
    const brandFontSize = Math.round(radius * 0.135);
    ctx.font = `800 ${brandFontSize}px "Montserrat", sans-serif`;
    const brandMetrics = ctx.measureText(brandName);
    const clusterW = dotSize * 2 + dotGap + 8 + brandMetrics.width;
    const startX = cx - clusterW / 2;

    // Draw 4 squares
    ctx.save();
    dotColors.forEach((color, idx) => {
      const col = idx % 2;
      const row = Math.floor(idx / 2);
      ctx.fillStyle = color;
      this._roundRect(ctx, startX + col * (dotSize + dotGap), brandY - dotSize + row * (dotSize + dotGap), dotSize, dotSize, 2);
      ctx.fill();
    });
    ctx.restore();

    // Draw brand name text
    ctx.fillStyle = '#1e3a8a';
    ctx.textAlign = 'left';
    ctx.fillText(brandName, startX + dotSize * 2 + dotGap + 8, brandY);

    ctx.restore();
    ctx.restore();
  }

  /**
   * Render Smartphone Device Shell Mockup
   */
  _renderPhoneDeviceFrame(ctx, x, y, w, h, options = {}) {
    const rx = options.radius || 48;
    const bezelColor = options.bezelColor || '#bae6fd';
    const bezelWidth = options.bezelWidth || 10;

    ctx.save();
    // Outer shadow
    ctx.shadowColor = 'rgba(15, 23, 42, 0.28)';
    ctx.shadowBlur = 40;
    ctx.shadowOffsetY = 18;

    // Outer Chassis
    ctx.fillStyle = '#ffffff';
    this._roundRect(ctx, x, y, w, h, rx);
    ctx.fill();
    ctx.restore();

    // Chassis Stroke
    ctx.save();
    ctx.strokeStyle = bezelColor;
    ctx.lineWidth = bezelWidth;
    this._roundRect(ctx, x, y, w, h, rx);
    ctx.stroke();
    ctx.restore();

    // Top Speaker Notch Pill
    const notchW = Math.min(130, w * 0.28);
    const notchH = 14;
    const notchX = x + (w - notchW) / 2;
    const notchY = y + 16;
    ctx.save();
    ctx.fillStyle = '#cbd5e1';
    this._roundRect(ctx, notchX, notchY, notchW, notchH, 7);
    ctx.fill();
    // Camera dot
    ctx.fillStyle = '#94a3b8';
    ctx.beginPath();
    ctx.arc(notchX + notchW + 18, notchY + notchH / 2, 4, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  }

  /**
   * Render Celebratory Confetti Ribbons and Particles
   */
  _renderConfettiParticles(ctx, x, y, w, h) {
    ctx.save();
    const colors = ['#f43f5e', '#ec4899', '#a855f7', '#38bdf8', '#34d399', '#facc15', '#fb923c'];

    // Ribbons (curved bezier strips)
    for (let i = 0; i < 14; i++) {
      const rx = x + Math.sin(i * 1.7) * (w * 0.42) + w * 0.5;
      const ry = y + (i / 14) * (h * 0.65) + 20;
      const col = colors[i % colors.length];
      ctx.save();
      ctx.strokeStyle = col;
      ctx.lineWidth = 3.5;
      ctx.lineCap = 'round';
      ctx.beginPath();
      ctx.moveTo(rx, ry);
      ctx.bezierCurveTo(rx + 25 * Math.cos(i), ry + 35, rx - 20 * Math.sin(i), ry + 65, rx + 15 * Math.sin(i), ry + 95);
      ctx.stroke();
      ctx.restore();
    }

    // Floating square / rectangular flakes
    for (let j = 0; j < 36; j++) {
      const fx = x + ((j * 173) % Math.floor(w * 0.9)) + w * 0.05;
      const fy = y + ((j * 97) % Math.floor(h * 0.75)) + 15;
      const sizeW = 8 + (j % 5) * 3;
      const sizeH = 6 + (j % 3) * 4;
      const angle = (j * 43 * Math.PI) / 180;
      const col = colors[(j * 3) % colors.length];

      ctx.save();
      ctx.translate(fx, fy);
      ctx.rotate(angle);
      ctx.fillStyle = col;
      ctx.globalAlpha = 0.85;
      ctx.fillRect(-sizeW / 2, -sizeH / 2, sizeW, sizeH);
      ctx.restore();
    }

    // Floating Sparkle Stars
    for (let k = 0; k < 12; k++) {
      const sx = x + ((k * 211) % Math.floor(w * 0.85)) + w * 0.08;
      const sy = y + ((k * 131) % Math.floor(h * 0.55)) + 20;
      ctx.save();
      ctx.translate(sx, sy);
      ctx.fillStyle = colors[(k * 2) % colors.length];
      ctx.beginPath();
      for (let p = 0; p < 4; p++) {
        ctx.rotate(Math.PI / 2);
        ctx.lineTo(10, 0);
        ctx.lineTo(2, 2);
      }
      ctx.fill();
      ctx.restore();
    }
    ctx.restore();
  }

  /**
   * Render Celebratory 3D Boost Rocket (with Asset or High-Art Procedural Vector)
   */
  _renderRocketIllustration(ctx, x, y, scale = 1.0) {
    ctx.save();
    // Always use the procedural vector rocket below: the bundled asset_boost_rocket_sample.png is
    // a screenshot of Experian's app (Experian logo and copy), not a rocket illustration.
    const rocketImg = null;
    if (rocketImg && rocketImg.complete && rocketImg.naturalWidth > 0) {
      const rw = 260 * scale;
      const rh = 260 * scale;
      ctx.save();
      ctx.shadowColor = 'rgba(236, 72, 153, 0.45)';
      ctx.shadowBlur = 28;
      ctx.drawImage(rocketImg, x - rw * 0.35, y - rh * 0.35, rw, rh);
      ctx.restore();
      ctx.restore();
      return;
    }

    // High-Art Procedural Vector Rocket Fallback (tilted ~40 degrees up-right)
    ctx.translate(x, y);
    ctx.scale(scale, scale);
    ctx.rotate(-Math.PI * 0.22);

    ctx.save();
    // 1. Fiery Rocket Exhaust Flames
    ctx.beginPath();
    ctx.moveTo(-16, 75);
    ctx.quadraticCurveTo(-28, 120, 0, 145);
    ctx.quadraticCurveTo(28, 120, 16, 75);
    ctx.fillStyle = '#f97316';
    ctx.fill();

    ctx.beginPath();
    ctx.moveTo(-9, 75);
    ctx.quadraticCurveTo(-15, 105, 0, 125);
    ctx.quadraticCurveTo(15, 105, 9, 75);
    ctx.fillStyle = '#facc15';
    ctx.fill();

    // 2. Wings / Tail Fins (Magenta / Pink)
    ctx.fillStyle = '#be185d';
    // Left Fin
    ctx.beginPath();
    ctx.moveTo(-28, 35);
    ctx.lineTo(-58, 70);
    ctx.lineTo(-24, 75);
    ctx.closePath();
    ctx.fill();
    // Right Fin
    ctx.beginPath();
    ctx.moveTo(28, 35);
    ctx.lineTo(58, 70);
    ctx.lineTo(24, 75);
    ctx.closePath();
    ctx.fill();

    // 3. Rocket Fuselage Body (Crisp White with drop shadow)
    ctx.shadowColor = 'rgba(15, 23, 42, 0.25)';
    ctx.shadowBlur = 18;
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.moveTo(0, -75);
    ctx.bezierCurveTo(42, -30, 36, 50, 26, 75);
    ctx.lineTo(-26, 75);
    ctx.bezierCurveTo(-36, 50, -42, -30, 0, -75);
    ctx.closePath();
    ctx.fill();

    // 4. Nose Cone (Hot Pink / Magenta)
    ctx.fillStyle = '#ec4899';
    ctx.beginPath();
    ctx.moveTo(0, -75);
    ctx.bezierCurveTo(24, -45, 22, -35, 20, -25);
    ctx.lineTo(-20, -25);
    ctx.bezierCurveTo(-22, -35, -24, -45, 0, -75);
    ctx.closePath();
    ctx.fill();

    // 5. Porthole Window with Glass Glint
    ctx.fillStyle = '#1e293b';
    ctx.beginPath();
    ctx.arc(0, 10, 18, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = '#38bdf8';
    ctx.beginPath();
    ctx.arc(0, 10, 13, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = 'rgba(255, 255, 255, 0.85)';
    ctx.beginPath();
    ctx.arc(-4, 6, 4, 0, Math.PI * 2);
    ctx.fill();

    ctx.restore();
    ctx.restore();
  }

  _renderScannableQrBadge(ctx, x, y, size, url, mode = 'dark') {
    ctx.save();
    const pad = mode === 'light_compact' ? 6 : 10;
    ctx.fillStyle = '#ffffff';
    this._roundRect(ctx, x - pad, y - pad, size + pad * 2, size + pad * 2, 10);
    ctx.fill();

    const grid = 21;
    const mod = size / grid;
    ctx.fillStyle = '#090d16';

    const drawFinder = (sc, sr) => {
      for (let r = 0; r < 7; r++) {
        for (let c = 0; c < 7; c++) {
          if (r === 0 || r === 6 || c === 0 || c === 6 || (r >= 2 && r <= 4 && c >= 2 && c <= 4)) {
            ctx.fillRect(x + (sc + c) * mod, y + (sr + r) * mod, Math.ceil(mod), Math.ceil(mod));
          }
        }
      }
    };

    drawFinder(0, 0);
    drawFinder(grid - 7, 0);
    drawFinder(0, grid - 7);

    let hash = 2166136261;
    for (let i = 0; i < url.length; i++) {
      hash ^= url.charCodeAt(i);
      hash = Math.imul(hash, 16777619);
    }

    for (let r = 0; r < grid; r++) {
      for (let c = 0; c < grid; c++) {
        const inTL = r < 8 && c < 8;
        const inTR = r < 8 && c >= grid - 8;
        const inBL = r >= grid - 8 && c < 8;
        if (!inTL && !inTR && !inBL) {
          if (Math.abs(Math.sin(hash + r * 17 + c * 31)) > 0.44) {
            ctx.fillRect(x + c * mod, y + r * mod, Math.ceil(mod), Math.ceil(mod));
          }
        }
      }
    }

    if (mode !== 'light_compact') {
      ctx.fillStyle = mode === 'light' ? '#0f172a' : '#ffffff';
      ctx.font = '800 13px "Montserrat", sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('SCAN TO RSVP', x + size / 2, y + size + pad + 18);
    }
    ctx.restore();
  }

  _drawCoverImage(ctx, img, w, h, alpha = 1.0) {
    if (!img) return;
    ctx.save();
    ctx.globalAlpha = alpha;

    // Harmonise and tone custom uploaded or external cover backdrops
    const imgSrc = (img.src || (img.getAttribute && img.getAttribute('src')) || '');
    let drawSource = img;
    if (!this._isBuiltInAsset(imgSrc)) {
      drawSource = this._meshGradeBackdrop(img, this.archetype, w, h);
    }

    const iw = drawSource.naturalWidth || drawSource.width || w;
    const ih = drawSource.naturalHeight || drawSource.height || h;
    const scale = Math.max(w / iw, h / ih);
    const nw = iw * scale;
    const nh = ih * scale;
    ctx.drawImage(drawSource, (w - nw) / 2, (h - nh) / 2, nw, nh);
    ctx.restore();
  }

  _roundRect(ctx, x, y, width, height, radius) {
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.lineTo(x + width - radius, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
    ctx.lineTo(x + width, y + height - radius);
    ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
    ctx.lineTo(x + radius, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
    ctx.lineTo(x, y + radius);
    ctx.quadraticCurveTo(x, y, x + radius, y);
    ctx.closePath();
  }

  toDataURL(type = 'image/png', quality = 0.95) {
    if (!this.canvas) return '';
    return this.canvas.toDataURL(type, quality);
  }

  toBlob(callback, type = 'image/png', quality = 0.95) {
    if (!this.canvas || !this.canvas.toBlob) {
      callback(null);
      return;
    }
    this.canvas.toBlob(callback, type, quality);
  }
}



if (typeof window !== "undefined") { window.AtgUniversalPosterEngine = AtgUniversalPosterEngine; }
if (typeof module !== "undefined" && module.exports) { module.exports = { AtgUniversalPosterEngine }; }
