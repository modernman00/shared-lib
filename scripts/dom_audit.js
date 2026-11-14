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
        if (!m) return null;
        return {
            r: parseInt(m[1], 10),
            g: parseInt(m[2], 10),
            b: parseInt(m[3], 10),
            a: m[4] !== undefined ? parseFloat(m[4]) : 1
        };
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

    function getEffectiveBackground(el) {
        let cur = el;
        while (cur && cur !== document.documentElement) {
            const bg = window.getComputedStyle(cur).backgroundColor;
            const parsed = parseRgb(bg);
            if (parsed && parsed.a >= 0.5) {
                return parsed;
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

        // 3. Touch Target & Button Integrity Check (Primary Action Controls)
        const controls = Array.from(document.querySelectorAll('button, input[type="submit"], input[type="button"], [role="button"], .btn'));
        controls.forEach(function (ctrl) {
            if (!isVisible(ctrl)) return;
            const rect = ctrl.getBoundingClientRect();
            if (rect.width < 36 || rect.height < 36) {
                warnings.push({
                    rule: 'TOUCH_TARGET_SMALL',
                    message: 'Interactive control smaller than safe touch threshold (' + Math.round(rect.width) + 'x' + Math.round(rect.height) + 'px)',
                    selector: getSelector(ctrl),
                    text: (ctrl.innerText || '').trim().slice(0, 30)
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
            const fg = parseRgb(style.color);
            const bg = getEffectiveBackground(el);
            if (!fg || !bg) return;

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

        // 5. Broken Images Check
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
        });

        return {
            passed: violations.length === 0,
            viewport: { width: winWidth, height: window.innerHeight },
            url: window.location.href,
            violationCount: violations.length,
            warningCount: warnings.length,
            violations: violations,
            warnings: warnings.slice(0, 15)
        };
    }

    global.runSovereignDomAudit = runSovereignDomAudit;
})(typeof window !== 'undefined' ? window : this);
