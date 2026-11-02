'use strict';
const fs=require('node:fs');
const assert=require('node:assert/strict');
const js=fs.readFileSync('saas/console/assets/js/flow-graph.js','utf8');
const css=fs.readFileSync('saas/console/assets/css/app.css','utf8');
for(const key of [
  "aiPanel.hidden=true",
  "const aiLauncher=button('✦'",
  "shell.append(aiLauncher,aiPanel)",
  "aiLauncher.setAttribute('aria-expanded'",
  "aiPanel.addEventListener('keydown'",
  "if(event.key==='Escape')",
  "const aiThread=make('div','graph-ai-thread')",
  "aiThread.setAttribute('role','log')",
  "aiMessage('user',prompt)",
  "aiMessage('assistant'",
  "aiApply.hidden=true",
  "const aiClose=button('×'",
  "data=JSON.parse(JSON.stringify(proposal))",
])assert(js.includes(key),'Missing floating AI behavior: '+key);
assert(!js.includes('shell.append(aiPanel);'),'AI panel must not stack below canvas');
for(const key of [
  '.graph-ai-panel[hidden]{display:none!important}',
  '.graph-ai-panel{position:absolute;',
  '.graph-ai-launcher{position:absolute;',
  '.graph-ai-thread{flex:1;',
  '.graph-shell:fullscreen .graph-layout',
  '.graph-shell.graph-maximized .graph-layout',
  'height:clamp(590px,76vh,930px)',
  '@media(max-width:620px)'
])assert(css.includes(key),'Missing responsive layout contract: '+key);
console.log('FLOATING_FLOW_CHAT_AND_FULLSCREEN_CANVAS_PASS');
