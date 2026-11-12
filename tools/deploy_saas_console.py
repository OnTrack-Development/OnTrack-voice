"""Install a CI-pinned OnTrack SaaS runtime directly at app.ontrackegy.com/.
The old /saas/console URL is a compatibility route, never a second deployment.
Never include .ontrack-saas keys or customer files in the public archive.
"""
import hashlib,io,json,os,pathlib,shutil,subprocess,tarfile,tempfile,urllib.parse,urllib.request,zipfile,shlex
HOME=pathlib.Path('/home/appontrack')
ROOT=HOME/'public_html'
BACKUPS=HOME/'backup/releases'
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'

def get_archive(url,sha):
    u=urllib.parse.urlsplit(url)
    if u.scheme!='https' or not u.hostname or not (u.hostname.endswith('.blob.core.windows.net') or u.hostname.endswith('.actions.githubusercontent.com')):
        raise ValueError('untrusted_artifact_host')
    if len(sha)!=64 or any(c not in '0123456789abcdef' for c in sha):
        raise ValueError('invalid_artifact_digest')
    with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'OnTrack-Root-Deploy/1.0'}),timeout=12) as response:
        data=response.read(4*1024*1024+1)
    if len(data)>4*1024*1024:raise ValueError('artifact_too_large')
    with zipfile.ZipFile(io.BytesIO(data)) as z:
        members=[i for i in z.infolist() if i.filename=='release.tar.gz']
        if len(members)!=1:raise ValueError('invalid_artifact_bundle')
        tar=z.read(members[0])
    if hashlib.sha256(tar).hexdigest()!=sha:raise ValueError('artifact_digest_mismatch')
    return tar

def site_preflight(download_url,tar_sha,source_commit):
    """Validate all staged PHP and an isolated SQLite knowledge workflow before a live swap."""
    import re
    if not re.fullmatch(r'[0-9a-f]{40}',source_commit):raise ValueError('invalid_source_commit')
    archive=get_archive(download_url,tar_sha)
    stage=pathlib.Path(tempfile.mkdtemp(prefix='.ontrack-ci-',dir=str(HOME)))
    stage.chmod(0o700)
    app=stage/'stage'
    app.mkdir(mode=0o700)
    try:
        with tarfile.open(fileobj=io.BytesIO(archive),mode='r:gz') as tf:
            members=tf.getmembers()
            if len(members)>250:raise ValueError('too_many_archive_files')
            for m in members:
                parts=pathlib.PurePosixPath(m.name).parts
                if not parts or parts[0]!='console' or any(p in ('..','.') for p in parts) or m.issym() or m.islnk() or not (m.isdir() or m.isfile()) or m.size>1024*1024:
                    raise ValueError('invalid_archive_entry')
                target=app.joinpath(*parts[1:]) if len(parts)>1 else app
                if m.isdir():target.mkdir(parents=True,exist_ok=True)
                else:
                    target.parent.mkdir(parents=True,exist_ok=True)
                    with tf.extractfile(m) as inp,target.open('wb') as out:shutil.copyfileobj(inp,out)
        php_files=sorted(app.rglob('*.php'))
        if len(php_files)<30:raise RuntimeError('site_php_archive_incomplete')
        for source in php_files:
            result=subprocess.run([PHP,'-l',str(source)],capture_output=True,text=True,timeout=3)
            if result.returncode!=0:raise RuntimeError('site_php_syntax_'+source.relative_to(app).as_posix())
        seed=app/'database/Seed.php'
        knowledge=app/'_core/VoiceKnowledge.php'
        tool_gateway=app/'_core/VoiceToolGateway.php'
        flow_runtime=app/'_core/VoiceFlowRuntime.php'
        kb_web=app/'_core/VoiceKnowledgeWeb.php'
        if not all(p.is_file() for p in (seed,knowledge,tool_gateway,flow_runtime,kb_web)):
            raise RuntimeError('site_runtime_modules_missing')
        test=(
            'require_once '+json.dumps(str(seed))+';'
            'require_once '+json.dumps(str(knowledge))+';'
            'require_once '+json.dumps(str(tool_gateway))+';'
            'require_once '+json.dumps(str(flow_runtime))+';'
            'require_once '+json.dumps(str(kb_web))+';'
            'seed_console();'
            'voice_tools_init();voice_flow_runtime_init();voice_kb_web_init();'
            "$site=one(\"SELECT id FROM sites WHERE account_id=4 AND status='active' LIMIT 1\");"
            "if(!$site)throw new RuntimeException('site_fixture_missing');"
            '$sid=(int)$site["id"];'
            '$fid=voice_knowledge_folder_add(4,$sid,"Host QA");'
            '$kid=voice_knowledge_add(4,$sid,"QA Facts","note","Host isolate test","public",$fid);'
            "if(!str_contains(voice_knowledge_context(4,$sid),'Host isolate test'))throw new RuntimeException('kb_not_indexed');"
            "if(voice_knowledge_context(5,$sid)!=='')throw new RuntimeException('tenant_isolation_failure');"
            '$v=voice_knowledge_update(4,$sid,$kid,"Updated QA facts");'
            "if($v!==2)throw new RuntimeException('revision_failed');"
            "if(!voice_knowledge_delete(4,$kid))throw new RuntimeException('archive_failed');"
            "if(!voice_knowledge_folder_delete(4,$sid,$fid))throw new RuntimeException('folder_delete_failed');"
            "echo 'SITE_PHP_FIXTURE_PASS';"
        )
        environment=os.environ.copy()
        environment['ONTRACK_TEST_PRIVATE']=str(stage/'private')
        try:
            result=subprocess.run([PHP,'-r',test],capture_output=True,text=True,
                timeout=12,env=environment)
        except subprocess.TimeoutExpired:raise RuntimeError('site_isolated_test_timeout')
        if result.returncode!=0 or 'SITE_PHP_FIXTURE_PASS' not in result.stdout:
            raise RuntimeError('site_isolated_knowledge_test_failed_'+str(result.returncode))
        print(json.dumps({'result':'preflight_pass','source':source_commit[:12],
            'php_files':len(php_files),'isolated_database':True,
            'no_live_db_mutation':True},ensure_ascii=False))
    finally:
        shutil.rmtree(stage,ignore_errors=True)

