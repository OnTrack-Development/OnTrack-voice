#!/usr/bin/env python3
"""Add IO Premium voice management to the canonical live SaaS without reverting
independent edits already on the server. Only unique reviewed anchors may change.
All three files are PHP-linted before any atomic write; rollback on failure.
"""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import time

ROOT=Path("/home/appontrack/public_html/_core")
STAGE=Path("/home/appontrack/.ontrack-mobile/voice-controls-stage")
BACKUP=Path("/home/appontrack/.ontrack-mobile/voice-controls-backup")
PHP="/opt/cpanel/ea-php83/root/usr/bin/php"
EXPECTED={
 "Views.php":"eb7088a8faa177324e8bb039faff74e7abd1f650",
 "Actions.php":"7d1e387efd79d94e0cd06156ad62d1f178bf4101",
 "IoPremiumProvider.php":"bfeed5397e2fc4071db4c56e71045dfa0e33a980",
}

def gitblob(blob):
    return hashlib.sha1(("blob "+str(len(blob))).encode("ascii")+b"\0"+blob).hexdigest()

def source(path):
    if not path.is_file() or path.is_symlink():raise RuntimeError("source_or_target_missing_"+path.name)
    return path.read_bytes()

def nl(blob):
    if b"\x00" in blob:raise RuntimeError("binary_source_disallowed")
    return blob.decode("utf-8").replace("\r\n","\n")

def one_replace(text,old,new,label):
    if old==new:raise RuntimeError("no_change_"+label)
    if new in text and old not in text:return text
    if text.count(old)!=1:raise RuntimeError("unexpected_live_anchor_"+label)
    return text.replace(old,new,1)

def new_section(template,start,end):
    if template.count(start)!=1 or template.count(end)!=1:
        raise RuntimeError("unreviewed_reference_section")
    return template[template.index(start):template.index(end)]

def views_patch(live,ref):
    # Restore the already-implemented voices page to every intended sidebar.
    live=one_replace(live,"unset($nav['whatsapp_admin'],$nav['voices']);",
                     "unset($nav['whatsapp_admin']);","platform_voices_link")
    live=one_replace(live,"        unset($nav['voices']);\n        return $nav;",
                     "        return $nav;","reseller_voices_link")
    live=one_replace(live,"unset($nav['campaigns'],$nav['whatsapp'],$nav['voices'],$nav['staff']);",
                     "unset($nav['campaigns'],$nav['whatsapp'],$nav['staff']);","customer_voices_link")
    # Voice choice lives inside the existing Agent / الصوت والمكالمة pane.
    old="select_html('الصوت','voice',$options,(string)$agent['voice']).'</div>'."
    new="'</div>'."
    live=one_replace(live,old,new,"remove_hidden_agent_voice_control")
    old=("'<div class=\"agent-studio-section\"><div class=\"agent-studio-section-title\">"
         "<strong>الرد والمكالمة</strong></div>'.")
    new=("<div class=\"agent-studio-section\"><div class=\"agent-studio-section-title\">")
    # Read the exact reviewed pane header and voice dropdown from new revision.
    start="                '<div class=\"agent-studio-section\"><div class=\"agent-studio-section-title\"><strong>الصوت والرد والمكالمة</strong></div>'."
    end="                '<div class=\"form-grid\"><label class=\"field\">مهلة الرد بالثواني"
    pane=new_section(ref,start,end)
    old_pane="                '<div class=\"agent-studio-section\"><div class=\"agent-studio-section-title\"><strong>الرد والمكالمة</strong></div>'.\n"
    live=one_replace(live,old_pane,pane,"agent_voice_section")
    # Only one select name=voice in the editing form, plus one in create form.
    marker="function render_voices(array $a):void{\n"
    live=one_replace(live,marker,marker+"    require_once __DIR__.'/IoPremiumProvider.php';\n","provider_ui_include")
    old="    if($showProvider) echo card('إدارة كتالوج الأصوات',tbl(\n"
    new=("    if($showProvider && in_array((string)$a['role'],['owner','admin'],true) && !active_support())\n"
         "        echo render_io_premium_admin_provider();\n"+old)
    live=one_replace(live,old,new,"provider_admin_form")
    if "name=\"io_fish_api_key\"" in live:raise RuntimeError("provider_credentials_must_be_rendered_by_helper_only")
    if "render_io_premium_admin_provider();" not in live or "select_html('الصوت المستخدم لهذا المساعد','voice'" not in live:
        raise RuntimeError("required_voice_ui_not_present")
    return live

