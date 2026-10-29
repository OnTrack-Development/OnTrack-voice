import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {spawn} from 'node:child_process';
const require = createRequire(import.meta.url);
const {chromium} = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES
  ? `${process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES}/playwright` : 'playwright');
const base = process.env.VOICE_TEST_URL || 'http://127.0.0.1:8080';
if (process.env.VOICE_TEST_PHP) {
  const server = spawn(process.env.VOICE_TEST_PHP, ['-S', '127.0.0.1:8080'], {cwd: new URL('../', import.meta.url), stdio: 'ignore'});
  server.unref();
  process.on('exit', () => server.kill());
  let ready = false;
  for (let attempt = 0; attempt < 100; attempt++) {
    try { await fetch(`${base}/api/status.php`); ready = true; break; } catch {}
    await new Promise(resolve => setTimeout(resolve, 30));
  }
  assert.equal(ready, true, 'PHP test server must start');
}
const browser = await chromium.launch({headless: true, ...(process.env.VOICE_TEST_BROWSER ? {executablePath: process.env.VOICE_TEST_BROWSER} : {})});
const context = await browser.newContext({viewport: {width: 1365, height: 900}});
const page = await context.newPage();
const pageErrors = [];
page.on('pageerror', error => pageErrors.push(error.message));
const requests = [];

// Validate the real PHP request path separately from the fake voice connection.
assert.equal((await context.request.post(`${base}/api/live_session.php`, {data: {mode: 'outbound', mission: {}}})).status(), 400);
assert.equal((await context.request.get(`${base}/api/live_session.php`)).status(), 405);

await page.addInitScript(() => {
  window.testLive = {sessions: []};
  window.testAudio = {processors: [], played: 0, stopped: 0, tracks: []};
  class AudioContext {
    state = 'running'; currentTime = 0; sampleRate = 48000; destination = {};
    async resume() { this.state = 'running'; }
    async close() { this.state = 'closed'; }
    createMediaStreamSource() { return {connect() {}, disconnect() {}}; }
    createGain() { return {gain: {value: 0}, connect() {}, disconnect() {}}; }
    createScriptProcessor() {
      const processor = {onaudioprocess: null, connect() {}, disconnect() {}};
      window.testAudio.processors.push(processor);
      return processor;
    }
    createBuffer(channels, length, rate) { return {duration: length / rate, getChannelData: () => new Float32Array(length)}; }
    createBufferSource() { return {connect() {}, start() { window.testAudio.played++; }, stop() { window.testAudio.stopped++; }}; }
  }
  window.AudioContext = AudioContext;
  Object.defineProperty(navigator.mediaDevices, 'getUserMedia', {value: async () => {
    if (window.denyMic) throw new DOMException('Permission denied', 'NotAllowedError');
    const track = {readyState: 'live', enabled: true, stop() { this.readyState = 'ended'; }};
    window.testAudio.tracks.push(track);
    if (window.delayMic) await new Promise(resolve => { window.releaseMic = resolve; });
    return {getTracks: () => [track], getAudioTracks: () => [track]};
  }});
  window.fireMic = () => window.testAudio.processors.at(-1)?.onaudioprocess?.({inputBuffer: {getChannelData: () => new Float32Array(4096)}});
});
await page.route('https://cdn.jsdelivr.net/npm/@google/genai@2.25.0/+esm', route => route.fulfill({contentType: 'text/javascript', body: `
  export const Modality = {AUDIO: 'AUDIO'};
  export class GoogleGenAI {
    live = {connect: async options => {
      const session = {options, sent: [], closed: false,
        sendRealtimeInput(data) { this.sent.push(data); },
        close() { this.closed = true; options.callbacks.onclose({reason: 'closed'}); }};
      window.testLive.sessions.push(session);
      options.callbacks.onopen();
      return session;
    }};
  }` }));
