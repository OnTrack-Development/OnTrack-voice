/**
 * AYO mic turns: AudioWorklet PCM with pre-roll and adaptive noise gate.
 * 16k mono LINEAR16 -> Inworld STT sync. Never forward low-energy silence.
 * No API keys leave the PHP host.
 */
export function createMicTurns({stream,csrf,onText,onStatus,onError}) {
  const Context=window.AudioContext||window.webkitAudioContext;
  if(!Context||!window.AudioWorkletNode)throw Error('محتاج Chrome حديث يدعم AudioWorklet لتحسين دقة الكلام');
  const ctx=new Context({sampleRate:16000,latencyHint:'interactive'});
  let source=null,processor=null,mute=null,active=false,listening=false,busy=false,ready=false,closed=false;
  let status='idle',noise=.006,pre=[],audio=[],voiceMs=0,lastVoiceMs=0,startMs=0,clockMs=0;
  const say=s=>{if(status!==s){status=s;onStatus?.(s)}};
  const reset=()=>{audio=[];voiceMs=0;lastVoiceMs=0;startMs=0;};
  const voiceLevel=raw=>{
    const d=new DataView(raw.buffer,raw.byteOffset,raw.byteLength);let total=0,count=0;
    for(let i=0;i+1<raw.length;i+=8){const v=d.getInt16(i,true)/32768;total+=v*v;count++}
    return Math.sqrt(total/Math.max(1,count));
  };
  const sizeMs=raw=>Math.round(raw.byteLength/2/ctx.sampleRate*1000);
  const base64=raw=>{
    let b='';
    for(let i=0;i<raw.length;i+=8192)b+=String.fromCharCode(...raw.subarray(i,i+8192));
    return btoa(b);
  };
  async function transcribe(parts,durationMs){
    if(!active||!listening||busy||!parts.length)return;
    busy=true;say('transcribing');
    try{
      const total=parts.reduce((n,p)=>n+p.length,0);
      if(total>850000)throw Error('الجملة طويلة؛ جرّب توقف بين الجمل');
      const bytes=new Uint8Array(total);let offset=0;
      for(const part of parts){bytes.set(part,offset);offset+=part.length}
      const r=await fetch('api.php?action=transcribe',{
        method:'POST',headers:{'Content-Type':'application/json','X-AYO-CSRF':csrf()},
        body:JSON.stringify({encoding:'LINEAR16',sampleRate:ctx.sampleRate,durationMs,audio:base64(bytes)})
      });
      const data=await r.json().catch(()=>({error:'استجابة STT غير صالحة'}));
      if(!r.ok)throw Error(data.error||'تعذر فهم الصوت');
      const text=(data.transcript||'').trim();
      if(!active||!listening)return;
      if(data.suspect||!data.hasText||!text){say('no-speech');return}
      say('recognized');await onText(text);
    }catch(e){if(active)onError?.(e.message||'فشل التفريغ')}
    finally{busy=false;if(active&&listening)say('listening')}
  }
  function accept(buffer){
    if(!active||!listening||busy||closed)return;
    const frame=new Uint8Array(buffer);
    if(frame.length<32)return;
    const rms=voiceLevel(frame),ms=sizeMs(frame),now=performance.now();
    const gate=Math.min(.075,Math.max(.014,noise*2.6));
    if(startMs===0){
      if(rms<gate){noise=noise*.98+rms*.02;pre.push(frame);if(pre.length>6)pre.shift();return}
      startMs=now;clockMs=now;lastVoiceMs=now;voiceMs=ms;
      audio=[...pre,frame];pre=[];
      say('recording');return;
    }
    audio.push(frame);
    if(rms>gate){voiceMs+=ms;lastVoiceMs=now}
    if(now-startMs>12500||(now-startMs>650&&now-lastVoiceMs>950)){
      const parts=audio,duration=Math.round(parts.reduce((n,p)=>n+p.length,0)/2/ctx.sampleRate*1000);
      reset();pre=[];
      // Avoid STT hallucinations on fan noise, clicks, and very short bursts.
      const voiced=parts.length?parts.reduce((n,p)=>n+(voiceLevel(p)>.014?sizeMs(p):0),0):0;
      if(duration<600||voiced<350){say('no-speech');return}
      transcribe(parts,duration);
    }
  }
  const initializing=(async()=>{
    try{
      await ctx.audioWorklet.addModule('audio-worklet.js');
      if(closed)return;
      source=ctx.createMediaStreamSource(stream);
      processor=new AudioWorkletNode(ctx,'ayo-pcm-capture');
      mute=ctx.createGain();mute.gain.value=0;
      source.connect(processor).connect(mute).connect(ctx.destination);
      processor.port.onmessage=e=>accept(e.data);
      ready=true;if(listening)say('listening');
    }catch(e){onError?.('تعذر تشغيل PCM: '+e.message);throw e}
  })();
  return {
    resume(){active=true;listening=true;initializing.then(()=>{ctx.resume().catch(e=>onError?.(e.message));if(ready)say('listening')}).catch(()=>{});},
    pause(){listening=false;reset();pre=[];say('paused');},
    stop(){active=false;listening=false;closed=true;reset();pre=[];try{processor?.disconnect();source?.disconnect();mute?.disconnect();}catch{}ctx.close().catch(()=>{});say('idle');},
    get isProcessing(){return busy},
    get sampleRate(){return ctx.sampleRate}
  };
}
