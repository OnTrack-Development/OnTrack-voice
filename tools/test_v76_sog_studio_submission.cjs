'use strict';
const fs=require('node:fs');
const assert=require('node:assert/strict');
const app=fs.readFileSync('saas/console/assets/js/app.js','utf8');
const flow=fs.readFileSync('saas/console/assets/js/flow-graph.js','utf8');
const views=fs.readFileSync('saas/console/_core/Views.php','utf8');
const studio=flow.slice(flow.indexOf("const editorForm=root.closest('form')"));
assert(studio.includes("editorForm?.matches('form.agent-studio-form')"),
  'Graph editor must detect Agent Studio form');
assert(studio.includes("editorForm.addEventListener('submit',()=>store())"),
  'Graph must serialize newest nodes before shared form is submitted');
assert(studio.includes("if(editorForm&&!editorForm.matches('form.agent-studio-form'))"),
  'Graph legacy HTTP handler cannot intercept agent studio form');
assert(studio.includes("ontrack:studio-saved")&&studio.includes("ontrack:studio-failed"),
  'AI proposals require backend success/failure acknowledgment');
assert(app.includes("document.addEventListener('submit',async event=>"),
  'One central studio form submit handler expected');
assert(app.includes("new URLSearchParams(data)"),
  'Shared-host SOG save must not upload multipart to ModSecurity');
assert(app.includes("application/x-www-form-urlencoded;charset=UTF-8"),
  'SOG body needs proper Content-Type');
assert(app.includes("contentType.includes('application/json')"),
  'No HTML login or WAF page can be blindly parsed as JSON');
assert(app.includes("form.dispatchEvent(new CustomEvent('ontrack:studio-saved'"),
  'Actual successful response sends a graph AI acknowledgment');
assert(app.includes("form.dispatchEvent(new CustomEvent('ontrack:studio-failed'"),
  'Failed save never consumes AI proposal');
assert(views.includes("hash_file('sha256',__DIR__.'/../assets/js/flow-graph.js')"),
  'Cache key must invalidate old Flow graph upload logic on SOG domain');
const graphSave=studio.indexOf("const response=await fetch(editorForm.getAttribute('action')");
const graphNext=studio.indexOf("if(!response.ok",graphSave);
assert(graphSave>=0 && graphNext>graphSave,'Legacy standalone graph send exists');
assert(!studio.slice(graphSave,graphNext).includes('response.json()'),
  'Legacy editor must reject non-JSON HTTP responses gracefully');
console.log('V76_SOG_AGENT_STUDIO_SINGLE_SAVE_RESPONSE_AND_CACHE_PASS');
