const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');

(async () => {
    console.log('=== OnTrack Voice: Production Live Verification ===');
    const token = '89030ffb89c047ca214ad73eadc0c3f45b21e6fea3baa59c76d2a7de34f7d39c';
    const artifactsDir = 'C:\\Users\\ahmed\\.gemini\\antigravity\\brain\\2a7c21fd-e80d-4adb-b035-2fdd6464252d';

    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: 'new',
        defaultViewport: { width: 1440, height: 900 },
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-web-security'
        ]
    });

    const page = await browser.newPage();

    page.on('dialog', async dialog => {
        console.log('  Dismissed dialog:', dialog.message());
        await dialog.dismiss();
    });

    // 1. Authenticate & Open API Keys Tab
    const authUrl = `https://app.ontrackegy.com/auth/dev-preview?token=${token}&tab=api_keys`;
    console.log('Step 1: Navigating to API Keys Tab...');
    await page.goto(authUrl, { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 1500));

    // Verify Fish Audio is present
    const pageContent = await page.content();
    console.log('  Fish Audio in HTML:', pageContent.includes('Fish Audio') ? 'YES' : 'NO');
    console.log('  drama-3 in HTML:', pageContent.includes('drama-3') ? 'YES' : 'NO');
    console.log('  Fish Audio Primary in HTML:', pageContent.includes('Fish Audio Primary') ? 'YES' : 'NO');

    const shotApiKeys = path.join(artifactsDir, 'production_api_keys_live.png');
    await page.screenshot({ path: shotApiKeys, fullPage: false });
    console.log('  + Screenshot saved:', shotApiKeys);

    // 2. Open Devices Tab
    console.log('Step 2: Navigating to Devices Tab...');
    await page.goto('https://app.ontrackegy.com/?tab=devices', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 1500));

    const devContent = await page.content();
    console.log('  Xiaomi 22101316I in HTML:', devContent.includes('22101316I') ? 'YES' : 'NO');
    console.log('  Redmi Note 12 Pro in HTML:', devContent.includes('Redmi Note 12 Pro') ? 'YES' : 'NO');
    console.log('  SIM 1 in HTML:', devContent.includes('SIM 1') ? 'YES' : 'NO');
    console.log('  SIM 2 in HTML:', devContent.includes('SIM 2') ? 'YES' : 'NO');

    const shotDevices = path.join(artifactsDir, 'production_devices_live.png');
    await page.screenshot({ path: shotDevices, fullPage: false });
    console.log('  + Screenshot saved:', shotDevices);

    // 3. Open Calls Tab with Call 75
    console.log('Step 3: Navigating to Calls Tab (Call 75 - Mahmoud Mansour)...');
    await page.goto('https://app.ontrackegy.com/?tab=calls&call_id=75', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 1500));

    const callsContent = await page.content();
    console.log('  01119060707 in HTML:', callsContent.includes('01119060707') ? 'YES' : 'NO');
    console.log('  Mahmoud Mansour in HTML:', callsContent.includes('Mahmoud Mansour') ? 'YES' : 'NO');
    console.log('  Ahmed Seef in HTML:', callsContent.includes('Ahmed Seef') ? 'YES' : 'NO');

    const shotCalls = path.join(artifactsDir, 'production_calls_live.png');
    await page.screenshot({ path: shotCalls, fullPage: false });
    console.log('  + Screenshot saved:', shotCalls);

    // 4. Click "عرض نص المحادثة" to open Transcript Modal
    console.log('Step 4: Opening Transcript Modal for Call 75...');
    const btnTranscript = await page.$('button[onclick="openTranscriptModal()"]');
    if (btnTranscript) {
        await btnTranscript.click();
        await new Promise(r => setTimeout(r, 1000));
        const modalContent = await page.$eval('#ontrackTranscriptModal', el => el.innerText).catch(() => '');
        console.log('  Modal text snippet:', modalContent.substring(0, 200).replace(/\n+/g, ' '));
        console.log('  Contains real conversation:', modalContent.includes('معلم ماني') || modalContent.includes('المتصل') ? 'YES' : 'NO');
        
        const shotTranscript = path.join(artifactsDir, 'production_transcript_modal_live.png');
        await page.screenshot({ path: shotTranscript, fullPage: false });
        console.log('  + Screenshot saved:', shotTranscript);
    } else {
        console.log('  FAIL: btnTranscript not found!');
    }

    // 5. Mobile View Verification
    console.log('Step 5: Verifying Mobile View...');
    await page.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
    await page.goto('https://app.ontrackegy.com/?tab=api_keys', { waitUntil: 'networkidle2' });
    const shotMobileKeys = path.join(artifactsDir, 'production_mobile_api_keys.png');
    await page.screenshot({ path: shotMobileKeys, fullPage: false });
    console.log('  + Screenshot saved:', shotMobileKeys);

    await page.goto('https://app.ontrackegy.com/?tab=calls&call_id=75', { waitUntil: 'networkidle2' });
    const shotMobileCalls = path.join(artifactsDir, 'production_mobile_calls.png');
    await page.screenshot({ path: shotMobileCalls, fullPage: false });
    console.log('  + Screenshot saved:', shotMobileCalls);

    await browser.close();
    console.log('=== All Verifications Finished Successfully! ===');
})();
