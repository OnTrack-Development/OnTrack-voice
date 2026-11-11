#!/usr/bin/env python3
"""CI-only SaaS simulator with truthful per-step status.

Executes existing, real business-logic/SQLite fixtures in independent
namespaces. It is not a public host simulator and never places phone calls.
"""
import datetime as dt
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import time

ROOT=Path(__file__).resolve().parents[1]
OUTPUT=ROOT/"dist/gap-map/simulation-report.json"
CASES=[
    ("signup","تسجيل عميل جديد وريسلر والتحقق من الملكية",
     "saas","Signup / membership","tools/test_voice_saas_console.php",
     "VERIFIED_TENANT_REGISTRATION_AND_PASSWORD_RECOVERY_PASS"),
    ("login","تسجيل الدخول وحماية المحاولات واسترجاع كلمة السر",
     "saas","Login / account security","tools/test_auth_login_throttle.php",
     "AUTH_LOGIN_DATABASE_THROTTLE_PASS"),
    ("reseller","صلاحيات الريسيلر واختيار باقاته",
     "saas","Reseller plans","tools/test_saas_phase3_reseller_plan_controls.php",
     "SAAS_PHASE3_RESELLER_PLANS_OWNER_UI_NONREGRESSION_PASS"),
    ("plans","إضافة/تغيير باقة وتجديد وحفظ مدة الاشتراك",
     "saas","Subscriptions","tools/test_v1_site05_plan_transitions.php",
     "SITE05_ATOMIC_SUBSCRIPTION_AND_OWNER_ENTITLEMENTS_PASS"),
    ("flow","Agent وFlow والنشر واسترجاع المسارات",
     "saas","Agent Flow","tools/test_voice_flow_runtime.php",
     "VOICE_FLOW_RUNTIME_STATES_AND_TRACES_PASS"),
    ("knowledge","إضافة معرفة وفهرسة عربية وعزل الحساب",
     "saas","Knowledge Base","tools/test_voice_kb_search_index.php",
     "SITE06_PUBLIC_SCOPED_KNOWLEDGE_SEARCH_PASS"),
    ("call_events","محاكاة حملة وموافقة DNC وأحداث مكالمة دون Dial",
     "shared","Campaign / DNC / Call ledger","tools/test_tenant_workspace.php",
     "TENANT_WORKSPACE_PASS"),
    ("handoff","طلب موظف بشري وACK وإغلاق وهمي للحدث",
     "shared","Human Escalation","tools/test_human_escalation.php",
     "HUMAN_ESCALATION_END_TO_END_PASS")
]
FUTURE=[
    ("real_external_email","إرسال رسالة استرجاع Password فعلية","saas","Mail provider","NOT_TESTED","تحتاج اختبار SMTP خارجي مصرح."),
    ("browser_flow","مستخدم حقيقي داخل Chrome بكامل المسارات","saas","Browser UI","NOT_TESTED","فحوص HTTP/Chrome المستقلة لها CI مختلف."),
    ("kashier_sandbox","تسوية دفع حقيقي على بيئة Kashier","saas","Kashier","NOT_TESTED","HMAC fixture لا يثبت Provider settlement."),
    ("live_call","اتصال فعلي من شريحة الهاتف مع PCM Duplex","shared","Mobile + Voice SaaS","BLOCKED","مؤجل حتى Android build وتجربة صريحة؛ لم تتصل بأي رقم.")
]
SECRET=re.compile(r"(?i)(?:Bearer\s+|password[=:]|secret[=:]|api[_-]?key[=:]|token[=:])\S+")

def safe(message):
    return SECRET.sub("[REDACTED]",message)[-700:]

def main():
    if os.environ.get("CI")!="true" or os.environ.get("GITHUB_ACTIONS")!="true" \
       or os.environ.get("ONTRACK_SIMULATOR_ENABLE")!="1":
        print("SAAS_SIMULATOR_CI_ONLY: refusing execution outside isolated Actions",file=sys.stderr)
        return 3
    results=[]
    with tempfile.TemporaryDirectory(prefix="ontrack-gap-ci-") as tmp:
        for number,(id_,title,area,component,script,marker) in enumerate(CASES):
            ns="sim_"+re.sub(r"[^a-zA-Z0-9]","",os.environ.get("GITHUB_RUN_ID","local"))+"_"+str(number)
            if not re.fullmatch(r"[A-Za-z0-9_]{8,60}",ns):
                raise RuntimeError("invalid CI sandbox namespace")
            env=os.environ.copy()
            env.update(ONTRACK_CI_NAMESPACE=ns,TMPDIR=tmp,ONTRACK_SIMULATOR_MODE="1")
            env.pop("ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED",None)
            env.pop("ONTRACK_TEST_PRIVATE",None)
            start=time.monotonic()
            path=ROOT/script
            detail=""
            if not path.is_file():
                result="FAIL";detail="fixture_missing"
            else:
                try:
                    cmd=subprocess.run(["php",str(path)],cwd=ROOT,env=env,
                        capture_output=True,text=True,errors="replace",timeout=160,check=False)
                    result="PASS" if cmd.returncode==0 and marker in cmd.stdout else "FAIL"
                    if result=="PASS":detail="SQLite isolated + PHP business logic verified"
                    else:detail="exit="+str(cmd.returncode)+" "+safe(cmd.stdout+"\n"+cmd.stderr)
                except subprocess.TimeoutExpired:
                    result="FAIL";detail="fixture_timeout_160s"
            results.append({
                "id":id_,"title":title,"area":area,"owner_component":component,
                "status":result,"detail":detail,"duration_ms":round((time.monotonic()-start)*1000),
                "evidence_script":script,"proof_marker":marker,
                "type":"isolated_ci_fixture_not_host_or_phone"
            })
            print("SIM_STEP",id_,result,flush=True)
        for id_,title,area,component,status,detail in FUTURE:
            results.append({"id":id_,"title":title,"area":area,
                "owner_component":component,"status":status,"detail":detail,
                "type":"manual_or_external_gate_not_run"})
    output={
        "schema":1,"generated_at":dt.datetime.now(dt.timezone.utc).isoformat(),
        "environment":"GitHub hosted isolated PHP/SQLite; no real call or external payment",
        "source_sha":os.environ.get("GITHUB_SHA",""),"run_id":os.environ.get("GITHUB_RUN_ID",""),
        "steps":results,
        "count":{"pass":sum(x["status"]=="PASS" for x in results),
                 "fail":sum(x["status"]=="FAIL" for x in results),
                 "not_tested":sum(x["status"] in ("NOT_TESTED","BLOCKED") for x in results)},
        "disclaimer":"A fixture is not proof of real email, payment, host, phone or account lifecycle."
    }
    OUTPUT.parent.mkdir(parents=True,exist_ok=True)
    OUTPUT.write_text(json.dumps(output,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
    print("SAAS_GAP_MAP_SIMULATION_REPORT_WRITTEN")
    return 1 if output["count"]["fail"] else 0
if __name__=="__main__":sys.exit(main())
