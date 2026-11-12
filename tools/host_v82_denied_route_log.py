#!/usr/bin/env python3
"""Read-only classify recent 404 POSTs in cPanel access logs without PII,
IP addresses, referers, cookies, query strings or raw request bodies."""
import json,os,re
from pathlib import Path
HOME=Path('/home/appontrack')
def main():
    if os.getuid()!=2379:raise RuntimeError('unauthorized_uid')
    sources=[]
    for folder in [HOME/'access-logs',HOME/'logs']:
        if folder.is_dir():
            for p in folder.iterdir():
                if p.is_file() and p.stat().st_size>0:sources.append(p)
    patterns={};records=[];total=0
    for p in sources[:30]:
        try:
            with p.open('rb') as f:
                f.seek(max(0,p.stat().st_size-220000))
                lines=f.read().decode('utf-8','replace').splitlines()[-900:]
        except OSError:continue
        for line in lines:
            if 'POST ' not in line:continue
            match=re.search(r'"POST\s+([^\s"]+)\s+HTTP/[\d.]+"\s+(404|403|421)\b',line)
            if not match:continue
            path=match.group(1).split('?',1)[0]
            if path.startswith('/portal/'):path='/portal/[slug]'
            elif path not in ('/','/studio-save','/saas/console/'):
                path='/other'
            status=match.group(2)
            bucket=status+' '+path
            patterns[bucket]=patterns.get(bucket,0)+1
            total+=1
            if len(records)<12:
                moment=re.search(r'\[\d{2}/\w{3}/\d{4}:(\d{2}:\d{2}):\d{2}',line)
                records.append({'status':int(status),'path_bucket':path,
                                'time_HHMM':moment.group(1) if moment else None})
    print(json.dumps({'probe':'v82_denied_post_route_readonly',
       'log_files_examined':len(sources[:30]),'denials_seen':total,
       'by_bucket':patterns,'sample':records,'raw_ip_or_url_exposed':False,
       'live_data_modified':False},separators=(',',':')),flush=True)
if __name__=='__main__':main()
