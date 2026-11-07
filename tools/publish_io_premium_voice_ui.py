#!/usr/bin/env python3
"""Targeted on-host UI patch. Preserve all unrelated live SaaS edits."""
import hashlib,json,os,pathlib,subprocess,tempfile,time

ROOT=pathlib.Path('/home/appontrack/public_html/_core')
STAGE=pathlib.Path('/home/appontrack/.ontrack-mobile/io-premium-voice-ui-stage')
PRIV=pathlib.Path('/home/appontrack/.ontrack-mobile')
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
SHA='bfeed5397e2fc4071db4c56e71045dfa0e33a980'

def blob(b):return hashlib.sha1(('blob '+str(len(b))).encode()+bytes([0])+b).hexdigest()
def norm(b):return b.decode('utf-8').replace('\r\n','\n')
def swap(s,a,b,k):
    # If already published, do not insert provider action/card twice on rerun.
    if s.count(b)==1:return s
    if s.count(a)==1:return s.replace(a,b,1)
    raise RuntimeError('unexpected_live_ui_anchor_'+k)
def atomic(p,b):
    f,t=tempfile.mkstemp(dir=str(p.parent),prefix='.io-premium-')
    try:
        with os.fdopen(f,'wb') as h:h.write(b);h.flush();os.fsync(h.fileno())
        os.chmod(t,0o644);os.replace(t,p)
    finally:
        if os.path.exists(t):os.unlink(t)

def patch_views(v):
    v=swap(v,"        unset($nav['whatsapp_admin'],$nav['voices']);",
             "        unset($nav['whatsapp_admin']);",'platform_nav')
    v=swap(v,"        unset($nav['voices']);\n        return $nav;",
             "        return $nav;",'reseller_nav')
    v=swap(v,"    unset($nav['campaigns'],$nav['whatsapp'],$nav['voices'],$nav['staff']);",
             "    unset($nav['campaigns'],$nav['whatsapp'],$nav['staff']);",'user_nav')
    v=swap(v,"                select_html('الصوت','voice',$options,(string)$agent['voice']).'</div>'.",
             "                '</div>'.",'remove_misplaced_voice')
    v=swap(v,
      "                '<div class=\"agent-studio-section\"><div class=\"agent-studio-section-title\"><strong>الرد والمكالمة</strong></div>'.",
      "                '<div class=\"agent-studio-section\"><div class=\"agent-studio-section-title\"><strong>الصوت والرد والمكالمة</strong></div>'.\n"
      "                select_html('الصوت المستخدم لهذا المساعد','voice',$options,(string)$agent['voice']).\n"
      "                '<p class=\"dim\">الصوت المختار بيتفعّل في المكالمات بعد الضغط على «نشر إعدادات المساعد»؛ IO Premium يحتاج تفعيل الأدمن ومنح الصلاحية للحساب.</p>'.",
      'voice_call_pane')
    v=swap(v,"function render_voices(array $a):void{\n    $id=(int)$a['account_id'];",
      "function render_voices(array $a):void{\n    require_once __DIR__.'/IoPremiumProvider.php';\n    $id=(int)$a['account_id'];",'import_provider')
    v=swap(v,"    if($showProvider) echo card('إدارة كتالوج الأصوات',tbl(",
      "    if($showProvider && in_array((string)$a['role'],['owner','admin'],true) && !active_support())\n"
      "        echo render_io_premium_admin_provider();\n"
      "    if($showProvider) echo card('إدارة كتالوج الأصوات',tbl(",'provider_widget')
    v=swap(v,"function render_keys(array $a):void{\n    $id=(int)$a['account_id'];",
      "function render_keys(array $a):void{\n"
      "    if($a['kind']==='platform')\n"
      "        echo card('مفاتيح مزودي الصوت','<p class=\"dim\">مفتاح Fish Audio وVoice ID الخاص بصوت IO Premium بيتداروا من «إدارة الأصوات»، مش من مفاتيح API اللي بنصدرها للعملاء.</p><a class=\"btn primary\" href=\"/?tab=voices\">فتح إعدادات IO Premium والصوت</a>');\n"
      "    $id=(int)$a['account_id'];",'api_key_shortcut')
    return v