def rewrite_root_urls(root):
    changes=0
    for p in root.rglob('*'):
        if p.is_symlink() or not p.is_file() or p.suffix.lower() not in ('.php','.js','.css','.html'):continue
        if p.relative_to(root).parts[0] in ('mobile','saas'):continue
        original=p.read_text(errors='replace')
        changed=original.replace('/saas/console','')
        if p.relative_to(root).as_posix()=='_core/Bootstrap.php':
            changed=changed.replace("$prefix='';","$prefix='/saas/console';")
            changed=changed.replace("function go(string $path):never {header('Location: '.($path==='/'?'':$path),true,303);exit;}",
                "function go(string $path):never {header('Location: /'.ltrim($path,'/'),true,303);exit;}")
            changed=changed.replace("'path'=>'',","'path'=>'/',")
        if changed!=original:p.write_text(changed);changes+=1
    bootstrap=(root/'_core/Bootstrap.php').read_text()
    legacy_root_go="function go(string $path):never {header('Location: /'.ltrim"
    scoped_root_go=("header('Location: '.($path==='/'?($prefix===''?'/':$prefix):$prefix.$path),true,303);" in bootstrap
        and "in_array($route,$public,true)?'':''" in bootstrap)
    if not (legacy_root_go in bootstrap or scoped_root_go):
        raise RuntimeError('root_url_transform_failed')
    return changes


def ensure_knowledge_sync_cron():
    """Install only this site's five-minute CLI entry, preserving every other cron."""
    tag='# ontrack-site-knowledge-sync'
    entry='*/5 * * * * '+shlex.quote(PHP)+' '+shlex.quote(str(ROOT/'jobs/knowledge_sync.php'))+' >/dev/null 2>&1 '+tag
    current=subprocess.run(['crontab','-l'],capture_output=True,text=True,timeout=10)
    if current.returncode:
        if not (current.returncode==1 and not current.stdout.strip() and
                'no crontab' in current.stderr.lower()):
            raise RuntimeError('site_kb_crontab_unreadable')
        prior=[]
    else:
        prior=current.stdout.splitlines()
    if entry in prior:return True
    preserved=[line for line in prior if tag not in line]
    updated='\n'.join(preserved+[entry])+'\n'
    result=subprocess.run(['crontab','-'],input=updated,text=True,
                          capture_output=True,timeout=10)
    if result.returncode:raise RuntimeError('site_kb_cron_install_failed')
    verified=subprocess.run(['crontab','-l'],capture_output=True,text=True,timeout=10)
    if verified.returncode or entry not in verified.stdout.splitlines():
        raise RuntimeError('site_kb_cron_verify_failed')
    return True

