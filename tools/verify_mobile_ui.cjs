const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');

(async () => {
    console.log('=== OnTrack Voice: Mobile UI & Model Verification ===');
    const token = process.env.ONTRACK_SESSION_TOKEN || (fs.existsSync('saas/storage/app/session_token.txt') ? fs.readFileSync('saas/storage/app/session_token.txt', 'utf8').trim() : '');

    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: 'new',
        defaultViewport: { width: 390, height: 844, isMobile: true, hasTouch: true },
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-web-security'
        ]
    });

    const page = await browser.newPage();
    const artifactsDir = 'C:\\Users\\ahmed\\.gemini\\antigravity\\brain\\2a7c21fd-e80d-4adb-b035-2fdd6464252d';

    // Dismiss any alerts
    page.on('dialog', async dialog => {
        console.log('  Dismissed dialog:', dialog.message());
        await dialog.dismiss();
    });

    // 1. Mobile Api Keys Tab
    const authUrl = `https://app.ontrackegy.com/auth/dev-preview?token=${token}&tab=api_keys`;
    console.log('Step 1: Navigating to Mobile API Keys:', authUrl);
    await page.goto(authUrl, { waitUntil: 'networkidle2' });
    console.log('  Page URL after goto:', page.url());
    console.log('  Page title:', await page.title());

    // Check topbar height
    const topbarHeight = await page.$eval('.topbar', el => el.offsetHeight).catch(() => null);
    console.log('  Mobile Topbar Height:', topbarHeight, 'px (Target: <= 56px)');

    // Check Models displayed on provider cards
    const cardTexts = await page.$$eval('.ontrack-provider-card', cards => cards.map(c => c.innerText));
    console.log('  Provider Cards Found:', cardTexts.length);
    cardTexts.forEach((t, i) => console.log(`    Card ${i+1} snippet:`, t.split('\n')[0], '|', t.includes('الموديل') ? t.substring(t.indexOf('الموديل'), t.indexOf('الموديل')+35) : ''));

    const shot1 = path.join(artifactsDir, 'mobile_api_keys_live.png');
    await page.screenshot({ path: shot1, fullPage: false });
    console.log('  + Screenshot saved:', shot1);

    // 2. Mobile Calls Tab
    console.log('Step 2: Navigating to Mobile Calls Tab...');
    await page.goto('https://app.ontrackegy.com/?tab=calls', { waitUntil: 'networkidle2' });
    const callsShot = path.join(artifactsDir, 'mobile_calls_live.png');
    await page.screenshot({ path: callsShot, fullPage: false });
    console.log('  + Screenshot saved:', callsShot);

    // 3. Mobile Floorplan Tab
    console.log('Step 3: Navigating to Mobile Floorplan Tab...');
    await page.goto('https://app.ontrackegy.com/?tab=floorplan', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 3000));
    const floorplanShot = path.join(artifactsDir, 'mobile_floorplan_live.png');
    await page.screenshot({ path: floorplanShot, fullPage: false });
    console.log('  + Screenshot saved:', floorplanShot);

    await browser.close();
    console.log('=== Mobile UI Verification Completed Successfully! ===');
})();
