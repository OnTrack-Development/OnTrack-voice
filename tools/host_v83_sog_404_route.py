#!/usr/bin/env python3
"""Only expose sanitized route categories for known user-reported 404 POSTs.
No IP, referer, auth token, query, cookie, body, agent or customer data.
"""
from pathlib import Path
import os,re,json
HOME=Path('/home/appontrack')
def sanitize(raw):
    path=raw.split('?',1)[0].split('#',1)[0]
    path=re.sub(r'/portal/[a-z0-9-]+','/portal/[slug]',path,flags=re.I)
    path=re.sub(r'(?<![\w])[0-9]{4,}(?![\w])','[id]',path)
    path=re.sub(r'[A-Fa-f0-9]{20,}','[hash]',path)
    # Routes expected for the editor are public and non-sensitive.
    if len(path)>180:path=path[:180]+'...'
    return path
def main():
    if os.getuid()!=2379:raise RuntimeError('wrong_host')
    rows=[]
    for folder in [HOME/'access-logs',HOME/'logs']:
        if not folder.is_dir():continue
        for f in list(folder.iterdir())[:30]:
            if not f.is_file():continue
            try:
                with f.open('rb') as stream:
                    stream.seek(max(0,f.stat().st_size-150000))
                    lines=stream.read().decode('utf8','replace').splitlines()[-900:]
            except OSError:continue
            for line in lines:
                if 'POST ' not in line:continue
                match=re.search(r'"POST\s+([^\s"]+)\s+HTTP/[\d.]+"\s+404\b',line)
                if not match:continue
                timepart=re.search(r'\[\d{2}/\w{3}/\d{4}:(\d{2}:\d{2}):\d{2}',line)
                clock=timepart.group(1) if timepart else 'unknown'
                # Limit strictly to the two minutes shown in the user screenshot
                if clock not in ('16:45','16:46'):continue
                rows.append({'time_utc':clock,'path':sanitize(match.group(1))})
    print(json.dumps({'probe':'v83_user_404_path_classification','events':rows,
        'query_and_ip_excluded':True,'read_only':True},separators=(',',':')))
if __name__=='__main__':main()
