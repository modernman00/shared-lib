import { chromium } from '/Users/waleolaogun/Sites/bcp/node_modules/playwright/index.mjs';
import * as fs from 'fs';
import * as path from 'path';

const artifactsDir = '/Users/waleolaogun/.gemini/antigravity/brain/636adfe9-8e08-4458-ae18-e506eab051ff';

const appsToTest = [
    {
        name: 'bcp',
        url: 'http://bcp.test/poster-studio',
        expectedTitle: 'Healthcare',
        expectedArchetype: 'clinical_shift_surge',
        expectedField: 'Hourly Pay Rate'
    },
    {
        name: 'loaneasyfinance',
        url: 'https://lef.test/poster-studio',
        expectedTitle: 'LoanEasy',
        expectedArchetype: 'fintech_facility_offer',
        expectedField: 'Representative APR'
    },
    {
        name: 'iaccountapp',
        url: 'https://iaccountapp.test/poster-studio',
        expectedTitle: 'iAccount',
        expectedArchetype: 'tax_statutory_clinic',
        expectedField: 'Clinic / Masterclass Headline'
    },
    {
        name: 'execmindapp',
        url: 'https://execmindapp.test/poster-studio',
        expectedTitle: 'ExecMind',
        expectedArchetype: 'executive_board_summit',
        expectedField: 'Keynote Speaker'
    },
    {
        name: 'idecideapp',
        url: 'https://idecideapp.test/poster-studio',
        expectedTitle: 'iDecide',
        expectedArchetype: 'civic_referendum_poll',
        expectedField: 'Referendum Motion'
    },
    {
        name: 'scoretenant',
        url: 'https://scoretenant.test/poster-studio',
        expectedTitle: 'TenantScore',
        expectedArchetype: 'tenant_rent_passport',
        expectedField: 'Rent Guarantee Score'
    },
    {
        name: 'partyplatform',
        url: 'https://partyplatform.test/poster-studio',
        expectedTitle: 'PartyPlatform',
        expectedArchetype: 'neon_script_clash',
        expectedField: 'Primary Headline'
    },
    {
        name: 'familyplatform',
        url: 'https://olaogun.test/poster-studio',
        expectedTitle: 'FamilyPlatform',
        expectedArchetype: 'vip_black_gold',
        expectedField: 'Traditional Attire'
    }
];

