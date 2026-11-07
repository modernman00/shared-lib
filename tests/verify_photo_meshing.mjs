import { chromium } from '/Users/waleolaogun/Sites/bcp/node_modules/playwright/index.mjs';
import * as fs from 'fs';
import * as path from 'path';

const artifactsDir = '/Users/waleolaogun/.gemini/antigravity/brain/636adfe9-8e08-4458-ae18-e506eab051ff';
const samplePhoto = path.join(process.cwd(), 'resources/poster-assets/bg_vip_gold_lounge.jpg');

async function testUploadMeshing() {
    console.log('🚀 Running Real Photo Upload & Edge Dissolve Meshing Verification...');
    const browser = await chromium.launch({
        headless: true,
        args: ['--ignore-certificate-errors', '--no-sandbox']
    });

    const context = await browser.newContext({
        ignoreHTTPSErrors: true,
        viewport: { width: 1440, height: 960 }
    });
    const page = await context.newPage();

    const errors = [];
    page.on('console', msg => {
        if (msg.type() === 'error') errors.push(msg.text());
    });
    page.on('pageerror', err => errors.push(err.message));

    try {
        console.log('Navigating to PartyPlatform Poster Studio...');
        await page.goto('https://partyplatform.test/poster-studio', { waitUntil: 'networkidle', timeout: 15000 });
        await page.waitForSelector('#atgPosterCanvas', { timeout: 5000 });

        // Switch to VIP Black & Gold archetype
        console.log('Switching to VIP Black & Gold archetype...');
        await page.click('button[data-archetype="vip_black_gold"]');
        await page.waitForTimeout(600);

        // Upload a real rectangular photo into subjectUploadInput
        console.log('Uploading real camera photo to #subjectUploadInput...');
        await page.setInputFiles('#subjectUploadInput', samplePhoto);
        await page.waitForTimeout(1500); // Allow image reading, meshing, and canvas draw

        // Fill Title
        await page.fill('#inpTitle', 'JUMMY BIRTHDDAY');
        await page.dispatchEvent('#inpTitle', 'input');
        await page.waitForTimeout(600);

        // Capture screenshot of the meshed uploaded portrait
        const shotPath = path.join(artifactsDir, 'studio_partyplatform_uploaded_meshed.png');
        await page.screenshot({ path: shotPath });
        console.log(`📸 Studio UI screenshot saved: ${shotPath}`);

        const canvasBase64 = await page.evaluate(() => {
            const c = document.getElementById('atgPosterCanvas');
            return c.toDataURL('image/png').replace(/^data:image\/png;base64,/, '');
        });
        const posterCanvasPath = path.join(artifactsDir, 'poster_partyplatform_uploaded_meshed_canvas.png');
        fs.writeFileSync(posterCanvasPath, canvasBase64, 'base64');
        console.log(`🎨 Meshed Canvas Poster saved: ${posterCanvasPath}`);

        console.log('Console Errors:', errors);
        if (errors.length === 0) {
            console.log('✅ ZERO CONSOLE ERRORS ON REAL PHOTO UPLOAD & MESHING!');
        } else {
            console.warn('⚠️ Console errors found:', errors);
        }

    } catch (e) {
        console.error('❌ Test failed:', e.message);
        process.exit(1);
    } finally {
        await browser.close();
    }
}

testUploadMeshing();
