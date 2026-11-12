#!/usr/bin/env python3
"""Read only on approved hosting user: inspect WhatsApp cron without secrets."""
import json,os,subprocess
WORKER='/home/appontrack/public_html/whatsapp-worker.php'
PHP='/opt/cpanel/ea-php83/root/usr/bin/php'
def main():
    if os.getuid()!=2379:raise RuntimeError('wrong_host_user')
    result=subprocess.run(['crontab','-l'],capture_output=True,text=True,timeout=15)
    if result.returncode==0:lines=result.stdout.splitlines()
    elif result.returncode==1 and 'no crontab for' in result.stderr.lower():lines=[]
    else:raise RuntimeError('crontab_read_unavailable')
    active=[line for line in lines if WORKER in line and not line.lstrip().startswith('#')]
    expected='* * * * * '+PHP+' -q '+WORKER
    print(json.dumps({'probe':'v73_wa_cron_read_only','active_entries':len(active),
        'expected_minute_schedule':len(active)==1 and active[0].startswith(expected),
        'duplicate_schedule':len(active)>1,
        'other_entries_preserved':True},separators=(',',':')))
if __name__=='__main__':main()
