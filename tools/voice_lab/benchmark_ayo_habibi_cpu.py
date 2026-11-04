"""Isolated AYO voice-reference CPU benchmark using official Habibi-TTS EGY Specialized.
Reference MP3s stay on the owner's PC. Transcripts are machine-generated and require review.
"""
import json
import os
import sys
import time
import wave
import pathlib
import subprocess
import traceback

ROOT=pathlib.Path(r"D:\AI")
INPUT=ROOT/"AYO Voice Demo"
LAB=ROOT/"OnTrackHabibiLab"
OUT=LAB/"output"
OUT.mkdir(parents=True,exist_ok=True)
FILES=("1.mp3","1-1.mp3","2.mp3","2-2.mp3","3.mp3","3-3.mp3")
TEXT=("أهلًا بيك في أون تراك. أنا المساعد الصوتي، وهنجرّب دلوقتي نطق عربي مصري واضح وطبيعي. لو عندك استفسار عن خدمات الاستضافة أو الدعم الفني، قولّي محتاج إيه، وهساعدك خطوة بخطوة من غير تعقيد.")
report={"reference_dir":str(INPUT),"model":"Habibi-TTS 0.1.1 Specialized EGY","device":"cpu",
"nfe_step":16,"test_text":TEXT,"samples":[],"generations":[],"started_at":time.strftime("%Y-%m-%dT%H:%M:%SZ",time.gmtime())}
def save():
    (OUT/"report.json").write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding="utf-8")
def duration_wav(p):
    with wave.open(str(p),"rb") as f:
        return round(f.getnframes()/float(f.getframerate()),3)

print("References available:",[(p,(INPUT/p).exists()) for p in FILES],flush=True)
from faster_whisper import WhisperModel
from faster_whisper.audio import decode_audio
import numpy as np
import soundfile as sf
print("Loading Whisper base ASR on CPU...",flush=True)
asr=WhisperModel("base",device="cpu",compute_type="int8",download_root=str(LAB/"asr-cache"))
for name in FILES:
    entry={"name":name}
    try:
        mp3=INPUT/name
        if not mp3.is_file(): raise FileNotFoundError(str(mp3))
        a=decode_audio(str(mp3),sampling_rate=24000)
        max_n=24000*12
        a=a[:max_n]
        if len(a)<24000*2: raise ValueError("Reference shorter than 2 sec")
        wav=OUT/(mp3.stem+"_ref.wav")
        sf.write(str(wav),a,24000,subtype="PCM_16")
        segs,_=asr.transcribe(str(wav),language="ar",beam_size=3,condition_on_previous_text=False, vad_filter=False)
        transcript=" ".join(s.text.strip() for s in segs).strip()
        if not transcript: raise ValueError("Automatic transcription empty")
        entry.update({"ref_wav":str(wav),"duration_s":round(len(a)/24000,3),
                      "ref_text_auto":transcript,"rms":round(float(np.sqrt(np.mean(a*a))),6)})
        print(f"ASR {name}: {transcript}",flush=True)
    except Exception as exc:
        entry["error"]=str(exc)
        print(f"ASR error {name}: {exc}",flush=True)
    report["samples"].append(entry)
    save()
# Select strongest transcription/duration for each numbered voice group, preserving both references.
for group in ("1","2","3"):
    samples=[v for v in report["samples"] if v["name"] in (f"{group}.mp3",f"{group}-{group}.mp3") and v.get("ref_text_auto")]
    if not samples:
        report["generations"].append({"group":group,"error":"No valid transcribed reference"})
        save()
        continue
    sample=max(samples,key=lambda x:(min(x["duration_s"],10),len(x["ref_text_auto"])))
    outfile=f"ayo_group_{group}_EGY.wav"
    args=[str(pathlib.Path(sys.executable).parent/"Scripts"/"habibi-tts_infer-cli.exe"),
          "--model","Specialized","--dialect","EGY","--device","cpu","--nfe_step","16",
          "--ref_audio",sample["ref_wav"],"--ref_text",sample["ref_text_auto"],
          "--gen_text",TEXT,"--output_dir",str(OUT),"--output_file",outfile]
    if not pathlib.Path(args[0]).is_file():
        args[0]=str(pathlib.Path(sys.executable).parent/"habibi-tts_infer-cli.exe")
    print("Generating group",group,"using",sample["name"],flush=True)
    t0=time.monotonic()
    result={"group":group,"reference":sample["name"],"output":outfile,"text":TEXT}
    try:
        p=subprocess.run(args,capture_output=True,text=True,errors="replace",timeout=1200,
                         env=os.environ.copy())
        result["wall_s"]=round(time.monotonic()-t0,2)
        result["returncode"]=p.returncode
        result["log_tail"]=(p.stdout+"\n"+p.stderr)[-3500:]
        wav=OUT/outfile
        if p.returncode == 0 and wav.exists() and wav.stat().st_size>4096:
            result["audio_s"]=duration_wav(wav)
            result["rtf"]=round(result["wall_s"]/max(result["audio_s"],.1),2)
            result["bytes"]=wav.stat().st_size
            result["status"]="generated"
            print(f"GENERATED {outfile} in {result['wall_s']} s, {result['audio_s']} audio s, RTF {result['rtf']}",flush=True)
        else:
            result["status"]="failed"
            print("TTS failed:",result["log_tail"],flush=True)
    except subprocess.TimeoutExpired:
        result.update({"status":"timeout","wall_s":round(time.monotonic()-t0,2)})
        print("TTS timed out for",group,flush=True)
    except Exception as exc:
        result.update({"status":"error","error":str(exc),"traceback":traceback.format_exc()[-2000:]})
        print("TTS exception:",str(exc),flush=True)
    report["generations"].append(result)
    save()
    # Stop after a failed first generation; avoid repeated large downloads/errors.
    if result["status"]!="generated":break
print("BENCHMARK_RESULT:",json.dumps({"samples":len(report["samples"]),"generated":sum(x.get("status")=="generated" for x in report["generations"]),"results":report["generations"]},ensure_ascii=False)[-3200:],flush=True)
save()
