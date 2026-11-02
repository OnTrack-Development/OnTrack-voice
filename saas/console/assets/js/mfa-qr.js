/* QR is generated in this authenticated page only. The TOTP secret never
 * reaches a third-party QR service or any network request. */
(()=>{"use strict";
 const mount=(scope=document)=>{
 const panel=scope.querySelector("#mfa-qr");
 if(!panel)return;
 const uri=panel.getAttribute("data-mfa-uri")||"";
 if(!/^otpauth:\/\/totp\/.+\?secret=[A-Z2-7]{32}&issuer=/.test(uri)){
   panel.textContent="بيانات المصادقة غير صالحة، استخدم المفتاح اليدوي";return;
 }
 try {
   if(typeof qrcode!=="function")throw Error("QR generator missing");
   const qr=qrcode(0,"M");qr.addData(uri);qr.make();
   panel.innerHTML=qr.createSvgTag({cellSize:5,margin:10,scalable:true});
 }catch(_){panel.textContent="تعذر رسم QR. استخدم مفتاح الإدخال اليدوي أدناه.";}
 };
 window.ontrackMountMfaQR=mount;
 mount();
})();