def patch_actions(v):
    v=swap(v,"            case 'set_voice_ready':\n",
      "            case 'save_io_premium_provider':\n"
      "                if($kind!=='platform'||!in_array($a['role'],['owner','admin'],true)||active_support())deny();\n"
      "                require_once __DIR__.'/IoPremiumProvider.php';\n"
      "                io_premium_admin_save(\n"
      "                    field('io_fish_api_key',2048),\n"
      "                    required('io_voice_id',32),\n"
      "                    required('io_fish_model',32)\n"
      "                );\n"
      "                audit('voice.io_premium.config_verified',$id,['provider'=>'fish_audio','ready'=>true]);\n"
      "                $success='تم اختبار Fish Audio وتشفير المفتاح وحفظ IO Premium. اختار الصوت من إعدادات المساعد وانشر النسخة الجديدة.';\n"
      "                break;\n"
      "            case 'set_voice_ready':\n",'provider_save')
    v=swap(v,
      "                $voice=one('SELECT id FROM voices WHERE id=?',[intfield('voice_id')]);if(!$voice)deny(404);\n"
      "                $ready=field('ready',1)==='1'?1:0;",
      "                $voice=one('SELECT id,code FROM voices WHERE id=?',[intfield('voice_id')]);if(!$voice)deny(404);\n"
      "                $ready=field('ready',1)==='1'?1:0;\n"
      "                if($ready && $voice['code']==='io.premium'){\n"
      "                    require_once __DIR__.'/IoPremiumProvider.php';\n"
      "                    if(!io_premium_admin_state()['configured'])\n"
      "                        throw new InvalidArgumentException('أدخل مفتاح Fish Audio وVoice ID أولاً من إعدادات IO Premium');\n"
      "                }",'provider_activate')
    return v

def main():
    if os.getuid()!=2379 or not ROOT.is_dir() or ROOT.is_symlink() or STAGE.is_symlink():
        raise RuntimeError('unsafe_install_directory_or_user')
    sources={}
    for name in ['Views.php','Actions.php','IoPremiumProvider.php']:
        p=STAGE/name
        if not p.is_file() or p.is_symlink():raise RuntimeError('missing_reviewed_file_'+name)
        sources[name]=norm(p.read_bytes())
    if blob(sources['IoPremiumProvider.php'].encode('utf-8'))!=SHA:
        raise RuntimeError('unreviewed_provider_source')
    targets={}
    for name in sources:
        p=ROOT/name
        if p.is_symlink():raise RuntimeError('target_symlink_'+name)
        previous=p.read_bytes() if p.is_file() else b''
        if name=='Views.php':result=patch_views(norm(previous))
        elif name=='Actions.php':result=patch_actions(norm(previous))
        else:
            result=sources[name]
            if previous and norm(previous)!=result:raise RuntimeError('existing_provider_file_differs')
        new=result.encode('utf-8')
        f,tmp=tempfile.mkstemp(suffix='.php',dir=str(STAGE))
        try:
            with os.fdopen(f,'wb') as file:file.write(new)
            chk=subprocess.run([PHP,'-l',tmp],capture_output=True,text=True,timeout=12)
            if chk.returncode!=0:raise RuntimeError('PHP_lint_failed_'+name)
        finally:os.unlink(tmp)
        targets[name]=(p,previous,new)
    backup=PRIV/'io-premium-v51-backup'/('voices-ui-'+str(int(time.time())))
    backup.mkdir(mode=0o700,parents=True,exist_ok=False)
    touched=[]
    try:
        for name,(path,old,new) in targets.items():
            if old==new:continue
            if old:(backup/name).write_bytes(old)
            live=path.read_bytes() if path.exists() else b''
            if live!=old:raise RuntimeError('concurrent_edit_'+name)
            atomic(path,new);touched.append(name)
        print(json.dumps({'status':'published','php_lint':'PASS','files':touched,
            'backup':str(backup),'site_unrelated_changes_preserved':True},separators=(',',':')))
    except BaseException:
        for name in reversed(touched):
            path,old,_=targets[name]
            if old:atomic(path,old)
            else:path.unlink(missing_ok=True)
        raise

if __name__=='__main__':main()
