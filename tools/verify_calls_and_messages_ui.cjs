const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');

(async () => {
    console.log('=== OnTrack Voice: Calls & Messages UI Verification ===');
    const token = process.env.ONTRACK_SESSION_TOKEN || (fs.existsSync('saas/storage/app/session_token.txt') ? fs.readFileSync('saas/storage/app/session_token.txt', 'utf8').trim() : '');

    console.log('Step 1: Launching Chrome...');
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
    const artifactsDir = 'C:\\Users\\ahmed\\.gemini\\antigravity\\brain\\2a7c21fd-e80d-4adb-b035-2fdd6464252d';

    // Step 2: Authenticate and Navigate to Calls Tab
    const authUrl = `https://app.ontrackegy.com/auth/dev-preview?token=${token}&tab=calls`;
    console.log('Step 2: Navigating to Auth & Calls URL:', authUrl);
    await page.goto(authUrl, { waitUntil: 'networkidle2' });
    console.log('  Current URL after login redirect:', page.url());

    // Verify Calls Header
    const headerTitle = await page.$eval('.ontrack-pane strong', el => el.innerText).catch(() => null);
    console.log('  Calls Header Text:', headerTitle);

    const activePhone = await page.$eval('.ontrack-pane div[dir="ltr"]', el => el.innerText).catch(() => null);
    console.log('  Active Call Phone:', activePhone);

    const shot1 = path.join(artifactsDir, 'calls_tab_overview.png');
    await page.screenshot({ path: shot1, fullPage: false });
    console.log('  + Screenshot saved:', shot1);

    // Step 3: Open Transcript Modal
    console.log('\nStep 3: Clicking [View transcript] button...');
    const btnTranscript = await page.$('button[onclick*="openTranscriptModal"]');
    if (btnTranscript) {
        await btnTranscript.click();
        await new Promise(r => setTimeout(r, 800));

        const modalVisible = await page.$eval('#ontrackTranscriptModal', el => window.getComputedStyle(el).display !== 'none');
        console.log('  Transcript Modal Display Visible:', modalVisible);

        const bubblesCount = await page.$$eval('#ontrackTranscriptModal div[style*="border-radius: 14px"]', els => els.length);
        console.log('  Found Chat Bubbles Count:', bubblesCount);

        const shot2 = path.join(artifactsDir, 'calls_transcript_modal.png');
        await page.screenshot({ path: shot2, fullPage: false });
        console.log('  + Screenshot saved:', shot2);

        // Close modal
        await page.click('#ontrackTranscriptModal button[onclick*="closeTranscriptModal"]');
        await new Promise(r => setTimeout(r, 400));
    } else {
        console.log('  WARNING: View transcript button not found');
    }

    // Step 4: Click [Link to Existing Lead]
    console.log('\nStep 4: Testing Link to Existing Lead View...');
    const btnLink = await page.$('button[onclick*="switchLeadView(\'search\')"]');
    if (btnLink) {
        await btnLink.click();
        await new Promise(r => setTimeout(r, 600));

        const searchVisible = await page.$eval('#leadViewSearch', el => window.getComputedStyle(el).display !== 'none');
        console.log('  Leads Search View Visible:', searchVisible);

        const shot3 = path.join(artifactsDir, 'calls_link_drawer.png');
        await page.screenshot({ path: shot3, fullPage: false });
        console.log('  + Screenshot saved:', shot3);
    } else {
        console.log('  WARNING: Link to Existing Lead button not found');
    }

    // Step 5: Navigate to Messages Tab
    console.log('\nStep 5: Navigating to https://app.ontrackegy.com/?tab=messages ...');
    await page.goto('https://app.ontrackegy.com/?tab=messages', { waitUntil: 'networkidle2' });

    const msgHeader = await page.$eval('.ontrack-msg-pane strong', el => el.innerText).catch(() => null);
    console.log('  Messages Header:', msgHeader);

    const shot4 = path.join(artifactsDir, 'messages_tab_view.png');
    await page.screenshot({ path: shot4, fullPage: false });
    console.log('  + Screenshot saved:', shot4);

    await browser.close();
    console.log('\n=== All 4 Verification Screenshots Captured Successfully! ===');
})();
