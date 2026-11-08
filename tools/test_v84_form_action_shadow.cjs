'use strict';
/** Regression for actual production 404:
 * POST /saas/console/[object%20HTMLInputElement] from four SOG events.
 */
const fs=require('node:fs');
const assert=require('node:assert/strict');
const app=fs.readFileSync('saas/console/assets/js/app.js','utf8');
const graph=fs.readFileSync('saas/console/assets/js/flow-graph.js','utf8');
const views=fs.readFileSync('saas/console/_core/Views.php','utf8');
const base='https://app.ontrackegy.com/saas/console/?tab=agents';
const input={toString(){return '[object HTMLInputElement]'}};
function fakeForm(action='/'){
  return {action:input,getAttribute(name){return name==='action'?action:null}};
}
assert(new URL(fakeForm().action,base).pathname.includes('[object%20HTMLInputElement]'),
  'Test fixture must prove the original shadowed form.action 404');
assert.equal(new URL(fakeForm('/').getAttribute('action'),base).pathname,'/');
assert.equal(new URL(fakeForm('/portal/acc-b65a42203a7c/').getAttribute('action'),base).pathname,
  '/portal/acc-b65a42203a7c/');
assert.equal(new URL(fakeForm(null).getAttribute('action')||'/saas/console/',base).pathname,
  '/saas/console/');
assert(!/\b(?:form|editorForm)\.action\b/.test(app+graph),
  'Never use form.action property: hidden input named action shadows it');
assert(!/\bid\.form\.action\b/.test(graph),
  'AI chat sub-form has the same shadowed action bug');
assert(app.includes("fetch(form.getAttribute('action')||location.pathname"),
  'Studio POST must read the attribute, never a named element');
assert(app.includes("new URL(form.getAttribute('action')||location.href"),
  'Other AJAX forms need the same fix');
assert(graph.includes("fetch(editorForm.getAttribute('action')||location.pathname"),
  'Standalone Flow Builder must use action attribute');
assert(views.includes("action=\"'.e(white_label_url('/')).'\""),
  'SOG Studio must have a canonical action URL, not current tab');
console.log('V84_SOG_HIDDEN_ACTION_SHADOW_AND_POST_ROUTE_FIX_PASS');
