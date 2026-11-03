#!/usr/bin/env node
'use strict';
const fs=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const files=[
  'saas/mobile/ota/ui.html',
  'saas/mobile/audio-proof/overlay/app/src/main/assets/ontrack-ui.html'
];
for(const filename of files){
  const html=fs.readFileSync(filename,'utf8');
  const scripts=[...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)];
  assert(scripts.length>0,filename+' must include inline JS');
  for(const script of scripts)new vm.Script(script[1],{filename});
  for(const label of ["تقرير المكالمة","ملخص AI من التفريغ","ملاحظات العميل","نتيجة المكالمة"]){
    assert(html.includes(label),filename+' missing '+label);
  }
  assert(html.includes('item.textContent=title+\': \'+value;'),'untrusted call text must render with textContent');
  assert(html.includes('onTrackCalls'),'history callback missing');
  assert(!html.includes('eval('),'no dynamic code');
  console.log('PASS',filename,'script syntax and report UI');
}
