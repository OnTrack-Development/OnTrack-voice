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
assert(canvas.includes('const muted=hovered!==null&&!active'),
  'unrelated edges fade ONLY during actual pointer hover');
assert(canvas.includes('routing.focusRelation(data.edges,null,hovered)'),
  'selected node must never dim default links');
assert(!canvas.includes('edgeFocus'),'no default focus mode hides all other links');
assert(css.includes('stroke:var(--accent);stroke-width:1.85;stroke-opacity:.88'),
  'all graph links are red by default');
assert(css.includes('.graph-connector.is-muted{opacity:.018'),
  'hover filters non-connected links');
assert(canvas.includes("box.classList.remove('is-muted','is-neighbor')"),
  'hover must not fade unrelated nodes');
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
assert(geometry.columns<=7,'compact layout caps depth to seven columns');
assert(geometry.width<=2600,'compact layout does not create huge horizontal gaps');
assert(geometry.height<=1550,'compact layout does not create huge vertical gaps');
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
  const turns=r.points.some((p,i)=>i>0&&i<r.points.length-1&&
    (r.points[i-1][0]!==r.points[i+1][0])&&
    (r.points[i-1][1]!==r.points[i+1][1]));
  if(turns)assert(r.d.includes(' Q '),'rounded orthogonal turn expected');
  // The path control points are Manhattan-only. Diagonal movement is
  // produced solely by small quadratic corner smoothing.
  for(let i=1;i<r.points.length;i++){
    const p=r.points[i-1],q=r.points[i];
    assert(p[0]===q[0]||p[1]===q[1],'non-orthogonal line');
  }
  // Long routes must not cut through any unrelated node rectangle.
  for(let i=1;i<r.points.length;i++){
    const p=r.points[i-1],q=r.points[i];
    for(const n of laid){
      if(n.id===r.edge.from||n.id===r.edge.to)continue;
      const left=n.x,top=n.y,right=n.x+route.WIDTH,bottom=n.y+route.HEIGHT;
      const crosses=p[0]===q[0]
        ?p[0]>left&&p[0]<right&&Math.max(p[1],q[1])>top&&Math.min(p[1],q[1])<bottom
        :p[1]>top&&p[1]<bottom&&Math.max(p[0],q[0])>left&&Math.min(p[0],q[0])<right;
      assert(!crosses,'edge '+r.edge.from+'->'+r.edge.to+' crosses node '+n.id);
    }
  }
  if(r.kind==='rail'){
    const rail=r.points[2][1];
    const lo=Math.min(a.x+route.WIDTH,b.x),hi=Math.max(a.x+route.WIDTH,b.x);
    const involved=laid.filter(n=>n.id===a.id||n.id===b.id||
      (n.x<hi&&n.x+route.WIDTH>lo));
    assert(rail<Math.min(...involved.map(n=>n.y))||
      rail>Math.max(...involved.map(n=>n.y+route.HEIGHT)),
      'short local rail must avoid nodes between endpoints');
    assert(rail<geometry.height+150,'local feedback rail cannot detour far below canvas');
  }
}
const relationships=route.focusRelation(edges,'n4');
assert(relationships.focused.size>0&&relationships.focused.size<edges.length,
  'selected node highlights only adjacent edges');
assert(relationships.related.has('n4'),'selected is related');
assert(relationships.related.has('n5'),'direct successor highlighted');
assert(!relationships.related.has('n26'),'unrelated nodes dim');
assert.equal(route.geometry(laid,edges).length,edges.length,'geometry deterministic');
console.log('N8N_RED_HOVER_ONLY_COMPACT_ROUTING_28_NODE_54_EDGE_PASS');
