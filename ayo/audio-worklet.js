class AyoPcmCapture extends AudioWorkletProcessor {
  constructor(){super();this.buffer=[];this.target=Math.max(1024,Math.round(sampleRate/10));this.buffered=0;this.port.onmessage=(e)=>{if(e.data==='flush')this.flush();};}
  flush(){if(!this.buffered)return;const pcm=new Int16Array(this.buffered);let i=0;for(const ch of this.buffer){pcm.set(ch,i);i+=ch.length;}this.buffer=[];this.buffered=0;this.port.postMessage(pcm.buffer,[pcm.buffer]);}
  process(inputs){const first=inputs[0]?.[0];if(first){const chunk=new Int16Array(first.length);for(let i=0;i<first.length;i++){const s=Math.max(-1,Math.min(1,first[i]));chunk[i]=s<0?s*32768:s*32767;}this.buffer.push(chunk);this.buffered+=chunk.length;if(this.buffered>=this.target)this.flush();}return true;}
}
registerProcessor('ayo-pcm-capture',AyoPcmCapture);
