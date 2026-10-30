// Offline UI/bridge smoke test; not a handset/WebView compatibility test.
const {chromium}=require('playwright');const fs=require('fs');
(async()=>{
 const browser=await chromium.launch({headless:true});const page=await browser.newPage({viewport:{width:393,height:1180}});
 let network=0;const errors=[];await page.route('**/*',r=>{network++;r.abort()});page.on('pageerror',e=>errors.push(e.message));
 const policy=`<meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; connect-src 'none'; frame-src 'none'">`;
 const bridge=`<script>window.OTA_NONCE='test-render';window.calls=[];window.OnTrack={postMessage:raw=>window.calls.push(JSON.parse(raw))};</script>`;
 await page.setContent(fs.readFileSync('saas/mobile/ota/ui.html','utf8').replace('<head>','<head>'+policy+bridge));
 const calls=()=>page.evaluate(()=>window.calls);
 if((await calls())[0]?.nonce!=='test-render')throw Error('No scoped ready handshake');
 const state={status:'جاهز',running:false,inCall:false,revision:2,native:'0.1.6-ota',updating:false};
 await page.evaluate(s=>{onTrackInit({base:'https://app.ontrackegy.com/',key:''});onTrackState(s)},state);
 await page.locator('#key').fill('invalid');await page.locator('#start').click();if((await calls()).length!==1)throw Error('Invalid input passed');
 await page.locator('#key').fill('test_key_for_local_UI_only_123456');await page.locator('#start').click();if((await calls()).at(-1).command!=='start')throw Error('Start missing');
 await page.evaluate(s=>onTrackState(s),{...state,running:true,inCall:true});
 if(!await page.locator('#key').isDisabled()||!await page.locator('#rollback').isDisabled())throw Error('Active call guard missing');
 await page.locator('#stop').click();if((await calls()).at(-1).command!=='stop')throw Error('Stop missing');
 await page.evaluate(s=>onTrackState(s),{...state,status:'تعذر الربط: HTTP 401',updating:true});if(!await page.locator('#start').isDisabled())throw Error('Update guard missing');
 await page.screenshot({path:'/tmp/ontrack-ota-ui.png',fullPage:true});if(errors.length||network)throw Error('JS errors or external network');
 await browser.close();console.log('PASS: Arabic UI nonce, key validation, native start/stop, active-call and update guards; zero network');
})().catch(e=>{console.error(e.message);process.exit(1)});
