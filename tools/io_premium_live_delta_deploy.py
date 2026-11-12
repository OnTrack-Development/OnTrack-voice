#!/usr/bin/env python3
"""Minimal, auditable and reversible three-path live IO Premium release.
Preserve every unrelated server-side edit. Never overwrite changed Bootstrap
or voice sessions wholesale; patch only versioned exact function anchors.
"""
import json
import hashlib
import os
import pathlib
import shutil
import subprocess
import tempfile
import time

ROOT=pathlib.Path("/home/appontrack/public_html")
STAGE=pathlib.Path("/home/appontrack/.ontrack-mobile/io-premium-v51-stage")
PRIVATE=pathlib.Path("/home/appontrack/.ontrack-mobile")
PHP="/opt/cpanel/ea-php83/root/usr/bin/php"
PATHS=("_core/Bootstrap.php","_core/TenantVoiceSession.php","device/v1/tts.php")

def sha(data):
    return hashlib.sha256(data).hexdigest()

def load(path):
    if path.is_symlink() or not path.is_file():
        raise RuntimeError("unsafe_or_missing_path_"+path.name)
    data=path.read_bytes()
    if b"\x00" in data: raise RuntimeError("binary_source_disallowed")
    return data

def atomic(path,data):
    handle,temp=tempfile.mkstemp(dir=str(path.parent),prefix=".ontrack-io-")
    try:
        with os.fdopen(handle,"wb") as out:
            out.write(data);out.flush();os.fsync(out.fileno())
        os.chmod(temp,0o644)
        os.replace(temp,path)
    finally:
        if os.path.exists(temp): os.unlink(temp)

def normalize(data):
    return data.decode("utf-8").replace("\r\n","\n")

def produce_bootstrap(live,reference):
    mark="function init_console_v27():void {"
    next_mark="function init_console_v25():void {"
    if reference.count(mark)!=1 or reference.count(next_mark)!=1:
        raise RuntimeError("unreviewed_bootstrap_source")
    snippet=reference[reference.index(mark):reference.index(next_mark)]
    if not snippet.endswith("\n\n"):raise RuntimeError("invalid_bootstrap_v27_function")
    if live.count(mark)==1:
        # Existing version cannot be silently overwritten.
        if snippet.strip() not in live:raise RuntimeError("v27_already_present_but_different")
        return live
    if live.count(mark)!=0 or live.count(next_mark)!=1:
        raise RuntimeError("unreviewed_live_bootstrap_structure")
    base="init_console_v25(); init_console_v26();"
    if live.count(base)!=2:
        raise RuntimeError("bootstrap_schema_init_anchor_changed")
    updated=live.replace(base,base+" init_console_v27();")
    updated=updated.replace(next_mark,snippet+next_mark,1)
    if updated.count("init_console_v27();")!=2:raise RuntimeError("schema_v27_not_invoked_twice")
    return updated

def produce_voice_session(live,reference):
    mark="'voice_engine'=>"
    if mark in live:
        if "'official_google_live_fish_pcm'" not in live:
            raise RuntimeError("existing_voice_engine_different")
        return live
    needle="'voice'=>$definition['voice'],'answer_policy'=>$definition['policy'],"
    if live.count(needle)!=1:raise RuntimeError("voice_session_return_anchor_changed")
    # A pure expression is safer than replacing other server session settings.
    new="'voice'=>$definition['voice'],\n        'voice_engine'=>$definition['voice']==='io.premium'?'official_google_live_fish_pcm':'gemini_live_native',\n        'answer_policy'=>$definition['policy'],"
    result=live.replace(needle,new,1)
    if result.count(mark)!=1:raise RuntimeError("voice_engine_insert_failed")
    return result

def main():
    if os.getuid()!=2379 or ROOT.is_symlink() or STAGE.is_symlink() or not ROOT.is_dir():
        raise RuntimeError("unexpected_deployment_root_or_user")
    paths={}
    for name in PATHS:
        src=STAGE/name;target=ROOT/name
        if target.parent.is_symlink() or not target.parent.is_dir() or src.parent.is_symlink():
            raise RuntimeError("unsafe_deploy_parent")
        reference=normalize(load(src))
        old=load(target) if target.is_file() else b""
        if target.exists() and target.is_symlink():raise RuntimeError("symlink_target_refused")
        if name.endswith("Bootstrap.php"):patched=produce_bootstrap(normalize(old),reference).encode("utf-8")
        elif name.endswith("TenantVoiceSession.php"):patched=produce_voice_session(normalize(old),reference).encode("utf-8")
        else:
            if old and sha(old)!=sha(reference.encode("utf-8")):
                raise RuntimeError("existing_unreviewed_premium_tts_endpoint")
            patched=reference.encode("utf-8")
        with tempfile.NamedTemporaryFile(dir=str(PRIVATE),suffix=".php",delete=False) as f:
            f.write(patched);tmp=f.name
        try:
            lint=subprocess.run([PHP,"-l",tmp],capture_output=True,text=True,timeout=12)
            if lint.returncode!=0:raise RuntimeError("php_lint_failed_"+name)
        finally:os.unlink(tmp)
        paths[name]={"target":target,"before":old,"after":patched}
    print(json.dumps({"stage":"preflight_pass","paths":[{"path":k,"changed":v["before"]!=v["after"],
        "before_sha":sha(v["before"]) if v["before"] else None,"after_sha":sha(v["after"])}
        for k,v in paths.items()],"secret_values_logged":False},separators=(",",":")),flush=True)
    back=PRIVATE/"io-premium-v51-backup"/("delta-"+str(int(time.time())))
    back.mkdir(parents=True,mode=0o700,exist_ok=False)
    changed=[]
    try:
        for name,info in paths.items():
            if info["before"]!=info["after"]:
                dst=back/name;dst.parent.mkdir(parents=True,exist_ok=True)
                if info["before"]:
                    dst.write_bytes(info["before"]);dst.chmod(0o600)
                # Avoid overwriting any concurrently edited live content.
                now=load(info["target"]) if info["target"].is_file() else b""
                if sha(now)!=sha(info["before"]):raise RuntimeError("live_file_changed_during_release_"+name)
                atomic(info["target"],info["after"]);changed.append(name)
                if sha(load(info["target"]))!=sha(info["after"]):
                    raise RuntimeError("post_copy_sha_failed_"+name)
        print(json.dumps({"stage":"published","changed":changed,
            "rollback_location":str(back),"other_site_files_untouched":True},separators=(",",":")))
    except BaseException:
        for name in reversed(changed):
            info=paths[name]
            if info["before"]:atomic(info["target"],info["before"])
            else:info["target"].unlink(missing_ok=True)
        raise

if __name__=="__main__":
    main()