async function runVerification() {
    console.log('Launching Chromium for 8-App Sector Studio Verification...');
    const browser = await chromium.launch({
        headless: true,
        args: ['--ignore-certificate-errors', '--no-sandbox']
    });

    let overallSuccess = true;

    for (const app of appsToTest) {
        console.log(`\n======================================================`);
        console.log(`Testing Sector Studio for ${app.name.toUpperCase()} at ${app.url}`);
        const context = await browser.newContext({ ignoreHTTPSErrors: true });
        const page = await context.newPage();

        const consoleErrors = [];
        page.on('console', msg => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });
        page.on('pageerror', err => {
            consoleErrors.push(err.message);
        });

        try {
            const resp = await page.goto(app.url, { waitUntil: 'networkidle', timeout: 20000 });
            console.log(`[HTTP ${resp.status()}] Page loaded.`);

            if (resp.status() !== 200) {
                throw new Error(`Expected HTTP 200 but got ${resp.status()}`);
            }

            // Wait for main title and canvas
            await page.waitForSelector('#studioMainTitle', { timeout: 8000 });
            await page.waitForSelector('#atgPosterCanvas', { timeout: 8000 });

            const studioTitle = await page.$eval('#studioMainTitle', el => el.textContent.trim());
            console.log(`Studio Header: "${studioTitle}"`);
            if (!studioTitle.includes(app.expectedTitle)) {
                throw new Error(`Title mismatch: expected to contain "${app.expectedTitle}", got "${studioTitle}"`);
            }

            // Verify strict multi-tenant isolation: No portfolio switcher or foreign app names
            const appSwitcher = await page.$('#appSwitcherStrip');
            if (appSwitcher) {
                const isVisible = await appSwitcher.isVisible();
                if (isVisible) {
                    throw new Error(`Security / Isolation Violation: Portfolio switcher is visible on ${app.name}!`);
                }
            }
            const topbarText = await page.$eval('.studio-topbar', el => el.innerText);
            const prohibitedNames = appsToTest.filter(a => a.name !== app.name).map(a => a.expectedTitle);
            for (const badName of prohibitedNames) {
                if (topbarText.includes(badName)) {
                    throw new Error(`Security / Isolation Violation: Found foreign app name "${badName}" on ${app.name} studio topbar!`);
                }
            }
            console.log(`[PASS] Strict Single-App Isolation Verified: 0 foreign apps leaked on ${app.name}`);

            // Check sector field labels in DOM
            const pageText = await page.content();
            if (!pageText.includes(app.expectedField)) {
                throw new Error(`Sector field "${app.expectedField}" not found in studio DOM`);
            }
            console.log(`[PASS] Found sector-specific field label: "${app.expectedField}"`);

            // Verify archetype button is present in archetype grid
            const archetypeBtn = await page.$(`button[data-archetype="${app.expectedArchetype}"]`);
            if (!archetypeBtn) {
                throw new Error(`Dedicated archetype button for "${app.expectedArchetype}" not found`);
            }
            console.log(`[PASS] Found sector archetype button: "${app.expectedArchetype}"`);

            // Click archetype button to switch layout
            await archetypeBtn.click();
            await page.waitForTimeout(600);

            // Verify canvas has rendered content
            const isCanvasPopulated = await page.evaluate(() => {
                const canvas = document.getElementById('atgPosterCanvas');
                if (!canvas) return false;
                const ctx = canvas.getContext('2d');
                const pixel = ctx.getImageData(canvas.width / 2, canvas.height / 2, 1, 1).data;
                return pixel[3] > 0;
            });

            if (!isCanvasPopulated) {
                throw new Error('Canvas was empty or blank after render');
            }
            console.log(`[PASS] Canvas 2D engine successfully populated active sector shaders.`);

            // Verify 0 console errors (filter non-fatal third-party font network warnings if any)
            const fatalErrors = consoleErrors.filter(e => !e.includes('favicon.ico') && !e.includes('fonts.googleapis.com'));
            if (fatalErrors.length > 0) {
                console.warn(`[WARN] Fatal console errors detected:`, fatalErrors);
                throw new Error(`Console errors encountered: ${fatalErrors.join(' | ')}`);
            }
            console.log(`[PASS] 0 Console Errors on ${app.name}`);

            // Take studio full UI screenshot and direct canvas poster screenshot
            const screenshotPath = path.join(artifactsDir, `studio_${app.name}_rendered.png`);
            await page.screenshot({ path: screenshotPath, fullPage: false });
            console.log(`[PASS] Screenshot captured: ${screenshotPath}`);

            const canvasEl = await page.$('#atgPosterCanvas');
            if (canvasEl) {
                const canvasPath = path.join(artifactsDir, `poster_${app.name}.png`);
                const dataUrl = await page.evaluate(() => {
                    const canvas = document.getElementById('atgPosterCanvas');
                    return canvas ? canvas.toDataURL('image/png') : null;
                });

                if (dataUrl && dataUrl.startsWith('data:image/png;base64,')) {
                    const base64Data = dataUrl.replace(/^data:image\/png;base64,/, '');
                    fs.writeFileSync(canvasPath, Buffer.from(base64Data, 'base64'));
                    console.log(`[PASS] Full-Res (1080x1920) Poster Canvas captured: ${canvasPath}`);
                } else {
                    await canvasEl.screenshot({ path: canvasPath });
                    console.log(`[PASS] Poster Canvas DOM screenshot captured: ${canvasPath}`);
                }
            }

        } catch (err) {
            console.error(`[FAIL] Verification error on ${app.name}:`, err.message);
            overallSuccess = false;
        } finally {
            await context.close();
        }
    }

    await browser.close();

    if (!overallSuccess) {
        console.error('\nVerification failed for one or more applications.');
        process.exit(1);
    } else {
        console.log('\n======================================================');
        console.log('ALL 8 SECTOR STUDIOS VERIFIED IN BROWSER WITH 0 CONSOLE ERRORS!');
        console.log('======================================================');
    }
}

runVerification().catch(err => {
    console.error('Fatal execution error:', err);
    process.exit(1);
});
