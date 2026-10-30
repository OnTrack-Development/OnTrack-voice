# تحديثات OnTrack عبر الهواء

## النطاق الفعلي

تثبيت تأسيسي واحد0.1.6-ota، بنفسpackage com.ontrack.voiceproof وبنفسشهادة0.1.1/0.1.4/0.1.5. بعده تُحمّلواجهةمركزالاتصال HTML/CSS/JavaScriptمحليًا وتُحدّثتلقائيًا عندفتحها. يمكنتعديلتصميمها، أزرارها، التحققمنالمدخلات وتسلسلالواجهةضمنأوامرالطبقةالأصليةالمحددة؛ إعداداتالمهمة/الصوت منالخادمتظلعملًا مستقلًا قائمًا.

محركالصوتKotlin/Java ومساعدshell وصلاحياتAndroid وSDKهاتحتاجAPKجديد؛ لايوجدادعاءبتحديثهابدونتثبيتأوبأنOTAيعطيناصلاحياتنظامجديدة. ليستكلأجزاءالتطبيققابلةللتحديثبالهواء، وشاشةالتشخيصالموروثةتظلNative. لايُقدّمذلكدليلًا علىكيفيةعملThndrنفسه.

## الأمان والتعافي

- latest.jsonيحملpayloadموقعةRSA/SHA256بهُويةتوقيعAPKالموجودة. المفتاحالخاصلايخرجمنcacheالتوقيعفيGitHubActions. العميليربطالثقةبشهادةAPKالمثبت.
- runtime1 وmin_native/max_nativeيمنعونتحميلواجهةغيرمتوافقة؛ revisionمتزايد، hashملفHTMLوحجمهومسارهتُفحصقبلالتفعيل.
- لاتحميلDEX/SO/JARعلىالهاتف. لاطلبصلاحياتإضافية ولاتعطيلPlayProtect. خدمةقراءةإشعاراتالتطبيقاتالأخرىالمحذوفةتظلغيــرموجودة.
- HTMLمحليموثوقفقطفيWebView، ملفاتوشبكاتوملاحةوiframesومصادرخارجيةمحجوبة؛ JSbridgeمحدودلأوامرstart/stop/ready/checkUpdates/rollback/diagnostics/admin. لاshellعامأوقراءةملفاتعامةمنJS.
- يُحفظHTMLوالحالةذريًا بتخزينالتطبيقالخاص. pendingلايُؤكَّدإلاعندready؛ مهلة10ثوانٍأوبدايةالتشغيلالتاليةبعدتعطلترجعالواجهةالسابقةوتمنعإعادةنفسrevisionالفاشلة. نسخةمضمنةداخلAPKمتاحةعندغيابالإنترنت.
- التحديثلايوقفخدمةالمكالمة؛ إذاكانتالحالةالمعروفةringing/offhookيؤجلتفعيلهحتىانتهاءالمكالمة. لاادعاءباختبارهعلىالهاتفحتىنتيجةالمالك.
- بياناتالاقترانوالمفتاحتظلSharedPreferencesالموجودة؛ لاتثبيتجديدبهويةمختلفةولامسحبيانات.

## النشر

بعداختباراتالبناء،توقيعAPKوفحصmanifestالنهائي،تُوقّعحزمةUIبالمفتاحنفسه. jobمنفصلبصلاحيةContentsWriteيحمّلالartifactالمتحققمنه،يفحصشهادةالتوقيعوالتوقيعوالبصماتويضيفمهمةإلىفرعontrack-host-link. يستخدمGITHUB_TOKENالخاصبالـworkflow؛ لاPATجديدولاقراءةconfigالهوست. يحافظعلىالطابورإنوجدتمهمةمجهولة/معلقة،ويتطلبتقريرًامطابقًالبصمةrawqueueالسابقة. طولالمهمة<=16KiB،تنفيذ<=25ثانية.

العاملالموجودينشرHTMLثابتًاوlatest.jsonفقطفي/home/appontrack/public_html/saas/mobile/updates،والpointerيُنشرآخرشيء. يحتفظبنسخةخاصةمنالـmanifestالسابق،ويرفضrevisionأقلأوإعادةاستعمالرقمبمحتوىمختلف. لاتغييرAPIالجذرفيهذهالمهمة. نجاحqueueلايعنيالنشر؛ يلزمتقريرعاملومقارنةHTTP.

لتحديثواجهةلاحقًا: عدلsaas/mobile/ota/ui.html، وزدrevisionفيrelease.json،وادفعmain. لأنالبناءالحالييصدرNativeوOTAمعًا،سيظهرAPKأيضًافيartifactلكنالهاتفلايحتاجتثبيتهلتحديثUIالمتوافق. نشرrollbackعنالخادميستخدمrevisionأعلىيشيرلمحتوىواجهةسابقة؛ زرالعودةمحليًامتاحأيضًا.

## حدود التحقق الحالي

قبلالبناء:sourceoverlayنظيف،حارسmanifestنجح،واختباراتPythonللعزلوحفظالمهامالمعلقةومنعخفضrevisionنجحت. يلزماختباراتKotlinللـsignatureوالتوافق،APKفعلي،نشرHTTPوفحصه،ثمفتحالتطبيقعلىالهاتفلإثباتhotupdateوالتعافي. مشكلةMOBILE-006الهاتف401مازالتغيرمحلولة؛OTAليستدليلًاعلىإصلاحالمصادقةأوSIMduplex.

## المصادرالرسمية

https://developer.android.com/privacy-and-security/risks/insecure-webview-native-bridges
https://developer.android.com/privacy-and-security/risks/dynamic-code-loading
https://developer.android.com/guide/playcore/in-app-updates
https://developer.android.com/reference/android/content/pm/PackageInstaller.SessionParams#setRequireUserAction(int)