await page.route('**/api/live_session.php', async route => {
  requests.push(route.request().postDataJSON());
  await route.fulfill({json: {ok: true, token: 'test-token', model: 'mock-live', system_instruction: 'MOCK INSTRUCTION'}});
});
await page.route('https://ontrackegy.com/**', route => route.abort());
await page.goto(base);
await page.locator('#callBtn').click();
assert.equal(await page.locator('#missionDialog').evaluate(el => el.open), true);
await page.locator('#exampleMissionBtn').click();
assert.equal(await page.locator('#missionForm textarea').count(), 1);
assert.equal(await page.locator('#missionForm input').count(), 0);
assert.match(await page.locator('[name=brief]').inputValue(), /اسمك عمر/);
await page.locator('[name=brief]').fill('   ');
await page.locator('#missionForm button[type=submit]').click();
assert.equal(await page.locator('#missionDialog').evaluate(el => el.open), true);
assert.match(await page.locator('#missionError').textContent(), /تفاصيل المهمة/);
const brief = 'اسمك كريم، مساعد مبيعات لشركة تجريبية.\nالعميل أحمد، اعرض شقة تجريبية بـ4500000 جنيه.\nأقل سعر 4300000 جنيه سري. اتكلم بالمصري. <img src=x onerror=alert(1)>';
await page.locator('[name=brief]').fill(brief);
await page.locator('#missionForm button[type=submit]').click();
assert.equal(await page.locator('#missionDialog').evaluate(el => el.open), false);
await page.locator('#callBtn').click();
await page.waitForFunction(() => !document.querySelector('#armCallBtn').disabled);
assert.equal(requests[0].mode, 'outbound');
assert.deepEqual(requests[0].mission, {brief});
assert.equal(await page.locator('#callMode').isDisabled(), true);
await page.evaluate(() => {
  window.fireMic();
  window.testLive.sessions[0].options.callbacks.onmessage({serverContent: {outputTranscription: {text: 'unsolicited'}, modelTurn: {parts: [{inlineData: {data: 'AAAA', mimeType: 'audio/pcm;rate=24000'}}]}}});
});
assert.equal(await page.evaluate(() => window.testLive.sessions[0].sent.length), 0);
assert.equal(await page.evaluate(() => window.testAudio.played), 0);
assert.equal(await page.locator('#chatPopup').evaluate(el => el.classList.contains('is-open')), false);
await page.locator('#armCallBtn').click();
await page.evaluate(() => window.fireMic());
assert.equal(await page.evaluate(() => window.testLive.sessions[0].sent.filter(x => x.audio).length), 1);
assert.equal(await page.evaluate(() => window.testLive.sessions[0].sent.some(x => x.text)), false);
await page.evaluate(() => window.testLive.sessions[0].options.callbacks.onmessage({serverContent: {
  modelTurn: {parts: [{inlineData: {data: 'AAAA', mimeType: 'audio/pcm;rate=24000'}}]},
  outputTranscription: {text: 'أنا كريم، مساعد المبيعات'}, turnComplete: true,
}}));
await page.waitForFunction(() => document.querySelector('#orbText').textContent.includes('منتظر'));
assert.equal(await page.evaluate(() => window.testAudio.played), 0);
await page.evaluate(() => window.testLive.sessions[0].options.callbacks.onmessage({serverContent: {inputTranscription: {text: 'ألو مين معايا؟'}}}));
await page.waitForFunction(() => window.testAudio.played === 1);
assert.equal(await page.locator('.msg.bot small').textContent(), 'الإيجنت');
assert.equal(await page.locator('.msg.user small').textContent(), 'العميل');
assert.equal(await page.locator('.msg img').count(), 0);
assert.equal(await page.locator('.message-links').count(), 0);
await page.locator('#muteBtn').click();
await page.evaluate(() => window.fireMic());
assert.equal(await page.evaluate(() => window.testLive.sessions[0].sent.filter(x => x.audio).length), 1);
await page.locator('#muteBtn').click();
await page.evaluate(() => window.fireMic());
assert.equal(await page.evaluate(() => window.testLive.sessions[0].sent.filter(x => x.audio).length), 2);
await page.evaluate(() => window.testLive.sessions[0].options.callbacks.onmessage({serverContent: {interrupted: true}}));
await page.waitForFunction(() => window.testAudio.stopped > 0);

// A spoken farewell is a model turn, not an instruction to close the session.
await page.evaluate(() => window.testLive.sessions[0].options.callbacks.onmessage({serverContent: {
  outputTranscription: {text: 'مع السلامة'}, turnComplete: true,
}}));
assert.equal(await page.evaluate(() => window.testLive.sessions[0].closed), false);
assert.equal(await page.evaluate(() => window.testAudio.tracks[0].readyState), 'live');
await page.evaluate(() => {
  window.fireMic();
  window.testLive.sessions[0].options.callbacks.onmessage({serverContent: {
    inputTranscription: {text: 'استنى يا عم، أنا لسه بتكلم'},
    outputTranscription: {text: 'اتفضل، سامعك'}, turnComplete: true,
  }});
});
assert.equal(await page.evaluate(() => window.testLive.sessions[0].sent.filter(x => x.audio).length), 3);
assert.match(await page.locator('.msg.bot').last().textContent(), /اتفضل، سامعك/);
assert.equal(await page.evaluate(() => window.testLive.sessions[0].closed), false);
await page.locator('#stopBtn').click();
await page.waitForFunction(() => !document.querySelector('#callBtn').disabled);
assert.equal(await page.evaluate(() => window.testLive.sessions[0].closed), true);
assert.equal(await page.evaluate(() => window.testAudio.tracks[0].readyState), 'ended');
await page.locator('#chatReopenBtn').click();
assert.match(await page.locator('#chatCallState').textContent(), /متوقف/);
await page.locator('#chatCloseBtn').click();
await page.locator('#editMissionBtn').click();
assert.equal(await page.locator('[name=brief]').inputValue(), brief);
await page.locator('#closeMissionBtn').click();

