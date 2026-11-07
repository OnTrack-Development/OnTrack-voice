#!/usr/bin/env python3
"""Deploy only the Lebanese scenario preset to the existing canonical root UI."""
import hashlib, json, os, pathlib, shutil, stat, sys

ROOT = pathlib.Path('/home/appontrack/public_html')
def sha(data): return hashlib.sha256(data).hexdigest()
def read(p): return p.read_text(encoding='utf-8')
def paths(stage):
    return {
        'app':ROOT/'assets/js/app.js','views':ROOT/'_core/Views.php',
        'app_source':stage/'source-app.js','views_source':stage/'source-Views.php',
        'app_candidate':stage/'candidate-app.js','views_candidate':stage/'candidate-Views.php',
        'app_backup':stage/'backup-app.js','views_backup':stage/'backup-Views.php',
        'manifest':stage/'manifest.json'
    }
def patch_app(live, source):
    if "'lebanese-feedback':{text:lebaneseFeedbackExample" in live:return live
    source_start=source.index('  // Extra editable scenario:')
    source_end=source.index('  // Agent Studio stores',source_start)
    marker="""  document.addEventListener('click',async event=>{
    const button=event.target.closest?.('[data-scenario-example="dog-sale"]');"""
    if live.count(marker)!=1:raise ValueError('app.js handler anchor missing or ambiguous')
    start=live.index(marker)
    end=live.index('  // Agent Studio stores',start)
    return live[:start]+source[source_start:source_end]+live[end:]
def patch_views(live, source):
    if 'data-scenario-example="lebanese-feedback"' in live:return live
    anchor="""        '<button class="btn" type="button" data-scenario-example="dog-sale">تحميل مثال ديمو بيع كلب (قابل للتعديل)</button>'."""
    addition="""\n        '<button class="btn" type="button" data-scenario-example="lebanese-feedback">تقييم خدمة Voice Agent · لبناني ودلع (قابل للتعديل)</button>'."""
    if 'data-scenario-example="lebanese-feedback"' not in source or live.count(anchor)!=1:
        raise ValueError('Views.php preset anchor missing or ambiguous')
    return live.replace(anchor,anchor+addition,1)
def prepare(stage):
    p=paths(stage)
    if os.getuid()!=2379 or ROOT.is_symlink() or not ROOT.is_dir():raise ValueError('unexpected host identity or root')
    if not stage.is_dir() or stat.S_IMODE(stage.stat().st_mode)!=0o700:raise ValueError('private stage must be mode 700')
    for key in ('app','views'):
        if p[key].is_symlink() or not p[key].is_file():raise ValueError(key+' live file missing/symlink')
    olds={'app':read(p['app']),'views':read(p['views'])}
    news={'app':patch_app(olds['app'],read(p['app_source'])),
          'views':patch_views(olds['views'],read(p['views_source']))}
    if len(news['app'])>250000 or len(news['views'])>400000:raise ValueError('unexpected file size')
    m={'files':{}}
    for key in ('app','views'):
        old=olds[key].encode('utf-8');new=news[key].encode('utf-8')
        shutil.copy2(p[key],p[key+'_backup'])
        p[key+'_candidate'].write_bytes(new)
        m['files'][key]={'before':sha(old),'after':sha(new),
                          'changed':old!=new,'mode':stat.S_IMODE(p[key].stat().st_mode)}
    p['manifest'].write_text(json.dumps(m),encoding='utf-8')
    print('PREPARED',json.dumps(m))
def replace_atomic(src,dst,mode):
    temp=dst.with_name(dst.name+'.scenario-'+str(os.getpid()))
    try:
        shutil.copyfile(src,temp);os.chmod(temp,mode);os.replace(temp,dst)
    finally:
        if temp.exists():temp.unlink()
def apply(stage):
    p=paths(stage);m=json.loads(read(p['manifest']));updated=[]
    try:
        for key in ('app','views'):
            item=m['files'][key]
            if sha(p[key].read_bytes())!=item['before']:raise ValueError(key+' live changed after preparation')
            if item['changed']:
                if sha(p[key+'_candidate'].read_bytes())!=item['after']:raise ValueError(key+' candidate corrupted')
                replace_atomic(p[key+'_candidate'],p[key],item['mode']);updated.append(key)
        for key in ('app','views'):
            if sha(p[key].read_bytes())!=m['files'][key]['after']:raise ValueError(key+' digest mismatch after deploy')
    except BaseException:
        for key in reversed(updated):
            item=m['files'][key]
            if sha(p[key].read_bytes())==item['after']:
                replace_atomic(p[key+'_backup'],p[key],item['mode'])
        raise
    print('DEPLOYED',json.dumps({k:m['files'][k]['after'] for k in ('app','views')}))
def verify(stage):
    p=paths(stage);m=json.loads(read(p['manifest']))
    for key in ('app','views'):
        if sha(p[key].read_bytes())!=m['files'][key]['after']:raise ValueError(key+' host verification failed')
    if 'lebanese-feedback' not in read(p['app']) or 'lebanese-feedback' not in read(p['views']):
        raise ValueError('preset missing on the host')
    print('VERIFIED',json.dumps({k:m['files'][k]['after'] for k in ('app','views')}))
if __name__=='__main__':
    if len(sys.argv)!=3 or sys.argv[1] not in ('prepare','apply','verify'):
        sys.exit('Usage: patch.py prepare|apply|verify PRIVATE_STAGE')
    globals()[sys.argv[1]](pathlib.Path(sys.argv[2]))
