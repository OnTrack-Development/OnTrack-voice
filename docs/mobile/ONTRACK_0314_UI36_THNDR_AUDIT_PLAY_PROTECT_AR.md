# OnTrack Voice 0.3.14 · UI OTA 36 · فحص Thndr وPlay Protect

**التاريخ:** 2026-11-04 · **الحالة:** نشر السيرفر مثبت، اختبار جهاز المالك لم يتم التحقق منه.

## دليل الفحص
- فُحص ملف Thndr 12.56.0 الذي قدمه المالك كأرشيف .apks؛ لا تنسخ أو توزع ملفاته.
- ملفات **React Native / Hermes** موجودة (libreactnative.so / libhermes.so)، و**CodePushHash** داخل assets وواجهات CodePush في DEX. البنية تسمح بتحديث JS/assets؛ لا تثبت توقيت وتهيئة CodePush وقت التشغيل.
- يوجد ملف Lottie باسم `res/raw/thndranimatedlogo.json` بمعدل 60 إطار/ثانية من 0 إلى 751، أي نحو 12.52 ثانية، مع نصوص انتظار عربية وإنجليزية متغيرة.
- تصميم OnTrack بديل أصلي بهوية الشركة. لا تستخدم شعار Thndr أو أصول Lottie الخاصة به.

## نشر OnTrack المؤكد
- Native Android: **0.3.14 / versionCode 37 / package com.ontrack.voiceproof**.
  - Build + targeted Kotlin/daemon tests + R8 + APK signature: Actions `41771861709`, success.
  - APK SHA-256: `2f8a93fbbd18c49403c4c90edbdeea7fd9f360270d0cc84cac91570b4fd41a4f`.
  - APK bytes: `10935844`.
  - Native publish: Actions `41773770519`, host receipt `mobile-native-41773770519`, **success / exit_code 0**, version 37 and hash match.
- UI OTA: **revision 36** / compatibility native versionCode **34–37**.
  - Signed UI verification and host receipt: Actions `41774082433`, job `mobile-ota-41774082433`, **success / exit_code 0**.
  - UI HTML SHA-256: `3a5052af44defea16d646535cd4d021f31c8d8017779bc7d73115972bb031986`.
- Native OnTrackLaunchScreen includes original animated logo (scale/fade/glint), with true signed-HTML download byte progress and startup readiness gate.
- OTA36 hides the redundant HTML `#otSplash` so users do not see a second fake spinner after native splash.

## Play Protect: facts and safe user action
- Screenshot shows **App scan recommended / Play Protect hasn't seen this app before**. This is an Android/Google owned security flow, not a branded app modal.
- During internal sideload, use **Scan app** and follow the result. Never disable Play Protect or circumvent an actual malware block.
- Current APK release is signed with an explicitly pinned original `androiddebugkey` identity for existing internal devices. Rotating it without a documented migration would break in-place upgrades and potentially strand device data.
- Production remediation remains **OPEN**: trusted release signing + Play App Signing / internal testing channel, data/pairing migration plan, independent privacy/permission and security review. Do not call this complete without Play Console release evidence.
- Google documentation: https://support.google.com/work/android/answer/15162069 and https://developer.android.com/studio/publish/app-signing.

## Verification boundary
Host receipts prove only deployment, not that the Xiaomi phone installed Android 0.3.14, completed Google Play Protect scanning, showed the new animation, or passed live SIM audio E2E. Obtain explicit device-side evidence before marking device completion.
