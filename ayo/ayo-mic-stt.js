/**
 * AYO microphone turn detector: actual mic volume -> MediaRecorder Opus ->
 * Inworld synchronous Arabic transcription -> text sent to external LLM.
 * Browser SpeechRecognition is not required. No API key reaches the browser.
 */
export function createMicTurns({ stream, analyser, csrf, onText, onStatus, onError }) {
  const format = ['audio/webm;codecs=opus','audio/webm','audio/ogg;codecs=opus']
    .find(x => MediaRecorder.isTypeSupported(x));
  if (!format) throw new Error('المتصفح لا يدعم تسجيل Opus، استخدم Chrome أو Firefox');
  const encoding = format.includes('ogg') ? 'OGG_OPUS' : 'WEBM';
  const signal = new Uint8Array(analyser.fftSize);
  let active=false, recorder=null, listening=false, busy=false, startMs=0;
  let lastVoiceAt=0, quiet=0, timer=null, chunks=[], threshold=.022, noise=.012;
  let status='idle';
  const say = (s) => { if (s !== status) {status=s;onStatus?.(s);} };
  const volume = () => {
    analyser.getByteTimeDomainData(signal);let sum=0;
    for(let i=0;i<signal.length;i+=4){const v=(signal[i]-128)/128;sum+=v*v;}
    return Math.sqrt(sum/(signal.length/4));
  };
  async function submit(blob) {
    if (!active || blob.size < 800) return;
    if (blob.size > 680000) {onError?.('التسجيل تجاوز الحد، حاول بجملة أقصر');return;}
    busy=true; say('transcribing');
    try {
      const buffer=new Uint8Array(await blob.arrayBuffer());let binary='';
      for(let i=0;i<buffer.length;i+=8192) binary+=String.fromCharCode(...buffer.subarray(i,i+8192));
      const response=await fetch('api.php?action=transcribe',{
        method:'POST',headers:{'Content-Type':'application/json','X-AYO-CSRF':csrf()},
        body:JSON.stringify({encoding,audio:btoa(binary)})
      });
      const data=await response.json().catch(()=>({error:'استجابة غير صالحة من STT'}));
      if(!response.ok)throw new Error(data.error||'تعذر فهم الصوت');
      const phrase=(data.transcript||'').trim();
      if(phrase&&active){say('recognized');await onText(phrase);}
      else if(active)say('no-speech');
    }catch(err){if(active)onError?.(err.message||'خطأ في التفريغ الصوتي');}
    finally{busy=false;if(active&&listening)say('listening');}
  }
  function startRecording() {
    if (!active || !listening || recorder || busy) return;
    try {
      chunks=[];
      recorder=new MediaRecorder(stream,{mimeType:format,audioBitsPerSecond:32000});
      const current=recorder;
      current.ondataavailable=e=>{if(e.data?.size)chunks.push(e.data)};
      current.onstop=()=>{
        recorder=null;
        const blob=new Blob(chunks,{type:format});chunks=[];
        if(active)submit(blob);
      };
      current.onerror=()=>{onError?.('فشل تسجيل الصوت من الميكروفون')};
      startMs=performance.now();lastVoiceAt=startMs;current.start(250);
      say('recording');
    }catch(e){recorder=null;onError?.('تعذر بدء التسجيل: '+e.message);}
  }
  function finishRecording() {
    const current=recorder;
    if(current?.state==='recording') current.stop();
  }
  function tick(){
    if(!active||!listening||busy)return;
    const level=volume(),now=performance.now();
    if(!recorder){
      if(level<.024)noise=noise*.97+level*.03;
      const gate=Math.max(.018, Math.min(.06, noise*2.7));
      if(level>gate)startRecording();
    }else{
      const gate=Math.max(.014, Math.min(.045, noise*2.1));
      if(level>gate)lastVoiceAt=now;
      if(now-startMs>15500 || (now-startMs>500&&now-lastVoiceAt>850)) finishRecording();
    }
  }
  timer=setInterval(tick,75);
  return {
    resume(){active=true;listening=true;say('listening');},
    pause(){listening=false;finishRecording();say('paused');},
    stop(){active=false;listening=false;finishRecording();clearInterval(timer);timer=null;say('idle');},
    get isProcessing(){return busy;}
  };
}
