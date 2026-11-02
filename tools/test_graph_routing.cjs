'use strict';
const fs=require('node:fs'),assert=require('node:assert/strict');
const route=require('../saas/console/assets/js/graph-routing.js');
const canvas=fs.readFileSync('saas/console/assets/js/flow-graph.js','utf8');
const view=fs.readFileSync('saas/console/_core/Views.php','utf8');
const css=fs.readFileSync('saas/console/assets/css/app.css','utf8');
assert(view.includes('graph-routing.js?v='),'routing script included');
assert(view.indexOf('graph-routing.js?v=')<view.indexOf('flow-graph.js?v='),'router loaded before canvas');
assert(canvas.includes('routing.geometry('),'canvas must use orthogonal router');
assert(canvas.includes('routing.layout('),'canvas must use layered layout');
assert(canvas.includes('edgeFocus'),'focus mode exists');
assert(canvas.includes('fitGraph()'),'fit-to-screen mode exists');
assert(!canvas.includes("C'+x1"),'old uncontrolled cubic lines removed');
assert(css.includes('.graph-connector.is-focused'),'focused edge styling exists');
assert(css.includes('.graph-node::before'),'node input/output ports rendered');

const nodes=Array.from({length:28},(_,i)=>({
  id:'n'+i,type:i===0?'start':i===27?'end':'condition',text:'node '+i,x:0,y:0
}));
const edges=[];
for(let i=0;i<27;i++)edges.push({from:'n'+i,to:'n'+(i+1),label:'مسار عادي'});
for(let i=0;i<25;i++)edges.push({from:'n'+i,to:'n'+(i+2),label:'مسار فرعي'});
edges.push({from:'n20',to:'n6',label:'الرجوع للسياق'});
edges.push({from:'n17',to:'n4',label:'سؤال جانبي'});
const geometry=route.layout(nodes,edges);
assert.equal(Object.keys(geometry.positions).length,28);
const laid=nodes.map(n=>({...n,...geometry.positions[n.id]}));
for(let i=0;i<laid.length;i++){
  for(let j=i+1;j<laid.length;j++){
    const a=laid[i],b=laid[j];
    assert(a.x+route.WIDTH<=b.x||b.x+route.WIDTH<=a.x||
      a.y+route.HEIGHT<=b.y||b.y+route.HEIGHT<=a.y,
      'node boxes overlap: '+a.id+' '+b.id);
  }
}
const routed=route.geometry(laid,edges,{bottom:geometry.height-320});
assert.equal(routed.length,edges.length,'must retain all semantic edges');
const nodeById=new Map(laid.map(n=>[n.id,n]));
for(const r of routed){
  const a=nodeById.get(r.edge.from),b=nodeById.get(r.edge.to);
  assert.equal(r.from[0],a.x+route.WIDTH,'edge exits from RIGHT port');
  assert.equal(r.to[0],b.x,'edge enters LEFT port');
  assert(r.from[1]>a.y&&r.from[1]<a.y+route.HEIGHT,'output on node edge');
  assert(r.to[1]>b.y&&r.to[1]<b.y+route.HEIGHT,'input on node edge');
  assert(!r.d.includes(' C '),'no cubic spaghetti');
  assert(r.d.includes(' Q '),'rounded orthogonal turn expected');
  // The path control points are Manhattan-only. Diagonal movement is
  // produced solely by small quadratic corner smoothing.
  for(let i=1;i<r.points.length;i++){
    const p=r.points[i-1],q=r.points[i];
    assert(p[0]===q[0]||p[1]===q[1],'non-orthogonal line');
  }
  if(r.kind==='rail'){
    const rail=r.points[2][1];
    assert(rail<Math.min(...laid.map(n=>n.y))||
      rail>Math.max(...laid.map(n=>n.y+route.HEIGHT)),
      'overhead or lower rail must be outside all node boxes');
  }
}
const relationships=route.focusRelation(edges,'n4');
assert(relationships.focused.size>0&&relationships.focused.size<edges.length,
  'selected node highlights only adjacent edges');
assert(relationships.related.has('n4'),'selected is related');
assert(relationships.related.has('n5'),'direct successor highlighted');
assert(!relationships.related.has('n26'),'unrelated nodes dim');
assert.equal(route.geometry(laid,edges).length,edges.length,'geometry deterministic');
console.log('N8N_STYLE_ORTHOGONAL_ROUTING_28_NODE_54_EDGE_PASS');