def actions_patch(live,ref):
    # Reuse the single canonical form action; never add a parallel credential store.
    marker="            case 'set_voice_ready':\n"
    segment=new_section(ref,"            case 'save_io_premium_provider':\n",marker)
    action_count=live.count("case 'save_io_premium_provider':")
    if action_count==0:
        live=one_replace(live,marker,segment+marker,"provider_save_action")
    elif action_count==1:
        # Another verified deploy may already have installed the same action.
        # Never insert a duplicate, but reject a conflicting handler body.
        if segment.strip() not in live:
            raise RuntimeError("existing_provider_handler_changed")
    else:
        raise RuntimeError("duplicate_provider_action_already_live")
    old=("                $voice=one('SELECT id FROM voices WHERE id=?',[intfield('voice_id')]);if(!$voice)deny(404);\n"
         "                $ready=field('ready',1)==='1'?1:0;\n")
    new=new_section(ref,
        "                $voice=one('SELECT id,code FROM voices WHERE id=?'",
        "                if($ready && (string)($_POST['confirmed']??'')!=='1')")
    live=one_replace(live,old,new,"configured_credential_required_for_approval")
    if live.count("case 'save_io_premium_provider':")!=1:
        raise RuntimeError("provider_action_missing_or_duplicate")
    return live

def atomic(path,data):
    fd,tmp=tempfile.mkstemp(prefix=".ontrack-voices-",dir=str(path.parent))
    try:
        with os.fdopen(fd,"wb") as f:
            f.write(data);f.flush();os.fsync(f.fileno())
        os.chmod(tmp,0o644)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

def main():
    if os.getuid()!=2379 or not ROOT.is_dir() or ROOT.is_symlink() or not STAGE.is_dir() or STAGE.is_symlink():
        raise RuntimeError("unsafe_account_or_directories")
    planned={}
    for name in EXPECTED:
        src=STAGE/name; dst=ROOT/name
        if dst.is_symlink() or src.is_symlink():raise RuntimeError("symlink_refused")
        reference=nl(source(src)).encode()
        if gitblob(reference)!=EXPECTED[name]:raise RuntimeError("unreviewed_staged_git_blob_"+name)
        before=source(dst) if dst.is_file() else b""
        if name=="IoPremiumProvider.php":
            if before and nl(before).encode()!=reference:
                raise RuntimeError("existing_provider_php_unexpected")
            after=reference
        else:
            if not before:raise RuntimeError("live_source_missing_"+name)
            live=nl(before)
            target=nl(reference)
            output=views_patch(live,target) if name=="Views.php" else actions_patch(live,target)
            after=output.encode("utf-8")
        fd,tmp=tempfile.mkstemp(dir=str(STAGE),suffix=".php")
        try:
            with os.fdopen(fd,"wb") as t:t.write(after)
            lint=subprocess.run([PHP,"-l",tmp],stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=10)
            if lint.returncode!=0:raise RuntimeError("PHP_SYNTAX_FAILED_"+name)
        finally:os.unlink(tmp)
        planned[name]=(dst,before,after)
    print(json.dumps({"stage":"preflight","php_syntax":"PASS",
        "files":[{"file":name,"changed":before!=after,"new_sha256":hashlib.sha256(after).hexdigest()}
                  for name,(_,before,after) in planned.items()]},separators=(",",":")),flush=True)
    snapshot=BACKUP/str(int(time.time()))
    snapshot.mkdir(parents=True,mode=0o700)
    changed=[]
    try:
        for name,(dst,before,after) in planned.items():
            if before==after:continue
            back=snapshot/name
            if before:
                shutil.copy2(dst,back);os.chmod(back,0o600)
            if (source(dst) if dst.is_file() else b"")!=before:
                raise RuntimeError("live_concurrent_change_refused_"+name)
            atomic(dst,after);changed.append(name)
            if source(dst)!=after:raise RuntimeError("readback_sha_mismatch_"+name)
        print(json.dumps({"status":"published","changed":changed,
                          "rollback":str(snapshot),"private_keys_untouched":True},separators=(",",":")),flush=True)
    except BaseException:
        for name in reversed(changed):
            dst,before,_=planned[name]
            if before:atomic(dst,before)
            else:dst.unlink(missing_ok=True)
        raise

if __name__=="__main__":main()
