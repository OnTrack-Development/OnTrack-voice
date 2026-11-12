'use strict';
const fs = require('node:fs');
const assert = require('node:assert/strict');

const views = fs.readFileSync('saas/console/_core/Views.php', 'utf8');
const actions = fs.readFileSync('saas/console/_core/Actions.php', 'utf8');
const appJs = fs.readFileSync('saas/console/assets/js/app.js', 'utf8');
const appCss = fs.readFileSync('saas/console/assets/css/app.css', 'utf8');
const tenantSession = fs.readFileSync('saas/console/_core/TenantVoiceSession.php', 'utf8');
const pilotSession = fs.readFileSync('saas/mobile/server/session.php', 'utf8');
const deployPy = fs.readFileSync('tools/deploy_saas_console.py', 'utf8');
const otaRelease = JSON.parse(fs.readFileSync('saas/mobile/ota/release.json', 'utf8'));
const nativeRelease = JSON.parse(fs.readFileSync('saas/mobile/ota/native_release.json', 'utf8'));

for (const marker of [
  'function agent_voice_picker_html(',
  'function voice_sample_preview_control(',
  'data-voice-pick-card',
  'data-voice-search',
  'data-voice-filter',
  'data-voice-preview',
  "form_start('sync_fish_voices'",
  "form_start('add_fish_voice'",
  "select_html('الصوت المستخدم لهذا المساعد','voice'",
]) {
  assert(views.includes(marker), 'Views.php missing marker: ' + marker);
}
assert(!/onclick="return confirm\(/.test(views), 'Views.php must not contain inline confirm');

for (const marker of [
  "case 'sync_fish_voices':",
  "case 'add_fish_voice':",
  "case 'preview_voice':",
  'HTTP_X_REQUESTED_WITH',
  "json_encode(['ok'=>true,'message'",
  "json_encode(['ok'=>false,'error'",
]) {
  assert(actions.includes(marker), 'Actions.php missing marker: ' + marker);
}

for (const marker of [
  'filterVoicePicker',
  '[data-voice-preview]',
  'preview_voice',
  'مرحبا يا حلو',
  'مرحبا يا حلوة',
  'قاعدة التكيّف الفوري مع جنس المتصل',
]) {
  assert(appJs.includes(marker), 'app.js missing marker: ' + marker);
}
assert(!/\b(?:alert|confirm|prompt)\s*\(/.test(appJs), 'app.js must not call native alert/confirm/prompt');

for (const marker of ['.voice-picker{', '.voice-picker-card{', '.voice-preview-btn{']) {
  assert(appCss.includes(marker), 'app.css missing marker: ' + marker);
}

assert(tenantSession.includes('التأنيث الدائم') && tenantSession.includes('اسم المتصل وتقييمه'),
  'TenantVoiceSession.php missing female-lock or memory rules');
assert(pilotSession.includes('التأنيث الدائم') && pilotSession.includes('اسم المتصل وتقييمه'),
  'saas/mobile/server/session.php missing female-lock or memory rules');
assert(deployPy.includes("assets/voice-samples"), 'deploy_saas_console.py must preserve voice-samples');
assert.equal(otaRelease.revision, 61, 'OTA release revision must be 61');
assert.equal(otaRelease.max_native, 61, 'OTA release max_native must be 61');
assert.equal(nativeRelease.version_code, 61, 'Native version_code must be 61');

console.log('VOICE_PICKER_AND_PERSONA_PASS');
