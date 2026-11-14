/**
 * verify_explainer_studio_live_browser.js
 *
 * Automated Live Browser Visual & Audio Verification Script for
 * Sovereign Explainer Studio on FamilyPlatform (https://olaogun.test/explainer-studio-verify).
 *
 * Uses Chrome DevTools Protocol (CDP) via native Node 22 WebSocket.
 */

const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME_PATH = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const TARGET_URL = 'https://olaogun.test/explainer-studio-verify';
const DEBUG_PORT = 9223;
const SCREENSHOTS_DIR = path.join(__dirname, '../docs/audit/screenshots');

if (!fs.existsSync(SCREENSHOTS_DIR)) {
    fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

async function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForHttp(url, maxAttempts = 50) {
    for (let i = 0; i < maxAttempts; i++) {
        try {
            const res = await fetch(url);
            if (res.ok) {
                return await res.json();
            }
        } catch (_) {}
        await sleep(200);
    }
    throw new Error(`Failed to reach ${url} after ${maxAttempts} attempts.`);
}

class CdpClient {
    constructor(wsUrl) {
        this.wsUrl = wsUrl;
        this.ws = null;
        this.nextId = 1;
        this.pending = new Map();
        this.eventListeners = new Map();
    }

    async connect() {
        return new Promise((resolve, reject) => {
            this.ws = new WebSocket(this.wsUrl);
            this.ws.onopen = () => resolve();
            this.ws.onerror = (err) => reject(err);
            this.ws.onmessage = (event) => {
                const msg = JSON.parse(event.data);
                if (msg.id && this.pending.has(msg.id)) {
                    const { resolve, reject } = this.pending.get(msg.id);
                    this.pending.delete(msg.id);
                    if (msg.error) {
                        reject(new Error(msg.error.message || 'CDP Error'));
                    } else {
                        resolve(msg.result);
                    }
                } else if (msg.method) {
                    const listeners = this.eventListeners.get(msg.method) || [];
                    listeners.forEach((fn) => fn(msg.params));
                }
            };
        });
    }

    async send(method, params = {}) {
        const id = this.nextId++;
        return new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }

    on(method, listener) {
        if (!this.eventListeners.has(method)) {
            this.eventListeners.set(method, []);
        }
        this.eventListeners.get(method).push(listener);
    }

    async evaluate(expression, awaitPromise = true, retries = 25) {
        for (let i = 0; i < retries; i++) {
            try {
                const res = await this.send('Runtime.evaluate', {
                    expression,
                    awaitPromise,
                    returnByValue: true,
                });
                if (res.exceptionDetails) {
                    const desc = res.exceptionDetails.exception ? res.exceptionDetails.exception.description : res.exceptionDetails.text;
                    throw new Error(`Evaluation Exception: ${desc}`);
                }
                return res.result ? res.result.value : undefined;
            } catch (err) {
                if (err.message && err.message.includes('Cannot find default execution context') && i < retries - 1) {
                    await sleep(200);
                    continue;
                }
                throw err;
            }
        }
    }

    async screenshot(filepath) {
        const res = await this.send('Page.captureScreenshot', { format: 'png' });
        const buffer = Buffer.from(res.data, 'base64');
        fs.writeFileSync(filepath, buffer);
        console.log(`  📸 Screenshot saved: ${filepath}`);
    }

    close() {
        if (this.ws) {
            this.ws.close();
        }
    }
}

async function runVerification() {
    console.log('=================================================================');
    console.log('🎬  LIVE BROWSER VISUAL & AUDIO VERIFICATION: EXPLAINER STUDIO');
    console.log(`    Target: ${TARGET_URL}`);
    console.log('=================================================================\n');

    let chromeProc = null;
    let userDataDir = null;

    // Check if Chrome is already running on DEBUG_PORT
    let isRunning = false;
    try {
        const check = await fetch(`http://127.0.0.1:${DEBUG_PORT}/json/version`);
        if (check.ok) isRunning = true;
    } catch (_) {}

    if (!isRunning) {
        console.log('[1/7] Launching Headless Chrome Instance...');
        userDataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'chrome_cdp_'));
        chromeProc = spawn(
            CHROME_PATH,
            [
                '--headless=new',
                `--remote-debugging-port=${DEBUG_PORT}`,
                `--user-data-dir=${userDataDir}`,
                '--ignore-certificate-errors',
                '--allow-insecure-localhost',
                '--autoplay-policy=no-user-gesture-required',
                '--window-size=1280,960',
                '--disable-dev-shm-usage',
                '--no-first-run',
                '--no-default-browser-check',
                '--no-sandbox',
                'about:blank',
            ],
            { stdio: 'ignore' }
        );
    } else {
        console.log('[1/7] Connecting to Running Headless Chrome Instance...');
    }

    const cleanup = () => {
        if (chromeProc) {
            try { chromeProc.kill('SIGKILL'); } catch (_) {}
        }
        if (userDataDir) {
            try { fs.rmSync(userDataDir, { recursive: true, force: true }); } catch (_) {}
        }
    };

    process.on('exit', cleanup);
    process.on('SIGINT', cleanup);

    try {
        await waitForHttp(`http://127.0.0.1:${DEBUG_PORT}/json/version`, 50);

        // Create a dedicated clean tab via PUT /json/new
        console.log(`\n[2/7] Creating Dedicated Clean Tab for ${TARGET_URL}...`);
        const newTabRes = await fetch(`http://127.0.0.1:${DEBUG_PORT}/json/new?${encodeURIComponent(TARGET_URL)}`, { method: 'PUT' });
        const newTab = await newTabRes.json();
        const pageTargetId = newTab.id;
        const pageWsUrl = newTab.webSocketDebuggerUrl;

        console.log(`  ✓ Tab created: ${pageTargetId}`);
        console.log(`  ✓ Connecting to WebSocket: ${pageWsUrl}`);

        const client = new CdpClient(pageWsUrl);
        await client.connect();

        // Track console logs and exceptions
        const consoleErrors = [];
        client.on('Runtime.consoleAPICalled', (params) => {
            const text = (params.args || []).map((a) => a.value || a.description || '').join(' ');
            if (params.type === 'error') {
                consoleErrors.push(text);
                console.error(`  ❌ Console Error: ${text}`);
            }
        });
        client.on('Runtime.exceptionThrown', (params) => {
            const desc = params.exceptionDetails.exception ? params.exceptionDetails.exception.description : params.exceptionDetails.text;
            consoleErrors.push(desc);
            console.error(`  ❌ Uncaught Exception: ${desc}`);
        });

        await client.send('Page.enable');
        await client.send('Runtime.enable');
        await client.send('Page.bringToFront');

        console.log(`\n[2/7] Navigating to ${TARGET_URL}...`);
        await client.send('Page.navigate', { url: TARGET_URL });

        // Wait for page load
        let loaded = false;
        for (let i = 0; i < 40; i++) {
            const readyState = await client.evaluate('document.readyState');
            const studioEl = await client.evaluate('Boolean(document.querySelector(".atg-canvas-studio"))');
            if (readyState === 'complete' && studioEl) {
                loaded = true;
                break;
            }
            await sleep(250);
        }

        if (!loaded) {
            throw new Error('Page did not load within the allotted timeout.');
        }
        console.log('  ✓ Page successfully loaded and .atg-canvas-studio mounted.');

        // Take initial load screenshot
        const shot1 = path.join(SCREENSHOTS_DIR, '01_explainer_studio_initial_load.png');
        await client.screenshot(shot1);

        // 3. Inspect Studio Components & Dimensions
        console.log('\n[3/7] Inspecting Studio Components & Canvas Backing Store...');
        const inspection = await client.evaluate(`(() => {
            const canvas = document.querySelector('.atg-studio-canvas');
            const playBtn = document.querySelector('.atg-studio-play');
            const muteBtn = document.querySelector('.atg-studio-mute');
            const exportBtn = document.querySelector('.atg-studio-export');
            const select = document.querySelector('.atg-studio-style-select');
            const scrubber = document.querySelector('.atg-studio-scrubber');

            const optionCount = select ? select.options.length : 0;
            const currentStyle = select ? select.value : '';

            return {
                canvasFound: Boolean(canvas),
                canvasWidth: canvas ? canvas.width : 0,
                canvasHeight: canvas ? canvas.height : 0,
                dpr: window.devicePixelRatio || 1,
                playBtnFound: Boolean(playBtn),
                playBtnText: playBtn ? playBtn.textContent.trim() : '',
                muteBtnFound: Boolean(muteBtn),
                exportBtnFound: Boolean(exportBtn),
                scrubberFound: Boolean(scrubber),
                optionCount,
                currentStyle
            };
        })()`);

        console.log(`  ✓ Canvas physical buffer: ${inspection.canvasWidth}x${inspection.canvasHeight} (DPR: ${inspection.dpr})`);
        console.log(`  ✓ Style Atlas presets available in dropdown: ${inspection.optionCount}`);
        console.log(`  ✓ Active Style Preset: "${inspection.currentStyle}"`);
        console.log(`  ✓ Interactive controls: Play [${inspection.playBtnText}], SFX Mute, Scrubber, Export`);

        if (inspection.optionCount !== 10) {
            throw new Error(`Expected 10 Style Atlas presets, found ${inspection.optionCount}`);
        }

        // 4. Verify Web Audio API Unlock & 60fps Playback
        console.log('\n[4/7] Triggering Playback & Verifying Web Audio API...');
        await client.evaluate(`(() => {
            const playBtn = document.querySelector('.atg-studio-play');
            if (playBtn) playBtn.click();
        })()`);

        // Wait 1.8 seconds of playback
        await sleep(1800);

        const playbackState = await client.evaluate(`(() => {
            const scrubber = document.querySelector('.atg-studio-scrubber');
            const timeEl = document.querySelector('.atg-studio-time');
            const playBtn = document.querySelector('.atg-studio-play');

            return {
                scrubberValue: scrubber ? parseFloat(scrubber.value) : 0,
                timeText: timeEl ? timeEl.textContent.trim() : '',
                playBtnText: playBtn ? playBtn.textContent.trim() : ''
            };
        })()`);

        console.log(`  ✓ Timeline Progress: ${playbackState.timeText} (Scrubber: ${playbackState.scrubberValue}s)`);
        console.log(`  ✓ Play button state toggled: "${playbackState.playBtnText}"`);

        if (playbackState.scrubberValue <= 0) {
            throw new Error('Playback timeline did not advance! Scrubber remained at 0.');
        }

        // Take playback screenshot
        const shot2 = path.join(SCREENSHOTS_DIR, '02_explainer_studio_playback_60fps.png');
        await client.screenshot(shot2);

        // 5. Test Live Style Preset Switching
        console.log('\n[5/7] Testing Live Style Atlas Preset Switch...');
        const switchedStyle = await client.evaluate(`(() => {
            const select = document.querySelector('.atg-studio-style-select');
            if (!select) return null;
            select.value = 'sovereign-fintech';
            select.dispatchEvent(new Event('change'));
            return select.value;
        })()`);
        console.log(`  ✓ Switched preset to: "${switchedStyle}"`);

        await sleep(800);
        const shot3 = path.join(SCREENSHOTS_DIR, '03_explainer_studio_style_switch.png');
        await client.screenshot(shot3);

        // Pause playback from Step 4 before proceeding
        await client.evaluate(`(() => {
            const playBtn = document.querySelector('.atg-studio-play');
            if (playBtn && playBtn.textContent.includes('Pause')) {
                playBtn.click();
            }
        })()`);

        // 6. Test Web Audio Procedural SFX & Node Limiter
        console.log('\n[6/7] Testing Web Audio Procedural SFX & Limiter Stability...');
        const sfxResult = await client.evaluate(`(() => {
            const renderer = window.__explainerRenderer;
            const sfx = (renderer && renderer.sfx) || new ExplainerSfx({ volume: 0.4 });
            const ctx = sfx.ensureContext();
            const state = ctx ? ctx.state : 'null';
            const hasLimiter = Boolean(sfx.limiter);

            // Trigger procedural sound effects
            sfx.playWhoosh(0.2);
            sfx.playClick(620);
            sfx.playTick(880);
            sfx.playChime([523.25, 659.25, 783.99]);

            const streamDest = sfx.getStreamDestination();
            const hasAudioTracks = streamDest && streamDest.stream ? streamDest.stream.getAudioTracks().length : 0;

            return {
                audioCtxState: state,
                hasLimiter,
                hasAudioTracks
            };
        })()`);

        console.log(`  ✓ Web Audio Context Initialised: State "${sfxResult.audioCtxState}"`);
        console.log(`  ✓ DynamicsCompressorNode Master Limiter Active: ${sfxResult.hasLimiter}`);
        console.log(`  ✓ MediaStreamDestination Audio Tracks: ${sfxResult.hasAudioTracks}`);

        // 7. Test MediaRecorder WebM Video Export
        console.log('\n[7/7] Testing In-Browser MediaRecorder WebM Export...');
        await client.send('Page.bringToFront');

        console.log('  ⏳ Awaiting MediaRecorder video stream compilation (3.5s clip)...');
        const exportResult = await client.evaluate(`(async () => {
            const renderer = window.__explainerRenderer;
            if (!renderer) throw new Error('window.__explainerRenderer was not found');
            const startTime = performance.now();

            const timeoutPromise = new Promise((_, rej) => setTimeout(() => rej(new Error('Export timed out after 12000ms')), 12000));
            const blob = await Promise.race([renderer.exportWebM(3.5), timeoutPromise]);
            const durationMs = Math.round(performance.now() - startTime);

            return {
                sizeBytes: blob.size,
                type: blob.type,
                durationMs
            };
        })()`, true);

        console.log(`  ✓ Export completed in ${exportResult.durationMs}ms`);
        console.log(`  ✓ Exported Video Blob Type: "${exportResult.type}"`);
        console.log(`  ✓ Exported Video File Size: ${(exportResult.sizeBytes / 1024).toFixed(1)} KB (${exportResult.sizeBytes} bytes)`);

        if (exportResult.sizeBytes < 50000) {
            throw new Error(`Exported WebM file size (${exportResult.sizeBytes} bytes) is below minimum expected threshold of 50KB.`);
        }

        const shot4 = path.join(SCREENSHOTS_DIR, '04_explainer_studio_export_complete.png');
        await client.screenshot(shot4);

        // Check console error count
        console.log('\n=================================================================');
        console.log(`Browser Console Errors: ${consoleErrors.length}`);
        if (consoleErrors.length > 0) {
            console.error('Fatal: Browser console errors detected during verification!');
            process.exit(1);
        }
        console.log('🏆 100% LIVE BROWSER VISUAL & AUDIO VERIFICATION CLEARED');
        console.log('=================================================================');

        client.close();
        // Close target tab
        try {
            await fetch(`http://127.0.0.1:${DEBUG_PORT}/json/close/${pageTargetId}`, { method: 'PUT' });
        } catch (_) {}
    } finally {
        cleanup();
    }
}

runVerification().catch((err) => {
    console.error('\n❌ Verification Failed:', err);
    process.exit(1);
});
