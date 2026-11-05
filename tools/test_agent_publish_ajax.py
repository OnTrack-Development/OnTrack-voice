#!/usr/bin/env python3
"""Regression: an authenticated Agent Studio submit must never get a blank/HTML error."""
import json
import pathlib
import subprocess

ROOT=pathlib.Path(__file__).resolve().parents[1]
BOOTSTRAP=ROOT/'saas/console/_core/Bootstrap.php'
SOURCE=(ROOT/'saas/console/assets/js/app.js').read_text(encoding='utf-8')
ACTIONS=(ROOT/'saas/console/_core/Actions.php').read_text(encoding='utf-8')
FRONT=(ROOT/'saas/console/index.php').read_text(encoding='utf-8')

def check_case(name, ajax, call, expected):
    php=(
        '$_SERVER["REQUEST_METHOD"]="POST";'
        '$_SERVER["HTTP_X_REQUESTED_WITH"]='+('"XMLHttpRequest"' if ajax else '"not_ajax"')+';'
        '$_POST=["action"=>"update_agent","csrf"=>"expired"];'
        '$_SESSION=["csrf"=>"current"];'
        'require '+json.dumps(str(BOOTSTRAP.resolve()).replace('\\','/'))+';'
        +call
    )
    run=subprocess.run(['php','-r',php],text=True,capture_output=True,timeout=12)
    assert run.returncode==0,(name,run.stderr[:350])
    if ajax:
        payload=json.loads(run.stdout)
        assert payload['ok'] is False and expected in payload['error'],(name,payload)
    else:
        assert expected in run.stdout and not run.stdout.lstrip().startswith('{'),name
    print('PASS',name)

check_case('agent expired CSRF is JSON',True,'require_csrf();','انتهت صلاحية الطلب')
check_case('agent unauthenticated is JSON',True,'require_login();','انتهت جلسة الدخول')
check_case('agent permission denied is JSON',True,'deny(403,"ممنوع");','ممنوع')
check_case('non AJAX keeps original CSRF behavior',False,'require_csrf();','انتهت صلاحية الطلب')

for token in ['const raw=await response.text();','JSON.parse(raw)','response.status===419',
              'response.status===401','contentType.includes(\'application/json\')']:
    assert token in SOURCE,'Missing controlled UI response: '+token
assert 'const result=await response.json();' not in SOURCE,'unsafe raw response.json used for publishing'
assert "agent_studio_ajax_request()" in FRONT,'schema failures must be typed JSON'
assert "if($action==='update_agent'&&" in ACTIONS,'successful publish must return JSON'
print('AGENT_PUBLISH_AJAX_RESPONSE_CONTRACT_PASS')
