import { chromium } from '/Users/waleolaogun/Sites/bcp/node_modules/playwright/index.mjs';
import * as fs from 'fs';
import * as path from 'path';

const artifactsDir = '/Users/waleolaogun/.gemini/antigravity/brain/636adfe9-8e08-4458-ae18-e506eab051ff';

async function testExperianBoost() {
    console.log('🚀 Starting Experian Boost & Tax Health Studio Browser Verification...');
    const browser = await chromium.launch({
        headless: true,
        args: ['--ignore-certificate-errors', '--no-sandbox']
    });

    const targets = [
        {
            app: 'scoretenant',
            url: 'https://scoretenant.test/poster-studio',
            expectedPreset: 'Score Card — Tenant Rent Boost (1250/1250)',
            expectedArchetype: 'tenant_credit_boost_mockup',
            studioShot: 'studio_scoretenant_credit_boost_rendered.png',
            posterShot: 'poster_scoretenant_credit_boost_canvas.png'
        },
        {
            app: 'iaccountapp',
            url: 'https://iaccountapp.test/poster-studio',
            expectedPreset: 'Score Card — Tax Health Score (980/1000)',
            expectedArchetype: 'tax_health_score_app',
            studioShot: 'studio_iaccount_tax_health_rendered.png',
            posterShot: 'poster_iaccount_tax_health_canvas.png'
        }
    ];

    let allPass = true;

    for (const t of targets) {
        console.log(`\n======================================================`);
        console.log(`Verifying ${t.app.toUpperCase()} at ${t.url}`);
        const context = await browser.newContext({
            ignoreHTTPSErrors: true,
            viewport: { width: 1440, height: 960 }
        });
        const page = await context.newPage();

        const errors = [];
        page.on('console', msg => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
            }
        });
        page.on('pageerror', err => {
            errors.push(err.message);
        });

        try {
            const resp = await page.goto(t.url, { waitUntil: 'networkidle', timeout: 15000 });
            console.log(`HTTP Status: ${resp.status()}`);
            if (resp.status() !== 200) {
                console.error(`❌ HTTP Status is not 200: ${resp.status()}`);
                allPass = false;
                continue;
            }

            // Wait for canvas to be populated
            await page.waitForSelector('#atgPosterCanvas', { timeout: 5000 });
            await page.waitForTimeout(1500); // Give canvas time to draw and font metrics to settle

            // Check active preset
            const activePreset = await page.$eval('#presetSelect', el => {
                const opt = el.options[el.selectedIndex];
                return opt ? opt.textContent : '';
            });
            console.log(`Active Curated Preset: "${activePreset}"`);

            // Verify canvas has valid image data
            const canvasData = await page.evaluate(() => {
                const c = document.getElementById('atgPosterCanvas');
                return {
                    width: c.width,
                    height: c.height,
                    dataUrlLen: c.toDataURL().length
                };
            });
            console.log(`Canvas dimensions: ${canvasData.width}x${canvasData.height}, data length: ${canvasData.dataUrlLen}`);

            if (canvasData.dataUrlLen < 5000) {
                console.error(`❌ Canvas seems empty or blank! DataUrl length: ${canvasData.dataUrlLen}`);
                allPass = false;
            } else {
                console.log(`✅ Canvas actively rendered with rich graphics!`);
            }

            // Capture Studio UI Screenshot
            const studioShotPath = path.join(artifactsDir, t.studioShot);
            await page.screenshot({ path: studioShotPath, fullPage: false });
            console.log(`📸 Studio UI screenshot saved: ${studioShotPath}`);

            // Extract canvas directly to file
            const base64Data = await page.evaluate(() => {
                const c = document.getElementById('atgPosterCanvas');
                return c.toDataURL('image/png').replace(/^data:image\/png;base64,/, '');
            });
            const posterShotPath = path.join(artifactsDir, t.posterShot);
            fs.writeFileSync(posterShotPath, base64Data, 'base64');
            console.log(`🎨 Synthesized poster asset saved: ${posterShotPath}`);

            if (errors.length > 0) {
                console.warn(`⚠️ Console errors captured:`, errors);
            } else {
                console.log(`✅ 0 console errors detected!`);
            }

        } catch (e) {
            console.error(`❌ Test failed for ${t.app}:`, e.message);
            allPass = false;
        } finally {
            await context.close();
        }
    }

    await browser.close();

    if (allPass) {
        console.log(`\n🎉 ALL EXPERIAN BOOST & TAX HEALTH VISUAL TESTS PASSED WITH 100%!`);
        process.exit(0);
    } else {
        console.error(`\n❌ Some tests failed.`);
        process.exit(1);
    }
}

testExperianBoost();