def deploy(download_url,tar_sha,source_commit):
    if os.getuid()!=2379 or ROOT.is_symlink() or not ROOT.is_dir() or not (ROOT/'mobile').is_dir():
        raise RuntimeError('root_or_mobile_compat_missing')
    if not (HOME/'backup/ontrack-full-2026-11-03/status.json').is_file():
        raise RuntimeError('initial_full_account_backup_missing')
    archive=get_archive(download_url,tar_sha)
    stage=pathlib.Path(tempfile.mkdtemp(prefix='.voice-root-deploy-',dir=str(HOME)))
    inner=stage/'public_html';inner.mkdir(mode=0o755)
    backups=None;switched=False;files=0
    try:
        with tarfile.open(fileobj=io.BytesIO(archive),mode='r:gz') as tf:
            members=tf.getmembers()
            if len(members)>250:raise ValueError('too_many_archive_files')
            for m in members:
                parts=pathlib.PurePosixPath(m.name).parts
                if not parts or parts[0]!='console' or any(p in ('..','.') for p in parts) or m.issym() or m.islnk() or not (m.isdir() or m.isfile()) or m.size>1024*1024:
                    raise ValueError('invalid_archive_entry')
                relative=parts[1:]
                if not relative:continue
                dest=inner.joinpath(*relative)
                if m.isdir():dest.mkdir(parents=True,exist_ok=True)
                else:
                    dest.parent.mkdir(parents=True,exist_ok=True)
                    with tf.extractfile(m) as inp,dest.open('wb') as out:shutil.copyfileobj(inp,out)
                    dest.chmod(0o644);files+=1
        if not (inner/'_core/.htaccess').is_file() or not (inner/'index.php').is_file():
            raise ValueError('missing_front_controller')
        changes=rewrite_root_urls(inner)
        shutil.copytree(ROOT/'mobile',inner/'mobile',symlinks=False)
        if (ROOT/'assets/voice-samples').is_dir():
            shutil.copytree(ROOT/'assets/voice-samples',inner/'assets/voice-samples',symlinks=False,dirs_exist_ok=True)
        if (ROOT/'.well-known').is_dir():shutil.copytree(ROOT/'.well-known',inner/'.well-known',symlinks=True)
        (inner/'saas').mkdir(exist_ok=True)
        (inner/'saas/mobile').symlink_to('../mobile',target_is_directory=True)
        shutil.copy2(ROOT/'.htaccess',inner/'.htaccess')
        for directory,dirs,_ in os.walk(inner,followlinks=False):
            pathlib.Path(directory).chmod(0o755)
        for name in ['index.php','_core/Bootstrap.php','_core/Actions.php','_core/Views.php',
                     '_core/FlowAiLive.php','database/Seed.php','api/v1/index.php','device/v1/index.php']:
            outcome=subprocess.run([PHP,'-l',str(inner/name)],capture_output=True,text=True,timeout=3)
            if outcome.returncode!=0:raise RuntimeError('php_lint_'+name)
        BACKUPS.mkdir(parents=True,exist_ok=True,mode=0o700)
        BACKUPS.chmod(0o700)
        backups=BACKUPS/source_commit[:12]
        if backups.exists():raise RuntimeError('duplicate_release_backup')
        os.replace(ROOT,backups)
        try:os.replace(inner,ROOT);switched=True
        except Exception:os.replace(backups,ROOT);raise
        seed=subprocess.run([PHP,str(ROOT/'database/Seed.php')],capture_output=True,text=True,timeout=9)
        if seed.returncode!=0:raise RuntimeError('seed_failed')
        assert (ROOT/'saas/mobile').resolve()==(ROOT/'mobile').resolve()
        cron_installed=ensure_knowledge_sync_cron()
        print(json.dumps({'result':'deployed','source':source_commit[:12],'target':'/',
                          'files':files,'rewritten':changes,'php':'8.3',
                          'seed':json.loads(seed.stdout),'private_rollback':str(backups),
                          'android_compat':True,'knowledge_cron_installed':cron_installed},ensure_ascii=False))
    except Exception:
        if switched and backups is not None and backups.exists():
            broken=stage/'broken_root'
            os.replace(ROOT,broken);os.replace(backups,ROOT)
        raise
    finally:
        shutil.rmtree(stage,ignore_errors=True)