// Old callbacks cannot contaminate the next customer's call.
await page.locator('#callBtn').click();
await page.waitForFunction(() => !document.querySelector('#armCallBtn').disabled);
await page.evaluate(() => window.testLive.sessions[0].options.callbacks.onmessage({serverContent: {inputTranscription: {text: 'stale'}, outputTranscription: {text: 'stale'}}}));
assert.equal(await page.locator('.msg').count(), 0);
await page.locator('#stopBtn').click();
await page.waitForFunction(() => !document.querySelector('#callBtn').disabled);

// Permission denial cleans up; stopping while permission is pending also stops
// any eventually acquired track rather than resurrecting a cancelled session.
await page.evaluate(() => { window.denyMic = true; });
await page.locator('#callBtn').click();
await page.waitForFunction(() => !document.querySelector('#callBtn').disabled);
assert.match(await page.locator('#callError').textContent(), /اسمح/);
assert.equal(await page.evaluate(() => window.testLive.sessions.at(-1).closed), true);
await page.evaluate(() => { window.denyMic = false; window.delayMic = true; });
await page.locator('#callBtn').click();
await page.waitForFunction(() => !!window.releaseMic);
await page.locator('#stopBtn').click();
await page.waitForFunction(() => !document.querySelector('#callBtn').disabled);
await page.evaluate(() => { window.releaseMic(); window.delayMic = false; });
await page.waitForFunction(() => window.testAudio.tracks.at(-1).readyState === 'ended');
assert.equal(await page.locator('#armCallBtn').isVisible(), false);

// The legacy demo still sends its opening and opens the mic after that turn.
await page.locator('#callMode').selectOption('demo');
await page.locator('#callBtn').click();
await page.waitForFunction(() => window.testLive.sessions.at(-1).sent.some(x => x.text));
assert.equal(requests.at(-1).mode, 'demo');
assert.equal(Object.hasOwn(requests.at(-1), 'mission'), false);
await page.evaluate(() => window.testLive.sessions.at(-1).options.callbacks.onmessage({serverContent: {outputTranscription: {text: 'أهلاً من أون تراك'}, turnComplete: true}}));
await page.waitForFunction(() => document.querySelector('#micState').textContent === 'شغال');
await page.locator('#popupStopBtn').click();
await page.waitForFunction(() => !document.querySelector('#callBtn').disabled);

// One text box and its approval button fit within laptop and phone viewports.
for (const viewport of [{width: 1365, height: 768}, {width: 390, height: 844}, {width: 390, height: 600}]) {
  await page.setViewportSize(viewport);
  await page.locator('#callMode').selectOption('outbound');
  const bounds = await page.locator('#callBtn').boundingBox();
  assert.ok(bounds.y >= 0 && bounds.y + bounds.height <= viewport.height);
  await page.locator('#editMissionBtn').click();
  const approveBounds = await page.locator('#missionForm button[type=submit]').boundingBox();
  assert.ok(approveBounds.y >= 0 && approveBounds.y + approveBounds.height <= viewport.height);
  assert.equal(await page.locator('#missionForm textarea').count(), 1);
  if (process.env.VOICE_TEST_SCREENSHOT && viewport.width === 1365) {
    await page.screenshot({path: process.env.VOICE_TEST_SCREENSHOT});
  }
  await page.locator('#closeMissionBtn').click();
  await page.locator('#callBtn').click();
  await page.waitForFunction(() => !document.querySelector('#armCallBtn').disabled);
  const armBounds = await page.locator('#armCallBtn').boundingBox();
  assert.ok(armBounds.y >= 0 && armBounds.y + armBounds.height <= viewport.height);
  assert.equal(await page.locator('#chatPopup').evaluate(el => el.classList.contains('is-open')), false);
  await page.locator('#stopBtn').click();
  await page.waitForFunction(() => !document.querySelector('#callBtn').disabled);
}
assert.deepEqual(pageErrors, []);
console.log('Browser scenarios passed: single brief submission and editing, outbound silence and speech gate, mission lock, mute, interruption, continuity, cleanup, stale callbacks, permission denial, pending cancellation, legacy demo, responsive dialog.');
await context.close();
await browser.close();
