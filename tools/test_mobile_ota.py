#!/usr/bin/env python3
"""Tests the deploy boundary and queue collision protection; no host access."""
import base64,contextlib,hashlib,importlib.util,io,json,os,tempfile,unittest,zlib
from pathlib import Path
from unittest.mock import patch
ROOT=Path(__file__).resolve().parent.parent
def module(name,path):
    spec=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
publisher=module('publisher',ROOT/'tools/queue_mobile_ota.py')
deployer=module('deployer',ROOT/'saas/mobile/ota/deploy.py')
class OtaBoundaryTests(unittest.TestCase):
    def test_deploy_isolated_immutable_and_revision_cannot_decrease(self):
        with tempfile.TemporaryDirectory() as tmp:
            folder=Path(tmp);root=folder/'mobile';private=folder/'private';root.mkdir();private.mkdir(mode=0o700)
            original=folder/'root-api.php';original.write_text('UNCHANGED')
            def redirect(value):
                return root if value=='/home/appontrack/public_html/saas/mobile' else private if value=='/home/appontrack/.ontrack-mobile' else Path(value)
            def install(revision,html):
                raw=html.encode();digest=hashlib.sha256(raw).hexdigest();meta={'file':'ui-'+digest+'.html','sha256':digest,'size':len(raw),'revision':revision,'runtime':1}
                manifest=json.dumps({'payload':json.dumps(meta),'signature':'client-verifies-this'})
                bundle=json.dumps({'manifest':manifest,'html':html}).encode()
                with patch.object(deployer.os,'getuid',return_value=2379),patch.object(deployer,'Path',side_effect=redirect),contextlib.redirect_stdout(io.StringIO()):
                    deployer.deploy(base64.b64encode(zlib.compress(bundle)).decode(),hashlib.sha256(bundle).hexdigest())
                return manifest
            first=install(2,'<head>v2</head>');self.assertEqual(first,(root/'updates/latest.json').read_text())
            install(3,'<head>v3</head>');self.assertEqual(first,(private/'ota-backups/manifest-2.json').read_text())
            with self.assertRaises(ValueError):install(2,'<head>old</head>')
            with self.assertRaises(ValueError):install(3,'<head>changed-same-revision</head>')
            self.assertEqual('UNCHANGED',original.read_text());self.assertEqual(0o755,(root/'updates').stat().st_mode&0o777)
    def test_unknown_pending_queue_is_never_replaced(self):
        raw=json.dumps({'id':'other-task'},indent=2).encode();writes=[]
        def content(path):
            if '/jobs/' in path:return {'sha':'lease'},raw
            return {},json.dumps({'job_id':'other-task','target':publisher.TARGET,'status':'interrupted_unknown','job_sha256':hashlib.sha256(raw).hexdigest()}).encode()
        with patch.object(publisher,'verify',return_value=('{}','<head></head>',{'revision':2})),patch.object(publisher,'content',side_effect=content),patch.object(publisher,'request',side_effect=lambda *a,**kw:writes.append(a)),patch.dict(os.environ,{'GITHUB_REF':'refs/heads/main','GITHUB_RUN_ID':'test'}),patch('sys.argv',['script','unused']):
            with self.assertRaises(AssertionError):publisher.main()
        self.assertEqual([],writes)
    def test_bad_result_hash_preserves_queue(self):
        raw=json.dumps({'id':'previous-task'},indent=2).encode()
        def content(path):
            if '/jobs/' in path:return {'sha':'lease'},raw
            return {},json.dumps({'job_id':'previous-task','target':publisher.TARGET,'status':'success','job_sha256':'bad'}).encode()
        with patch.object(publisher,'verify',return_value=('{}','<head></head>',{'revision':2})),patch.object(publisher,'content',side_effect=content),patch.object(publisher,'request') as write,patch.dict(os.environ,{'GITHUB_REF':'refs/heads/main','GITHUB_RUN_ID':'test'}),patch('sys.argv',['script','unused']):
            with self.assertRaises(AssertionError):publisher.main()
            write.assert_not_called()
if __name__=='__main__':unittest.main()
