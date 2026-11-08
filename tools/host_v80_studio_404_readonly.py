#!/usr/bin/env python3
"""Read-only shared-host diagnostic for user-reported Agent Studio POST 404.
No cookies, tokens, complete request paths, user content or IPs are logged.
Do not mutate domain, WAF settings, user sessions, database or scheduled jobs.
"""
from pathlib import Path
import json,os,re,time
ROOT=Path('/home/appontrack/public_html')
HOME=Path('/home/appontrack')
def main():
    if os.getuid()!=2379:raise RuntimeError('unexpected_host_uid')
    candidates=[HOME/'access-logs',HOME/'logs',ROOT/'error_log',
      HOME/'tmp',HOME/'modsec_audit.log',HOME/'etc']
    out={'probe':'v80_studio_server_404_readonly','host_uid_ok':True,
       'log_paths':{},'recent_404_post_count':0,'recent_403_post_count':0}
    for p in candidates:
        if p.is_dir():
            out['log_paths'][p.name]={'type':'directory','readable':os.access(p,os.R_OK)}
        elif p.is_file():
            out['log_paths'][p.name]={'type':'file','readable':os.access(p,os.R_OK)}
        else:out['log_paths'][p.name]={'type':'missing'}
    logdirs=[HOME/'access-logs',HOME/'logs']
    sources=[]
    for folder in logdirs:
        if folder.is_dir() and not folder.is_symlink():
            for p in folder.iterdir():
                if p.is_file() and not p.is_symlink() and p.stat().st_size>0:
                    sources.append(p)
    for p in [ROOT/'error_log',HOME/'modsec_audit.log']:
        if p.is_file() and not p.is_symlink():sources.append(p)
    for source in sources[:25]:
        try:
            with source.open('rb') as stream:
                stream.seek(max(0,source.stat().st_size-150000))
                raw=stream.read().decode('utf-8','replace')
        except (PermissionError,OSError):continue
        lines=raw.splitlines()
        recent=0
        for line in lines[-400:]:
            if 'POST ' not in line or '/?' in line:continue
            m=re.search(r'\s(40[34])\s',line)
            if m:
                if m.group(1)=='404':out['recent_404_post_count']+=1
                else:out['recent_403_post_count']+=1
                recent+=1
        if recent:out['log_sources_with_denied_post']=(out.get('log_sources_with_denied_post',0)+1)
    for name in ['.htaccess','index.php','_core/Actions.php','_core/Views.php','assets/js/app.js']:
        p=ROOT/name
        out['source_'+name.replace('/','_')]={'exists':p.is_file(),'is_symlink':p.is_symlink()}
    print(json.dumps(out,separators=(',',':')),flush=True)
if __name__=='__main__':main()
