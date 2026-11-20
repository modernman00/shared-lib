/**
 * Sovereign DOM & Visual Ergonomics Auditor (v5.0)
 * Stage 8 ("Screen Editor" / Browser QA) of the 10-Stage Autonomous Software Engine.
 *
 * Automatically audits any rendered page against Global Design Standards v2.1:
 *  1. Zero Horizontal Overflow (document.documentElement.scrollWidth <= innerWidth)
 *  2. Safe Touch Targets (>= 44x44px or expanded hit area via ::after) on interactive controls
 *  3. Button Label Integrity (no awkward multi-line wrapping on buttons/pills)
 *  4. Inviolable Colour & Contrast Guards (Zero Black-on-Red, Zero White-on-White Ghost Text)
 *  5. Universal CSRF Token Presence in POST forms
 *  6. Broken Image / Asset Detection
 *
 * Usage inside Browser Subagent / DevTools:
 *   const report = window.runSovereignDomAudit();
 */
(function (global) {
    'use strict';

    function parseRgb(colorStr) {
        if (!colorStr) return null;
        const m = colorStr.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([\d.]+))?\s*\)/i);
        if (m) {
            return {
                r: parseInt(m[1], 10),
                g: parseInt(m[2], 10),
                b: parseInt(m[3], 10),
                a: m[4] !== undefined ? parseFloat(m[4]) : 1
            };
        }
        const sp = colorStr.match(/rgba?\(\s*(\d+)\s+(\d+)\s+(\d+)(?:\s*\/\s*([\d.]+%?))?\s*\)/i);
        if (sp) {
            let a = 1;
            if (sp[4] !== undefined) {
                a = sp[4].endsWith('%') ? parseFloat(sp[4]) / 100 : parseFloat(sp[4]);
            }
            return {
                r: parseInt(sp[1], 10),
                g: parseInt(sp[2], 10),
                b: parseInt(sp[3], 10),
                a: a
            };
        }
        const srgb = colorStr.match(/color\(\s*srgb\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)(?:\s*\/\s*([\d.]+%?))?\s*\)/i);
        if (srgb) {
            const clamp = function (v) { return Math.max(0, Math.min(255, Math.round(parseFloat(v) * 255))); };
            let a = 1;
            if (srgb[4] !== undefined) {
                a = srgb[4].endsWith('%') ? parseFloat(srgb[4]) / 100 : parseFloat(srgb[4]);
            }
            return {
                r: clamp(srgb[1]),
                g: clamp(srgb[2]),
                b: clamp(srgb[3]),
                a: a
            };
        }
        return null;
    }

    function relativeLuminance(rgb) {
        const srgb = [rgb.r / 255, rgb.g / 255, rgb.b / 255].map(function (c) {
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * srgb[0] + 0.7152 * srgb[1] + 0.0722 * srgb[2];
    }

    function contrastRatio(lum1, lum2) {
        const brightest = Math.max(lum1, lum2);
        const darkest = Math.min(lum1, lum2);
        return (brightest + 0.05) / (darkest + 0.05);
    }

    function isRedSurface(rgb) {
        if (!rgb || rgb.a < 0.2) return false;
        return rgb.r >= 140 && rgb.g <= 65 && rgb.b <= 65 && (rgb.r - Math.max(rgb.g, rgb.b)) >= 80;
    }

    function isDarkText(rgb) {
        if (!rgb) return false;
        return relativeLuminance(rgb) < 0.12;
    }

    function parseGradientFirstColor(bgImage) {
        if (!bgImage || bgImage === 'none') return null;
        const m = bgImage.match(/rgba?\([^)]+\)|color\(\s*srgb[^)]+\)/i);
        if (!m) return null;
        return parseRgb(m[0]);
    }

    function getEffectiveBackground(el) {
        let cur = el;
        while (cur && cur !== document.documentElement) {
            const curStyle = window.getComputedStyle(cur);
            const parsed = parseRgb(curStyle.backgroundColor);
            if (parsed && parsed.a >= 0.5) {
                return parsed;
            }
            if (curStyle.backgroundImage && curStyle.backgroundImage !== 'none' && curStyle.backgroundClip !== 'text' && curStyle.webkitBackgroundClip !== 'text') {
                const gradColor = parseGradientFirstColor(curStyle.backgroundImage);
                if (gradColor && gradColor.a >= 0.5) {
                    return gradColor;
                }
                return null;
            }
            cur = cur.parentElement;
        }
        const bodyBg = parseRgb(window.getComputedStyle(document.body).backgroundColor);
        return bodyBg && bodyBg.a >= 0.5 ? bodyBg : { r: 255, g: 255, b: 255, a: 1 };
    }

    function isVisible(el) {
        if (!el || !el.getBoundingClientRect) return false;
        const style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity) === 0) {
            return false;
        }
        const rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    }

    function getSelector(el) {
        if (!el) return 'unknown';
        const tag = el.tagName ? el.tagName.toLowerCase() : 'node';
        const id = el.id ? '#' + el.id : '';
        const cls = (typeof el.className === 'string' && el.className.trim())
            ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.')
            : '';
        return tag + id + cls;
    }

    function runSovereignDomAudit() {
        const violations = [];
        const warnings = [];

        // 1. Horizontal Overflow Check
        const docWidth = document.documentElement.scrollWidth;
        const winWidth = window.innerWidth;
        if (docWidth > winWidth + 2) {
            violations.push({
                rule: 'HORIZONTAL_OVERFLOW',
                message: 'Page has horizontal scroll overflow (' + docWidth + 'px > ' + winWidth + 'px)',
                selector: 'html/body'
            });
        }

        // 2. CSRF Check in POST Forms
        const forms = Array.from(document.querySelectorAll('form'));
        forms.forEach(function (form) {
            const method = (form.getAttribute('method') || 'post').toLowerCase();
            if (method === 'get') return;
            const hasToken = form.querySelector('input[name="_token"], input[name="token"], input[name="csrf_token"]');
            if (!hasToken) {
                violations.push({
                    rule: 'MISSING_CSRF_DOM',
                    message: 'POST form is missing a rendered hidden CSRF input',
                    selector: getSelector(form)
                });
            }
        });

        // 3. Touch Target, Button Label Integrity & Icon-Only Accessibility Check
        const minTouchPx = winWidth <= 640 ? 44 : 36;
        const controls = Array.from(document.querySelectorAll('button, input[type="submit"], input[type="button"], [role="button"], .btn'));
        controls.forEach(function (ctrl) {
            if (!isVisible(ctrl)) return;
            const rect = ctrl.getBoundingClientRect();
            const ctrlStyle = window.getComputedStyle(ctrl);
            const ctrlText = (ctrl.innerText || ctrl.value || '').trim();

            if (rect.width < minTouchPx || rect.height < minTouchPx) {
                warnings.push({
                    rule: 'TOUCH_TARGET_SMALL',
                    message: 'Interactive control smaller than safe touch threshold (' + Math.round(rect.width) + 'x' + Math.round(rect.height) + 'px < ' + minTouchPx + 'px)',
                    selector: getSelector(ctrl),
                    text: ctrlText.slice(0, 30)
                });
            }

            // Button Label Integrity (prevent multi-line wrapping on action buttons/pills)
            if (ctrlText.length > 0 && ctrlText.length <= 60) {
                const fontSize = parseFloat(ctrlStyle.fontSize) || 14;
                const rawLh = parseFloat(ctrlStyle.lineHeight);
                const lineHeight = Number.isFinite(rawLh) ? rawLh : fontSize * 1.25;
                if (rect.height > lineHeight * 2.2 && rect.height > 52) {
                    warnings.push({
                        rule: 'BUTTON_TEXT_WRAPPING',
                        message: 'Button label appears to wrap across multiple lines (' + Math.round(rect.height) + 'px tall)',
                        selector: getSelector(ctrl),
                        text: ctrlText.slice(0, 40)
                    });
                }
            }
        });

        // 3b. Unlabelled Icon-Only Interactive Controls (Hallmark Floor Gate)
        const iconControls = Array.from(document.querySelectorAll('button, a[href], [role="button"], .btn'));
        iconControls.slice(0, 300).forEach(function (ctrl) {
            if (!isVisible(ctrl)) return;
            const ctrlText = (ctrl.innerText || ctrl.value || '').trim();
            if (ctrlText.length > 0) return;
            const hasAria = Boolean(
                (ctrl.getAttribute('aria-label') || '').trim() ||
                (ctrl.getAttribute('aria-labelledby') || '').trim() ||
                (ctrl.getAttribute('title') || '').trim()
            );
            const imgWithAlt = ctrl.querySelector('img[alt]:not([alt=""])');
            if (!hasAria && !imgWithAlt) {
                violations.push({
                    rule: 'UNLABELLED_ICON_BUTTON',
                    message: 'Icon-only interactive control is missing aria-label, aria-labelledby, or title',
                    selector: getSelector(ctrl)
                });
            }
        });

        // 4. Inviolable Colour Guards (Zero Black-on-Red & Ghost Text)
        const textNodes = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6, p, span, a, button, label, th, td, li'));
        textNodes.slice(0, 400).forEach(function (el) {
            if (!isVisible(el)) return;
            const text = (el.innerText || '').trim();
            if (!text) return;

            const style = window.getComputedStyle(el);
            if (style.backgroundClip === 'text' || style.webkitBackgroundClip === 'text') return;
            const fg = parseRgb(style.color);
            const bg = getEffectiveBackground(el);
            if (!fg || fg.a < 0.1 || !bg) return;

            if (isRedSurface(bg) && isDarkText(fg)) {
                violations.push({
                    rule: 'BLACK_ON_RED_SURFACE',
                    message: 'Dark font detected over red surface (Inviolable Red Surface Rule violation)',
                    selector: getSelector(el),
                    text: text.slice(0, 40)
                });
            }

            const ratio = contrastRatio(relativeLuminance(fg), relativeLuminance(bg));
            if (ratio < 2.2) {
                violations.push({
                    rule: 'GHOST_TYPOGRAPHY_CONTRAST',
                    message: 'Severe low contrast (' + ratio.toFixed(2) + ':1) detected (Ghost Typography violation)',
                    selector: getSelector(el),
                    text: text.slice(0, 40)
                });
            }
        });

        // 5. Broken Images & Missing Alt Check
        const images = Array.from(document.querySelectorAll('img'));
        images.forEach(function (img) {
            if (!isVisible(img)) return;
            if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) {
                violations.push({
                    rule: 'BROKEN_IMAGE',
                    message: 'Image failed to load: ' + img.getAttribute('src'),
                    selector: getSelector(img)
                });
            }
            if (!img.hasAttribute('alt')) {
                warnings.push({
                    rule: 'MISSING_IMG_ALT',
                    message: 'Visible <img> is missing an alt attribute',
                    selector: getSelector(img)
                });
            }
        });

        // 6. Anti-AI-Slop Typography & Eyebrow Gates (Hallmark-inspired)
        const firstH1 = Array.from(document.querySelectorAll('h1')).find(isVisible);
        const firstH2 = Array.from(document.querySelectorAll('h2')).find(isVisible);
        if (firstH1 && firstH2) {
            const h1Size = parseFloat(window.getComputedStyle(firstH1).fontSize) || 0;
            const h2Size = parseFloat(window.getComputedStyle(firstH2).fontSize) || 0;
            if (h1Size > 0 && h2Size > 0 && h1Size < h2Size) {
                warnings.push({
                    rule: 'HEADING_HIERARCHY_INVERSION',
                    message: 'Primary <h1> (' + h1Size + 'px) is smaller than <h2> (' + h2Size + 'px)',
                    selector: getSelector(firstH1)
                });
            }
        }

        let eyebrowCount = 0;
        const eyebrowCandidates = Array.from(document.querySelectorAll('span, p, div, label'));
        eyebrowCandidates.slice(0, 300).forEach(function (el) {
            if (!isVisible(el) || el.children.length > 0) return;
            const text = (el.innerText || '').trim();
            if (!text || text.length > 45) return;
            const style = window.getComputedStyle(el);
            const fontSize = parseFloat(style.fontSize) || 14;
            const tracking = parseFloat(style.letterSpacing) || 0;
            if (style.textTransform === 'uppercase' && tracking >= 1 && fontSize <= 13) {
                eyebrowCount += 1;
            }
        });
        if (eyebrowCount > 4) {
            warnings.push({
                rule: 'AI_SLOP_EYEBROW_OVERUSE',
                message: 'Detected ' + eyebrowCount + ' small tracked-uppercase eyebrow labels (classic AI template tell; keep <= 4)',
                selector: 'document'
            });
        }

        return {
            passed: violations.length === 0,
            viewport: { width: winWidth, height: window.innerHeight },
            url: window.location.href,
            violationCount: violations.length,
            warningCount: warnings.length,
            violations: violations,
            warnings: warnings.slice(0, 20),
            metrics: {
                formsChecked: forms.length,
                controlsChecked: controls.length,
                imagesChecked: images.length,
                eyebrowCount: eyebrowCount
            }
        };
    }

    global.runSovereignDomAudit = runSovereignDomAudit;
})(typeof window !== 'undefined' ? window : this);
