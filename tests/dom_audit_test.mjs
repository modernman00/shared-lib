import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const auditSource = fs.readFileSync(path.resolve('scripts/dom_audit.js'), 'utf8');

function createMockElement({
    tagName = 'div',
    id = '',
    className = '',
    innerText = '',
    attrs = {},
    style = {},
    rect = { width: 120, height: 44 },
    children = [],
    complete = true,
    naturalWidth = 100
} = {}) {
    return {
        tagName: tagName.toUpperCase(),
        id,
        className,
        innerText,
        value: '',
        children,
        complete,
        naturalWidth,
        parentElement: null,
        getAttribute(name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : null;
        },
        hasAttribute(name) {
            return Object.prototype.hasOwnProperty.call(attrs, name);
        },
        querySelector(selector) {
            if (selector.includes('csrf_token') && attrs.hasCsrf) return {};
            if (selector.includes('img[alt]') && attrs.hasImgAlt) return {};
            return null;
        },
        getBoundingClientRect() {
            return rect;
        },
        _style: {
            display: 'block',
            visibility: 'visible',
            opacity: '1',
            color: 'rgb(15, 23, 42)',
            backgroundColor: 'rgb(255, 255, 255)',
            fontSize: '16px',
            lineHeight: '20px',
            textTransform: 'none',
            letterSpacing: '0px',
            ...style
        }
    };
}

function runAuditOnElements(elementsBySelector, winWidth = 1440) {
    const body = createMockElement({ tagName: 'body' });
    const context = {
        window: {
            innerWidth: winWidth,
            innerHeight: 900,
            location: { href: 'http://localhost/test' },
            getComputedStyle(el) {
                return el._style;
            }
        },
        document: {
            body,
            documentElement: { scrollWidth: winWidth },
            querySelectorAll(selector) {
                return elementsBySelector[selector] || [];
            }
        }
    };
    vm.createContext(context);
    vm.runInContext(auditSource, context);
    return context.window.runSovereignDomAudit();
}

// 1. Clean page test
const cleanReport = runAuditOnElements({
    'form': [createMockElement({ tagName: 'form', attrs: { method: 'post', hasCsrf: true } })],
    'button, input[type="submit"], input[type="button"], [role="button"], .btn': [
        createMockElement({ tagName: 'button', innerText: 'Save Changes', rect: { width: 120, height: 44 } })
    ],
    'button, a[href], [role="button"], .btn': [
        createMockElement({ tagName: 'button', innerText: 'Save Changes', rect: { width: 120, height: 44 } })
    ],
    'h1, h2, h3, h4, h5, h6, p, span, a, button, label, th, td, li': [
        createMockElement({ tagName: 'p', innerText: 'Hello world' })
    ],
    'img': [
        createMockElement({ tagName: 'img', attrs: { src: '/logo.png', alt: 'Logo' } })
    ],
    'h1': [createMockElement({ tagName: 'h1', innerText: 'Main', style: { fontSize: '32px' } })],
    'h2': [createMockElement({ tagName: 'h2', innerText: 'Sub', style: { fontSize: '20px' } })],
    'span, p, div, label': []
});

assert.equal(cleanReport.passed, true, 'Clean page should pass');
assert.equal(cleanReport.violationCount, 0);
assert.equal(cleanReport.warningCount, 0);

// 2. AI-slop & accessibility regression test
const eyebrows = Array.from({ length: 5 }, (_, idx) =>
    createMockElement({
        tagName: 'span',
        innerText: `FEATURE ${idx + 1}`,
        style: { textTransform: 'uppercase', letterSpacing: '1.5px', fontSize: '11px' }
    })
);

const slopReport = runAuditOnElements({
    'form': [],
    'button, input[type="submit"], input[type="button"], [role="button"], .btn': [
        createMockElement({ tagName: 'button', innerText: 'Wrapped Action Label', rect: { width: 90, height: 64 }, style: { lineHeight: '20px', fontSize: '14px' } })
    ],
    'button, a[href], [role="button"], .btn': [
        createMockElement({ tagName: 'button', innerText: '', rect: { width: 44, height: 44 } })
    ],
    'h1, h2, h3, h4, h5, h6, p, span, a, button, label, th, td, li': [],
    'img': [
        createMockElement({ tagName: 'img', attrs: { src: '/hero.png' } })
    ],
    'h1': [createMockElement({ tagName: 'h1', innerText: 'Title', style: { fontSize: '18px' } })],
    'h2': [createMockElement({ tagName: 'h2', innerText: 'Section', style: { fontSize: '26px' } })],
    'span, p, div, label': eyebrows
});

assert.equal(slopReport.passed, false, 'Unlabelled icon button should fail');
assert.ok(slopReport.violations.some((v) => v.rule === 'UNLABELLED_ICON_BUTTON'));
assert.ok(slopReport.warnings.some((w) => w.rule === 'BUTTON_TEXT_WRAPPING'));
assert.ok(slopReport.warnings.some((w) => w.rule === 'MISSING_IMG_ALT'));
assert.ok(slopReport.warnings.some((w) => w.rule === 'HEADING_HIERARCHY_INVERSION'));
assert.ok(slopReport.warnings.some((w) => w.rule === 'AI_SLOP_EYEBROW_OVERUSE'));
assert.equal(slopReport.metrics.eyebrowCount, 5);

console.log('✅ All dom_audit.js tests passed (clean + slop regression).');
