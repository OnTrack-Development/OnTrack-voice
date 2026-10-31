<?php
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\VoicePaymentOrder;
use App\Support\KashierGateway;
use App\Support\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoiceCheckoutController extends Controller
{
    public function checkout(Request $request, string $workspace, Plan $plan)
    {
        [$ws, $membership] = WorkspaceContext::resolve($request,$workspace);
        abort_unless(in_array($membership->role,['owner','admin'],true),403);
        abort_unless($plan->status === 'active' && $plan->price_minor !== null
            && $plan->currency === 'EGP' && $plan->billing_interval === 'month'
            && $plan->price_minor > 0, 422, 'هذه الباقة غير متاحة للشراء بعد.');
        $request->validate(['agree_terms'=>['accepted']]);
        $reference = 'OV-'.bin2hex(random_bytes(16));
        $order = VoicePaymentOrder::create([
            'tenant_id'=>$ws->tenant_id,'plan_id'=>$plan->id,
            'reference'=>$reference,'amount_minor'=>$plan->price_minor,
            'currency'=>'EGP','status'=>'pending','mode'=>KashierGateway::mode(),
        ]);
        $email = (string) $request->user()->email;
        try {
            $checkout = KashierGateway::createSession([
                'merchantId'=>KashierGateway::merchantId(),
                'amount'=>sprintf('%d.%02d',intdiv($plan->price_minor,100),$plan->price_minor%100),
                'currency'=>'EGP','order'=>$reference,'type'=>'one-time',
                'display'=>'ar','customer'=>['email'=>$email,'reference'=>(string)$ws->tenant_id],
                'merchantRedirect'=>route('app.plan.index',['workspace'=>$ws->slug]),
                'serverWebhook'=>route('api.kashier.webhook'),
                'description'=>mb_substr('OnTrack Voice — '.$plan->name,0,100),
            ]);
            $order->update(['kashier_session_id'=>$checkout['session_id']]);
            return redirect()->away($checkout['url']);
        } catch (\Throwable $e) {
            Log::warning('kashier_checkout_failed',['order_id'=>$order->id,'type'=>get_class($e)]);
            $order->update(['status'=>'failed']);
            return back()->withErrors(['checkout'=>'تعذر فتح صفحة الدفع الآمنة. لم يتم تفعيل أي اشتراك أو خصم من داخل OnTrack.']);
        }
    }
}
