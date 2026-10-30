#!/usr/bin/env python3
import hashlib,json,os,sys
from pathlib import Path
source=Path('saas/mobile/ota');target=Path(sys.argv[1]);target.mkdir(parents=True,exist_ok=True)
html=(source/'ui.html').read_bytes();meta=json.loads((source/'release.json').read_text())
assert 1<len(html)<=524288 and b'<head>' in html
assert meta['runtime']==1 and meta['revision']>=2 and meta['min_native']<=meta['max_native']
meta.update(sha256=hashlib.sha256(html).hexdigest(),size=len(html),source_commit=os.environ.get('GITHUB_SHA','local'))
meta['file']='ui-'+meta['sha256']+'.html';(target/meta['file']).write_bytes(html)
(target/'payload.json').write_text(json.dumps(meta,sort_keys=True,separators=(',',':')))
print('OTA bundle prepared:',meta['revision'],meta['sha256'])
