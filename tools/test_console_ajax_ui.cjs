'use strict';
const fs=require('node:fs');
const assert=require('node:assert/strict');
const app=fs.readFileSync('saas/console/assets/js/app.js','utf8');
const flow=fs.readFileSync('saas/console/assets/js/flow-graph.js','utf8');
const php=fs.readFileSync('saas/console/_core/Actions.php','utf8');
const css=fs.readFileSync('saas/console/assets/css/app.css','utf8');
const views=fs.readFileSync('saas/console/_core/Views.php','utf8');
for(const marker of [
  "window.ontrackConfirm=(message",
  "event.stopImmediatePropagation()",
  "window.ontrackToast=(message",
  "window.ontrackSoftNavigate=async",
  "history.pushState({otAjax:true}",
  "addEventListener('popstate'",
  "refreshSections(html,finalUrl.href,true)",
  "window.ontrackMountFlowEditors?.(main)",
  "window.ontrackMountPairingQR?.(main)",
  "window.ontrackMountMfaQR?.(main)",
  "const excludes=new Set(["])assert(app.includes(marker),'Missing AJAX/modal behavior: '+marker);
for(const marker of [
  "event.preventDefault();event.stopPropagation()",
  "'X-Requested-With':'XMLHttpRequest'",
  "const editorForm=root.closest('form')",
  "if(publishNow)fields.set('publish_now','1')",
  "window.ontrackConfirm('حذف الخطوة",
  "window.ontrackToast('JSON غير صالح'",
  "chatRequest('flow_ai_history',{current_graph_json:JSON.stringify(data)})",
  "payload.current_graph_read===true",
  "aiModel.textContent=payload.model",
])assert(flow.includes(marker),'Missing flow continuity/async save behavior: '+marker);
for(const marker of [
  "const nextNavigation=parsed.querySelector('.sidebar .nav-list')",
  "oldNavigation.replaceWith(nextNavigation)",
  "nav?.addEventListener('click',event=>",
  "group.getAttribute('data-nav-group')",
])assert(app.includes(marker),'Navigation permission/feature refresh missing: '+marker);
assert(views.includes('data-nav-group'), 'Sidebar groups must be stable across AJAX refresh');
assert(views.includes("form_start('voice_knowledge_reindex'"),
  'Knowledge UI must provide authorized reindex action');
assert(app.includes("'switch','set_view','support_enter'"),'Role switching must bypass AJAX');
assert(views.includes('data-no-ajax="1"'),'Role switch forms must force native full-page navigation');
for(const marker of ["HTTP_X_REQUESTED_WITH", "json_encode(['ok'=>true,'message'","json_encode(['ok'=>false,'error'"])
  assert(php.includes(marker),'Missing AJAX server response '+marker);
assert(!/\b(?:alert|confirm|prompt)\s*\(/.test(flow),'No browser modal in Flow Builder');
assert(!/onclick="return confirm\(/.test(views),'No native confirmation in the console templates');
assert(css.includes('.ot-confirm-overlay{'),'Branded confirmation styling');
console.log('CONSOLE_AJAX_MODAL_FLOW_CONTINUITY_PASS');
