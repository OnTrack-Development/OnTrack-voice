#!/usr/bin/env python3
"""Read-only direct-SSH postdeploy validation, no credentials or user data."""
import pathlib,subprocess,json
root=pathlib.Path('/home/appontrack/public_html/_core')
php='/opt/cpanel/ea-php83/root/usr/bin/php'
files={}
for name in ('Views.php','Actions.php','IoPremiumProvider.php'):
    file=root/name
    if file.is_symlink() or not file.is_file():raise RuntimeError('missing_live_'+name)
    r=subprocess.run([php,'-l',str(file)],capture_output=True,text=True,timeout=12)
    if r.returncode:raise RuntimeError('php_lint_failed_'+name)
    files[name]=file.read_text(encoding='utf-8')
views=files['Views.php'];actions=files['Actions.php'];provider=files['IoPremiumProvider.php']
checks={
'platform_voices_menu':"unset($nav['whatsapp_admin']);" in views and "unset($nav['whatsapp_admin'],$nav['voices'])" not in views,
'reseller_voices_menu':"unset($nav['voices']);" not in views,
'user_voices_menu':"unset($nav['campaigns'],$nav['whatsapp'],$nav['staff']);" in views,
'agent_editor_voice_tab':views.count("select_html('الصوت المستخدم لهذا المساعد','voice',$options,(string)$agent['voice'])")==1,
'agent_create_voice_selector':"select_html('الصوت','voice',$voices)" in views,
'agent_save_and_publish':"نشر إعدادات المساعد" in views,
'admin_catalog':"echo render_io_premium_admin_provider();" in views,
'admin_key_form':"form_start('save_io_premium_provider','/?tab=voices')" in provider,
'api_key_shortcut':"فتح إعدادات IO Premium والصوت" in views,
'action_registered_once':actions.count("case 'save_io_premium_provider':")==1,
'action_restricts_admin':"io_premium_admin_save(" in actions and "active_support())deny();" in actions,
'no_plaintext_key_prefill':"value=\"'.e($key)" not in provider and 'autocomplete="new-password"' in provider,
'same_private_provider_config':"private_path().'/voice-lab/owner-1.json'" in provider,
'encrypted_provider_secret':"seal_secret($key)" in provider,
'provider_requires_fish_stream_test':"io_premium_admin_verify_fish($key,$voiceId,$model)" in provider,
'provider_ready_guard':"io_premium_admin_state()['configured']" in actions
}
broken=[name for name,ok in checks.items() if not ok]
if broken:raise RuntimeError('missing_live_voice_controls:'+','.join(broken))
print(json.dumps({'status':'PASS','checks':len(checks),'php_lint':'PASS',
'admin_voices_visible':True,'agent_voice_selector':True,'provider_key_editor':True,
'plaintext_api_keys_exposed':False},separators=(',',':')))
