<?php
declare(strict_types=1);
require_once __DIR__.'/AiRelay.php';
require_once __DIR__.'/GoogleTenantKeys.php';

/** Owner-scoped Google Live BYOK. Never render or transmit a master key. */
function render_google_voice_key_settings(array $a):void {
    $id=(int)$a['account_id'];
    if($a['kind']==='platform')return;
    google_voice_key_schema();
    $items=ai_relay_rows('voice',false,$id);
    $editable=in_array($a['role'],['owner','admin'],true)&&!active_support();
    $unlimited=$a['kind']==='reseller';
    $limit=$unlimited?0:google_voice_key_limit($id);
    $canAdd=$editable&&($unlimited||count($items)<$limit);
    $source=google_voice_pool_owner($id);
    $state=$source===$id?'مفاتيح حسابك مستقلة تمامًا':
        ($source===ai_relay_platform()?'تستخدم مجموعة مفاتيح المنصة':'تستخدم مجموعة مفاتيح الريسيلر');
    $html='<p class="dim">مزوّد المكالمات: Google Gemini Live فقط. '.e($state).
        '. عند إضافة مفتاحك الخاص لن تستخدم مفاتيح المنصة أو الريسيلر لهذا الحساب.'.
        ' جميع المفاتيح محفوظة مشفرة، ولا تظهر في المتصفح بعد الحفظ.</p>'.
        '<p><strong>'.count($items).'</strong> مفتاح محفوظ · '.
        ($unlimited?'عدد غير محدود للريسيلر':('الحد حسب الباقة: '.(int)$limit)).'</p>';
    if($canAdd){
        $html.=form_start('relay_add','/?tab=settings').
            '<input type="hidden" name="scope" value="voice">'.
            '<input type="hidden" name="relay_driver" value="google_live">'.
            '<div class="form-grid">'.
            '<label class="field">اسم المفتاح<input name="relay_label" maxlength="100" required placeholder="Google Project 1"></label>'.
            '<label class="field">موديل Google Live<input name="relay_model" maxlength="120" dir="ltr" required value="gemini-3.8-live"></label>'.
            '<label class="field">API Key<input type="password" name="relay_api_key" maxlength="512" required autocomplete="off"></label>'.
            '</div>'.submit_btn('إضافة مفتاح Google');
    }elseif($editable&&!$unlimited){
        $html.='<p class="dim">وصلت للحد المسموح من مفاتيح Google في باقتك، أو الباقة لا تسمح بمفاتيح خاصة.</p>';
    }
    foreach($items as $item){
        $keyId=(int)$item['id'];
        $cool=!empty($item['cooldown_until'])&&$item['cooldown_until']>now();
        $label=e((string)$item['label']);$model=e((string)$item['model']);
        $status=$cool?'قيد التهدئة':((int)$item['enabled']===1?'مفعّل':'متوقف');
        $html.='<section class="card"><strong>'.$label.'</strong> <small>'.$model.'</small>'.
            '<p class="dim">#'.$keyId.' · '.e($status).
            ' · آخر حالة: '.e((string)$item['last_status']).'</p>';
        if($editable){
            $html.=form_start('relay_test','/?tab=settings','class="inline-form"').
                '<input type="hidden" name="relay_id" value="'.$keyId.'">'.
                '<button class="btn small primary" type="submit">اختبار المفتاح</button></form>';
            foreach(['up'=>'تقديم','down'=>'تأخير','toggle'=>(int)$item['enabled']===1?'إيقاف':'تفعيل',
                'reset'=>'إعادة ضبط','delete'=>'حذف'] as $operation=>$title){
                $html.=form_start('relay_manage','/?tab=settings','class="inline-form"').
                    '<input type="hidden" name="relay_id" value="'.$keyId.'">'.
                    '<input type="hidden" name="relay_operation" value="'.e($operation).'">'.
                    '<button class="btn small" type="submit">'.e($title).'</button></form>';
            }
            $html.='<details><summary>تعديل المفتاح</summary>'.
                form_start('relay_edit','/?tab=settings').
                '<input type="hidden" name="relay_id" value="'.$keyId.'">'.
                '<label class="field">الاسم<input name="relay_label" maxlength="100" required value="'.$label.'"></label>'.
                '<label class="field">موديل Google<input name="relay_model" maxlength="120" required dir="ltr" value="'.$model.'"></label>'.
                '<label class="field">مفتاح بديل (اختياري)<input type="password" name="relay_api_key" maxlength="512" autocomplete="off"></label>'.
                submit_btn('حفظ') . '</details>';
        }
        $html.='</section>';
    }
    echo card('مفاتيح Google Live الخاصة بحسابك',$html);
}
