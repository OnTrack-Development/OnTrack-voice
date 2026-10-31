<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\VoicePaymentOrder;
use App\Support\KashierGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KashierWebhookController extends Controller
{
    public function receive(Request $request)
    {
        if ($request->getContent() === '' || strlen($request->getContent()) > 65536) return response('',400);
        $event = json_decode($request->getContent(), true);
        if (!is_array($event) || !KashierGateway::validWebhook($event, $request->header('x-kashier-signature',''))) {
            return response('',401);
        }
        $data = $event['data'];
        $reference = (string)($data['merchantOrderId'] ?? '');
        if (!preg_match('/^OV-[0-9a-f]{32}$/D', $reference)) return response('',422);
        $status = (string)($data['status'] ?? '');
        $eventType = (string)($event['event'] ?? '');
        if (!in_array($status, ['SUCCESS','FAILURE','PENDING'], true)) return response('',422);
        if (!in_array($eventType, ['pay','refund','partial_refund','reject','reversal','void','authorize','capture'], true)) return response('',422);
        $transactionId = (string) ($data['transactionId'] ?? '');
        if ($transactionId === '' || strlen($transactionId) > 120) return response('',422);
        $money = self::minor((string)($data['amount'] ?? ''));
        if ($money === null) return response('',422);
        return DB::transaction(function () use ($reference,$data,$money,$status,$eventType,$transactionId) {
            $order = VoicePaymentOrder::where('reference',$reference)->lockForUpdate()->first();
            if (!$order) return response('',404);
            if ($order->mode !== KashierGateway::mode()
                || $order->amount_minor !== $money
                || $order->currency !== (string)($data['currency'] ?? '')) return response('',422);
            if ($order->status === 'refunded' || $order->status === 'paid' && $eventType === 'pay') return response('',200);
            if (in_array($eventType,['refund','reversal','void'],true) && $status === 'SUCCESS') {
                if ($order->status === 'paid') {
                    $order->update(['status'=>'refunded']);
                    Subscription::where('tenant_id',$order->tenant_id)
                        ->where('voice_payment_order_id',$order->id)
                        ->where('status','active')->update(['status'=>'cancelled']);
                }
                return response('',200);
            }
            if ($eventType !== 'pay' || $status !== 'SUCCESS') return response('',200);
            if (VoicePaymentOrder::where('kashier_transaction_id',$transactionId)
                    ->where('id','!=',$order->id)->exists()) return response('',409);
            $order->update(['status'=>'paid','kashier_transaction_id'=>$transactionId,'paid_at'=>now()]);
            Subscription::where('tenant_id',$order->tenant_id)->where('status','active')
                ->update(['status'=>'expired']);
            Subscription::create([
                'tenant_id'=>$order->tenant_id, 'plan_id'=>$order->plan_id,
                'voice_payment_order_id'=>$order->id,
                'status'=>'active','started_at'=>now(),'renews_at'=>now()->addMonthNoOverflow(),
            ]);
            return response('',200);
        });
    }

    private static function minor(string $amount): ?int
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,10})(?:\.([0-9]{1,2}))?$/D',$amount,$m)) return null;
        return ((int)$m[1])*100+(int)str_pad($m[2]??'0',2,'0');
    }
}
