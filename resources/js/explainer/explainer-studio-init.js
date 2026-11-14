/**
 * ExplainerStudioInit — Auto-wires .atg-canvas-studio containers to ExplainerCanvasRenderer & ExplainerSfx
 */
(function (global) {
    'use strict';

    function initCanvasStudios() {
        if (typeof document === 'undefined' || !global.ExplainerCanvasRenderer) return;

        document.querySelectorAll('.atg-canvas-studio').forEach((root) => {
            if (root.dataset.atgStudioBound === '1') return;
            root.dataset.atgStudioBound = '1';

            const canvas = root.querySelector('.atg-studio-canvas');
            const manifestEl = root.querySelector('.atg-studio-manifest');
            const stylesEl = root.querySelector('.atg-studio-styles');
            if (!canvas || !manifestEl) return;

            let manifest = {};
            try {
                manifest = JSON.parse(manifestEl.textContent || '{}');
            } catch (err) {
                console.error('[ExplainerStudio] Failed to parse manifest JSON:', err);
                manifest = {};
            }

            let styles = {};
            try {
                styles = stylesEl ? JSON.parse(stylesEl.textContent || '{}') : {};
            } catch (err) {
                console.error('[ExplainerStudio] Failed to parse styles JSON:', err);
                styles = {};
            }

            const sfx = global.ExplainerSfx ? new global.ExplainerSfx() : null;

            const playBtn = root.querySelector('.atg-studio-play');
            const muteBtn = root.querySelector('.atg-studio-mute');
            const styleSelect = root.querySelector('.atg-studio-style-select');
            const audioInput = root.querySelector('.atg-studio-audio-input');
            const exportBtn = root.querySelector('.atg-studio-export');
            const scrubber = root.querySelector('.atg-studio-scrubber');
            const timeEl = root.querySelector('.atg-studio-time');

            const renderer = new global.ExplainerCanvasRenderer(canvas, manifest, {
                sfx,
                onTimeUpdate(t, total) {
                    if (scrubber) scrubber.value = String(t.toFixed(2));
                    if (timeEl) timeEl.textContent = `${t.toFixed(1)}s / ${total.toFixed(1)}s`;
                },
                onComplete() {
                    if (playBtn) playBtn.textContent = '▶ Play';
                },
            });

            root.__explainerRenderer = renderer;
            if (typeof window !== 'undefined') window.__explainerRenderer = renderer;

            renderer.renderAtTime(0, false);

            if (playBtn) {
                playBtn.addEventListener('click', () => {
                    if (renderer.isPlaying) {
                        renderer.pause();
                        playBtn.textContent = '▶ Play';
                    } else {
                        renderer.play();
                        playBtn.textContent = '⏸ Pause';
                    }
                });
            }

            if (muteBtn && sfx) {
                muteBtn.addEventListener('click', () => {
                    const nextMuted = !sfx.muted;
                    sfx.setMuted(nextMuted);
                    muteBtn.setAttribute('aria-pressed', nextMuted ? 'true' : 'false');
                    muteBtn.textContent = nextMuted ? '🔇 SFX Off' : '🔊 SFX On';
                });
            }

            if (styleSelect) {
                styleSelect.addEventListener('change', (e) => {
                    const chosen = styles[e.target.value];
                    if (chosen) renderer.setStyle(chosen);
                });
            }

            if (audioInput) {
                let currentVoiceoverUrl = null;
                audioInput.addEventListener('change', (e) => {
                    const file = e.target.files && e.target.files[0];
                    if (!file) return;
                    if (currentVoiceoverUrl) {
                        try { URL.revokeObjectURL(currentVoiceoverUrl); } catch (_) {}
                    }
                    currentVoiceoverUrl = URL.createObjectURL(file);
                    renderer.attachVoiceoverAudio(currentVoiceoverUrl);
                });
            }

            if (scrubber) {
                scrubber.addEventListener('input', (e) => {
                    renderer.seek(parseFloat(e.target.value));
                });
            }

            if (exportBtn) {
                exportBtn.addEventListener('click', async () => {
                    const interactiveControls = [playBtn, scrubber, styleSelect, audioInput].filter(Boolean);
                    interactiveControls.forEach((el) => {
                        el.disabled = true;
                        el.classList.add('opacity-50', 'cursor-not-allowed');
                    });

                    exportBtn.disabled = true;
                    exportBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    exportBtn.textContent = '⏳ Exporting...';
                    let downloadUrl = null;
                    try {
                        const blob = await renderer.exportWebM();
                        downloadUrl = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = downloadUrl;
                        const isMp4 = blob.type && blob.type.includes('mp4');
                        const ext = isMp4 ? '.mp4' : '.webm';
                        a.download = ((manifest.storyboard && manifest.storyboard.id) || 'explainer') + ext;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    } catch (err) {
                        console.error('[ExplainerStudio] Video export failed:', err);
                        alert('Video export failed: ' + (err && err.message ? err.message : String(err)));
                    } finally {
                        if (downloadUrl) {
                            setTimeout(() => {
                                try { URL.revokeObjectURL(downloadUrl); } catch (_) {}
                            }, 5000);
                        }
                        exportBtn.disabled = false;
                        exportBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        exportBtn.textContent = '⬇ Export WebM';

                        interactiveControls.forEach((el) => {
                            el.disabled = false;
                            el.classList.remove('opacity-50', 'cursor-not-allowed');
                        });
                        if (playBtn) {
                            playBtn.textContent = renderer.isPlaying ? '⏸ Pause' : '▶ Play';
                        }
                    }
                });
            }
        });
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', initCanvasStudios);
    }

    global.initExplainerCanvasStudios = initCanvasStudios;
})(typeof window !== 'undefined' ? window : globalThis);
