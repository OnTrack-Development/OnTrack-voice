#!/usr/bin/env python3
"""Safe Ed25519 host-link job contract tests; no network, no host commands."""
import datetime as dt
import importlib.util
from pathlib import Path
import subprocess
import tempfile


def mod(name, relative):
    p=Path(__file__).resolve().parents[1]/relative
    spec=importlib.util.spec_from_file_location(name,p)
    obj=importlib.util.module_from_spec(spec)
    spec.loader.exec_module(obj)
    return obj


worker=mod("hostlink_worker","ops/host-link/worker.py")
signer=mod("hostlink_signer","ops/host-link/sign_job.py")


def expect_raises(fn,label):
    try:
        fn()
    except (ValueError,RuntimeError):
        return
    raise AssertionError("Host-link failed to reject "+label)


with tempfile.TemporaryDirectory(prefix="ontrack-hostlink-test-") as tmp:
    home=Path(tmp)
    private=home/"signing.pem"
    public=home/"signing-public.pem"
    subprocess.run(["openssl","genpkey","-algorithm","ED25519",
                    "-out",str(private)],check=True,capture_output=True)
    private.chmod(0o600)
    subprocess.run(["openssl","pkey","-in",str(private),
                    "-pubout","-out",str(public)],check=True,capture_output=True)
    now=dt.datetime.now(dt.timezone.utc)
    job={"schema":1,"id":"signed-safe-check-0001","target":"test-host",
         "action":"exec","argv":["/bin/true"],"cwd":str(home),
         "timeout_seconds":2,
         "created_at":now.isoformat(),
         "expires_at":(now+dt.timedelta(minutes=2)).isoformat()}
    cfg={"target":"test-host","allow_exec":True,"account_home":str(home),
         "project_root":str(home),"operator_pubkey_path":str(public),
         "allowed_exec_argv":[["/bin/true"]]}
    signed=signer.sign(job,str(private))
    worker.validate_job(signed,cfg)
    expect_raises(lambda:worker.validate_job(signed,dict(cfg,allowed_exec_argv=[])),
                  "missing local exec allowlist")
    signed_other=signer.sign(dict(job,argv=["/bin/false"]),str(private))
    expect_raises(lambda:worker.validate_job(signed_other,cfg),
                  "valid signer cannot invoke a command outside exact local allowlist")
    signed_arguments=signer.sign(dict(job,argv=["/bin/true","--unexpected"]),str(private))
    expect_raises(lambda:worker.validate_job(signed_arguments,cfg),
                  "allowed binary with unauthorized arguments")
    expect_raises(lambda:worker.validate_job(signed,dict(cfg,allowed_exec_argv=[["/bin/true",42]])),
                  "invalid local argv allowlist")
    expect_raises(lambda:worker.validate_job(job,cfg),"unsigned exec")
    changed=dict(signed,argv=["/bin/false"])
    expect_raises(lambda:worker.validate_job(changed,cfg),"modified signed argv")
    changed=dict(signed,target="different-host")
    expect_raises(lambda:worker.validate_job(changed,cfg),"cross-host replay")
    changed=dict(signed,expires_at=(now+dt.timedelta(hours=2)).isoformat())
    expect_raises(lambda:worker.validate_job(changed,cfg),"modified expiry")
    expect_raises(lambda:worker.validate_job(signed,dict(cfg,allow_exec=False)),
                  "operator disabled exec")
    expect_raises(lambda:worker.validate_job(signed,dict(cfg,operator_pubkey_path=str(home/"missing.pem"))),
                  "missing public verifier")
    # A probe remains read-only under its existing policy; signing is
    # mandatory only if the stopped host-link is explicitly enabled for exec.
    probe=dict(job,action="probe")
    del probe["argv"]
    worker.validate_job(probe,dict(cfg,allow_exec=False))
print("HOST_LINK_SIGNED_EXEC_TAMPER_ALLOWLIST_AND_REPLAY_PASS")
