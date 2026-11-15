const puppeteer = require('puppeteer-core');

(async () => {
    console.log('=== OnTrack Voice Headless Browser Audit Starting ===');
    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: 'new',
        args: [
            '--use-fake-ui-for-media-stream',
            '--use-fake-device-for-media-stream',
            '--no-sandbox',
            '--disable-setuid-sandbox'
        ]
    });

    const page = await browser.newPage();
    const context = browser.defaultBrowserContext();
    await context.overridePermissions('https://app.ontrackegy.com', ['microphone']);

    const errors = [];
    page.on('console', msg => {
        if (msg.type() === 'error') {
            console.log('  [CONSOLE ERROR]:', msg.text());
            errors.push({ type: 'console.error', text: msg.text() });
        }
    });
    page.on('pageerror', err => {
        console.log('  [PAGE ERROR]:', err.message);
        errors.push({ type: 'pageerror', text: err.message });
    });
    page.on('dialog', async d => {
        console.log('  [DIALOG ALERT]:', d.message());
        await d.dismiss();
    });

    // 1. Visit Login Page & Login
    console.log('Step 1: Logging in to https://app.ontrackegy.com/login ...');
    await page.goto('https://app.ontrackegy.com/login', { waitUntil: 'networkidle2' });
    
    // Check if login form exists
    const emailInput = await page.$('input[name="email"], input[name="login"]');
    if (emailInput) {
        await page.type('input[name="email"], input[name="login"]', 'owner@ontrackegy.com');
        await page.type('input[name="password"]', 'admin123456'); // Or demo credentials
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2' }),
            page.click('button[type="submit"]')
        ]).catch(() => console.log('Login form submitted without standard navigation'));
    }
    console.log('Current URL after login attempt:', page.url());

    // 2. Audit Tab: Agents Studio
    console.log('\nStep 2: Auditing Agents Studio (?tab=agents) ...');
    await page.goto('https://app.ontrackegy.com/?tab=agents', { waitUntil: 'networkidle2' });
    console.log('Agents tab title:', await page.title());

    // Check 6 Studio buttons
    const studioTabs = ['persona', 'skills', 'engine', 'knowledge', 'test', 'performance'];
    for (const t of studioTabs) {
        const btnId = `#tabBtn${t.charAt(0).toUpperCase() + t.slice(1)}`;
        const btn = await page.$(btnId);
        if (btn) {
            console.log(`  Clicking Studio Tab: ${t} (${btnId})...`);
            await btn.click().catch(e => console.log(`  Failed to click ${btnId}:`, e.message));
            await new Promise(r => setTimeout(r, 500));
        } else {
            console.log(`  Button NOT FOUND: ${btnId}`);
        }
    }

    // 3. Audit Tab: Calls
    console.log('\nStep 3: Auditing Calls Tab (?tab=calls) ...');
    await page.goto('https://app.ontrackegy.com/?tab=calls', { waitUntil: 'networkidle2' });
    const callItems = await page.$$('.zoie-call-item');
    console.log(`  Found ${callItems.length} call items in list.`);

    // 4. Audit Tab: Messages
    console.log('\nStep 4: Auditing Messages Tab (?tab=messages) ...');
    await page.goto('https://app.ontrackegy.com/?tab=messages', { waitUntil: 'networkidle2' });

    // 5. Audit Voice Call Page
    console.log('\nStep 5: Auditing Web Voice Call (/aivoiceagent/1?share=live) ...');
    await page.goto('https://app.ontrackegy.com/aivoiceagent/1?share=live', { waitUntil: 'networkidle2' });
    const btnCall = await page.$('#btnCall');
    if (btnCall) {
        console.log('  Found #btnCall, clicking to initiate voice call...');
        await btnCall.click();
        await new Promise(r => setTimeout(r, 2000));
    }

    console.log('\n=== Total Uncaught Errors Caught ===');
    console.log(JSON.stringify(errors, null, 2));

    await browser.close();
})();
