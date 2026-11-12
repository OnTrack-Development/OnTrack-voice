/* OnTrack Flow canvas geometry: readable left-to-right, n8n-style orthogonal paths.
 * Pure functions are shared with Node regression tests; no DOM dependencies.
 * This is view-only. It never changes the graph's semantic nodes/edges.
 */
(function(root,factory){
  const value=factory();
  if(typeof module==='object'&&module.exports)module.exports=value;
  if(root)root.OnTrackGraphRouting=value;
})(typeof window!=='undefined'?window:null,function(){
'use strict';
const WIDTH=220,HEIGHT=112;
const NODE_GAP_X=100, NODE_GAP_Y=68;
const LEFT=155,TOP=175,MAX_ROWS=6,MAX_COLS=7;
function layout(nodes,edges){
  const byId=new Map(nodes.map(n=>[String(n.id),n]));
  const next=new Map(),prior=new Map();
  for(const e of edges){
    if(!byId.has(String(e.from))||!byId.has(String(e.to)))continue;
    if(!next.has(e.from))next.set(e.from,[]);
    next.get(e.from).push(e.to);
    if(!prior.has(e.to))prior.set(e.to,[]);
    prior.get(e.to).push(e.from);
  }
  const rootNodes=nodes.filter(n=>n.type==='start');
  const queue=(rootNodes.length?rootNodes:[nodes[0]]).filter(Boolean).map(n=>String(n.id));
  const seen=new Set(),distance=new Map();
  queue.forEach(id=>distance.set(id,0));
  while(queue.length){
    const id=queue.shift();
    if(seen.has(id))continue;
    seen.add(id);
    for(const to of next.get(id)||[]){
      if(!distance.has(to))distance.set(to,(distance.get(id)||0)+1);
      if(!seen.has(to))queue.push(to);
    }
  }
  // A long chained graph and a wide fan-out both fit. Column slots are
  // bounded to seven rows, with deterministic parent-aware ordering.
  const indexes=new Map(nodes.map((n,i)=>[String(n.id),i]));
  const items=[...nodes].sort((a,b)=>{
    const da=distance.get(a.id)??1000,db=distance.get(b.id)??1000;
    return da-db||indexes.get(a.id)-indexes.get(b.id);
  });
  const columns=[];
  for(const n of items){
    const wanted=distance.get(n.id)??Math.min(columns.length,MAX_COLS-1);
    let col=Math.max(0,Math.min(wanted,MAX_COLS-1));
    // Overflow into the nearest available layer while keeping total width
    // compact, instead of assigning every logical depth a huge new column.
    while((columns[col]||[]).length>=MAX_ROWS){
      const alternative=Array.from({length:MAX_COLS},(_,i)=>i)
        .filter(i=>(columns[i]||[]).length<MAX_ROWS)
        .sort((a,b)=>Math.abs(a-wanted)-Math.abs(b-wanted)||a-b)[0];
      if(alternative===undefined){col=columns.length;break}
      col=alternative;
    }
    if(!columns[col])columns[col]=[];
    columns[col].push(n);
  }
  // Order sibling nodes using their closest already-placed parent.
  for(let col=1;col<columns.length;col++){
    const bucket=columns[col]||[];
    bucket.sort((a,b)=>{
      function score(node){
        const parents=(prior.get(node.id)||[]).filter(id=>columns.slice(0,col).some(group=>group?.some(v=>v.id===id)));
        if(!parents.length)return indexes.get(node.id);
        const matches=parents.map(id=>{
          for(let c=0;c<col;c++){
            const row=columns[c]?.findIndex(v=>v.id===id)??-1;
            if(row>=0)return row;
          }
          return 0;
        });
        return matches.reduce((x,y)=>x+y,0)/matches.length;
      }
      return score(a)-score(b)||indexes.get(a.id)-indexes.get(b.id);
    });
  }
  const positions={},colById={},rowById={};
  let maxRows=0;
  columns.forEach((bucket,col)=>{
    if(!bucket)return;
    maxRows=Math.max(maxRows,bucket.length);
    bucket.forEach((n,row)=>{
      positions[n.id]={x:LEFT+col*(WIDTH+NODE_GAP_X),y:TOP+row*(HEIGHT+NODE_GAP_Y)};
      colById[n.id]=col;rowById[n.id]=row;
    });
  });
  const width=Math.max(1250,LEFT+columns.length*(WIDTH+NODE_GAP_X)+160);
  const height=Math.max(820,TOP+maxRows*(HEIGHT+NODE_GAP_Y)+170);
  return {positions,colById,rowById,width,height,columns:columns.length,rows:maxRows};
}
function rounded(points,radius=12){
  if(points.length<2)return '';
  const fmt=n=>Number(n.toFixed(2));
  let d='M '+fmt(points[0][0])+' '+fmt(points[0][1]);
  for(let i=1;i<points.length-1;i++){
    const a=points[i-1],p=points[i],b=points[i+1];
    const lenA=Math.hypot(p[0]-a[0],p[1]-a[1]);
    const lenB=Math.hypot(b[0]-p[0],b[1]-p[1]);
    if(lenA===0||lenB===0)continue;
    const r=Math.min(radius,lenA/2,lenB/2);
    const enter=[p[0]-(p[0]-a[0])/lenA*r,p[1]-(p[1]-a[1])/lenA*r];
    const leave=[p[0]+(b[0]-p[0])/lenB*r,p[1]+(b[1]-p[1])/lenB*r];
    d+=' L '+fmt(enter[0])+' '+fmt(enter[1])+' Q '+fmt(p[0])+' '+fmt(p[1])+' '+fmt(leave[0])+' '+fmt(leave[1]);
  }
  const last=points[points.length-1];
  return d+' L '+fmt(last[0])+' '+fmt(last[1]);
}
function makePorts(nodes,edges){
  const outgoing=new Map(),incoming=new Map();
  nodes.forEach(n=>{outgoing.set(n.id,[]);incoming.set(n.id,[])});
  edges.forEach((e,i)=>{
    if(outgoing.has(e.from)&&incoming.has(e.to)){
      outgoing.get(e.from).push({...e,index:i});
      incoming.get(e.to).push({...e,index:i});
    }
  });
  const byId=new Map(nodes.map(n=>[n.id,n]));
  const ports=new Map();
  function assign(groups,direction){
    for(const [nodeId,items] of groups){
      items.sort((a,b)=>{
        const nodeA=byId.get(direction==='out'?a.to:a.from);
        const nodeB=byId.get(direction==='out'?b.to:b.from);
        return (nodeA?.y??0)-(nodeB?.y??0)||(nodeA?.x??0)-(nodeB?.x??0)||a.index-b.index;
      });
      items.forEach((edge,slot)=>{
        const value=ports.get(edge.index)||{};
        value[direction]=HEIGHT*(slot+1)/(items.length+1);
        ports.set(edge.index,value);
      });
    }
  }
  assign(outgoing,'out');assign(incoming,'in');
  return ports;
}
function route(source,target,port,lane=0,options={}){
  const x1=source.x+WIDTH,y1=source.y+(port?.out??HEIGHT/2);
  const x2=target.x,y2=target.y+(port?.in??HEIGHT/2);
  const horizontalGap=x2-x1;
  const adjacent=horizontalGap>=65&&horizontalGap<170;
  let points,label;
  if(adjacent){
    // A single, tidy horizontal gutter between consecutive columns.
    const middle=x1+horizontalGap/2;
    points=[[x1,y1],[middle,y1],[middle,y2],[x2,y2]];
    label=[middle,Math.abs(y1-y2)<25?y1-18:(y1+y2)/2];
  }else{
    // Route long/feedback links around only the nodes that fall BETWEEN
    // these endpoints, not around the bottom of the 3000px whole canvas.
    // Both vertical segments stay in the compact 100px column gutters.
    const laneOffset=(lane%5)*13;
    const minTop=options.minTop??Math.min(source.y,target.y);
    const maxBottom=options.maxBottom??Math.max(source.y+HEIGHT,target.y+HEIGHT);
    const above=minTop-36-laneOffset;
    const below=maxBottom+36+laneOffset;
    const rail=(Math.abs(y1-above)+Math.abs(y2-above)<=
      Math.abs(y1-below)+Math.abs(y2-below))?above:below;
    const right=x1+Math.min(40,Math.max(22,Math.abs(horizontalGap)*.2));
    const left=x2-Math.min(40,Math.max(22,Math.abs(horizontalGap)*.2));
    points=[[x1,y1],[right,y1],[right,rail],[left,rail],[left,y2],[x2,y2]];
    label=[(right+left)/2,rail-16];
  }
  return {d:rounded(points,10),points,label,kind:adjacent?'adjacent':'rail',
    from:[x1,y1],to:[x2,y2]};
}
function geometry(nodes,edges,canvas={}){
  const byId=new Map(nodes.map(n=>[n.id,n]));
  const ports=makePorts(nodes,edges);
  let lane=0;
  const routes=[];
  edges.forEach((edge,i)=>{
    const source=byId.get(edge.from),target=byId.get(edge.to);
    if(!source||!target)return;
    const left=Math.min(source.x+WIDTH,target.x);
    const right=Math.max(source.x+WIDTH,target.x);
    const intervening=nodes.filter(n=>n.id===source.id||n.id===target.id||
      (n.x<right&&n.x+WIDTH>left));
    const minTop=Math.min(...intervening.map(n=>n.y));
    const maxBottom=Math.max(...intervening.map(n=>n.y+HEIGHT));
    const forwardGap=target.x-(source.x+WIDTH);
    const nearby=forwardGap>=65&&forwardGap<170;
    const path=route(source,target,ports.get(i),nearby?0:lane++,{minTop,maxBottom});
    routes.push({...path,edge,index:i});
  });
  return routes;
}
function focusRelation(edges,selected,hovered=null){
  const active=hovered||selected;
  const related=new Set();
  if(active!==null&&active!==undefined)related.add(active);
  const focused=new Set();
  edges.forEach((e,i)=>{
    if(e.from===active||e.to===active){
      focused.add(i);related.add(e.from);related.add(e.to);
    }
  });
  return {focused,related,active};
}
return {layout,rounded,route,geometry,focusRelation,WIDTH,HEIGHT};
});
