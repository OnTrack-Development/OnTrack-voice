const puppeteer = require('puppeteer-core');

(async () => {
    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: 'new',
        args: [
            '--use-fake-ui-for-media-stream',
            '--use-fake-device-for-media-stream',
            '--allow-file-access',
            '--no-sandbox',
            '--disable-setuid-sandbox'
        ]
    });

    const page = await browser.newPage();

    // Grant microphone permission
    const context = browser.defaultBrowserContext();
    await context.overridePermissions('https://app.ontrackegy.com', ['microphone']);

    page.on('console', msg => console.log('PAGE CONSOLE:', msg.type(), msg.text()));
    page.on('pageerror', err => console.log('PAGE ERROR:', err.message));
    page.on('dialog', async dialog => {
        console.log('PAGE DIALOG ALERT:', dialog.message());
        await dialog.dismiss();
    });

    page.on('requestfailed', request => {
        console.log('REQUEST FAILED:', request.url(), request.failure()?.errorText);
    });
    page.on('response', response => {
        if (response.status() >= 400) {
            console.log('HTTP ERROR RESPONSE:', response.status(), response.url());
        }
    });

    console.log('Navigating to https://app.ontrackegy.com/aivoiceagent/1?share=live ...');
    await page.goto('https://app.ontrackegy.com/aivoiceagent/1?share=live', { waitUntil: 'networkidle2' });

    console.log('Page Title:', await page.title());

    // Click the call button
    console.log('Clicking call button...');
    const btn = await page.$('#btnCall');
    if (btn) {
        await btn.click();
        await new Promise(r => setTimeout(r, 3000));
    } else {
        console.log('Button #btnCall not found!');
    }

    await browser.close();
})();
