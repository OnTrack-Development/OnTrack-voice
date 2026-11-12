<?php

namespace App\Services\Billing;

use App\Services\Console\ConsoleContextService;
use App\Services\Security\VaultService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class BillingService
{
    public function __construct(
        private readonly VaultService $vault,
        private readonly ConsoleContextService $ctx,
    ) {}

    public function activeSubscription(int $accountId): ?array
    {
        $now = $this->ctx->now();
        $row = DB::table('subscriptions as s')
            ->join('plans as p', 'p.id', '=', 's.plan_id')
            ->where('s.account_id', $accountId)
            ->where('s.status', 'active')
            ->where('s.starts_at', '<=', $now)
            ->where('s.ends_at', '>', $now)
            ->orderByDesc('s.id')
            ->select('s.*', 'p.name as plan_name', 'p.call_quota', 'p.site_limit', 'p.agent_limit', 'p.staff_limit', 'p.price_minor', 'p.currency')
            ->first();

        return $row ? (array) $row : null;
    }

    public function plansForAccount(array $account): array
    {
        $kind = ($account['kind'] ?? '') === 'reseller' ? 'reseller' : 'user';
        $parentId = (int) ($account['parent_id'] ?? 0);

        $rows = DB::table('plans')
            ->leftJoin('reseller_plan_owners as rpo', 'rpo.plan_id', '=', 'plans.id')
            ->where('plans.status', 'active')
            ->whereIn('plans.kind', [$kind, 'customer'])
            ->select('plans.*', 'rpo.reseller_account_id')
            ->orderBy('plans.price_minor')
            ->orderBy('plans.id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            if ($parentId > 0) {
                if ((int) ($row->reseller_account_id ?? 0) === $parentId) {
                    $out[] = (array) $row;
                }
            } elseif ($row->reseller_account_id === null) {
                $out[] = (array) $row;
            }
        }

        // Fallback to platform plans if reseller hasn't customized any plans
        if (empty($out) && $parentId > 0) {
            $out = $rows->filter(fn ($r) => $r->reseller_account_id === null)->map(fn ($r) => (array) $r)->values()->all();
        }

        return $out;
    }

    public function invoicesForAccount(int $accountId): array
    {
        return DB::table('billing_invoices')
            ->where('account_id', $accountId)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function selectFreePlan(int $accountId, int $planId): void
    {
        $plan = DB::table('plans')->where('id', $planId)->where('status', 'active')->first();
        if (! $plan || (int) ($plan->price_minor ?? -1) !== 0) {
            throw new InvalidArgumentException('التفعيل المباشر متاح للباقات المجانية فقط. استخدم بوابة الدفع للباقات المدفوعة.');
        }
        $now = $this->ctx->now();
        $end = gmdate('Y-m-d H:i:s', time() + 30 * 86400);

        DB::transaction(function () use ($accountId, $planId, $now, $end): void {
            DB::table('subscriptions')
                ->where('account_id', $accountId)
                ->where('status', 'active')
                ->update(['status' => 'replaced']);

            DB::table('subscriptions')->insert([
                'account_id' => $accountId,
                'plan_id' => $planId,
                'status' => 'active',
                'starts_at' => $now,
                'ends_at' => $end,
                'started_at' => $now,
                'renews_at' => $end,
            ]);
        });
    }

    public function createManualInvoice(int $accountId, int $planId, array $user, string $proofNote = ''): int
    {
        $plan = DB::table('plans')->where('id', $planId)->where('status', 'active')->first();
        $account = $this->ctx->account($accountId);
        if (! $plan || ! $account) {
            throw new InvalidArgumentException('الباقة أو الحساب غير متاح.');
        }

        $minor = (int) ($plan->price_minor ?? 0);
        if ($minor <= 0) {
            throw new InvalidArgumentException('هذه الباقة مجانية؛ فعّلها مباشرة.');
        }

        $now = $this->ctx->now();
        $due = gmdate('Y-m-d H:i:s', time() + 7 * 86400);
        $reference = 'man_'.bin2hex(random_bytes(12));

        return DB::transaction(function () use ($accountId, $account, $plan, $planId, $user, $minor, $reference, $proofNote, $now, $due): int {
            $orderId = DB::table('orders')->insertGetId([
                'account_id' => $accountId,
                'external_order_id' => $reference,
                'plan_id' => $planId,
                'customer_name' => (string) ($user['name'] ?? $account['name']),
                'customer_email' => (string) ($user['email'] ?? ''),
                'amount_minor' => $minor,
                'status' => 'pending_manual_review',
                'payment_reference' => mb_substr(trim($proofNote), 0, 180) ?: null,
                'created_at' => $now,
            ]);

            $invoiceNo = 'INV-'.gmdate('Ym').'-'.str_pad((string) $orderId, 5, '0', STR_PAD_LEFT);
            $issuerId = (int) ($account['parent_id'] ?: 1);
            $issuer = $this->ctx->account($issuerId);

            $invoiceId = DB::table('billing_invoices')->insertGetId([
                'invoice_no' => $invoiceNo,
                'account_id' => $accountId,
                'issuer_account_id' => $issuerId,
                'order_id' => $orderId,
                'plan_id' => $planId,
                'bill_to_name' => (string) ($account['name'] ?? $user['name']),
                'bill_to_email' => (string) ($user['email'] ?? ''),
                'issuer_name' => (string) ($issuer['brand_name'] ?? $issuer['name'] ?? 'IO Voice'),
                'plan_name' => (string) $plan->name,
                'amount_minor' => $minor,
                'currency' => (string) ($plan->currency ?: 'EGP'),
                'status' => 'pending',
                'issued_at' => $now,
                'due_at' => $due,
                'payment_reference' => mb_substr(trim($proofNote), 0, 180) ?: null,
            ]);

            DB::table('billing_invoice_events')->insert([
                'invoice_id' => $invoiceId,
                'actor_user_id' => (int) ($user['id'] ?? 0),
                'event' => 'manual.requested',
                'reference' => mb_substr(trim($proofNote), 0, 180) ?: null,
                'created_at' => $now,
            ]);

            return $invoiceId;
        });
    }

    public function startKashierCheckout(int $accountId, int $planId, array $user): string
    {
        $cfg = $this->vault->readJsonFile('kashier.json', []);
        if (empty($cfg['merchant_id']) || empty($cfg['secret_key']) || empty($cfg['payment_api_key'])) {
            throw new RuntimeException('بوابة Kashier غير مفعّلة في خزنة المنصة بعد؛ يمكنك استخدام طلب الفاتورة اليدوية.');
        }
        $plan = DB::table('plans')->where('id', $planId)->where('status', 'active')->first();
        if (! $plan || (int) ($plan->price_minor ?? 0) < 1) {
            throw new InvalidArgumentException('الباقة غير صالحة للدفع الإلكتروني.');
        }

        $minor = (int) $plan->price_minor;
        $amount = intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
        $reference = 'ovc_'.bin2hex(random_bytes(16));

        $orderId = DB::table('orders')->insertGetId([
            'account_id' => $accountId,
            'external_order_id' => $reference,
            'plan_id' => $planId,
            'customer_name' => (string) ($user['name'] ?? ''),
            'customer_email' => (string) ($user['email'] ?? ''),
            'amount_minor' => $minor,
            'status' => 'pending_payment',
            'created_at' => $this->ctx->now(),
        ]);

        $payload = [
            'expireAt' => gmdate('Y-m-d\TH:i:s\Z', time() + 900),
            'maxFailureAttempts' => 3,
            'paymentType' => 'credit',
            'amount' => $amount,
            'currency' => 'EGP',
            'order' => $reference,
            'merchantId' => $cfg['merchant_id'],
            'merchantRedirect' => 'https://'.config('ontrack.public_domain').'/?tab=billing',
            'serverWebhook' => 'https://'.config('ontrack.public_domain').'/api/billing/kashier/webhook',
            'display' => 'ar',
            'type' => 'one-time',
            'allowedMethods' => 'card,wallet',
            'interactionSource' => 'ECOMMERCE',
            'description' => 'IO Voice Subscription - '.$plan->name,
            'customer' => ['email' => (string) ($user['email'] ?? '')],
        ];

        $endpoint = ($cfg['mode'] ?? 'test') === 'live'
            ? 'https://api.kashier.io/v3/payment/sessions'
            : 'https://test-api.kashier.io/v3/payment/sessions';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Authorization: '.$cfg['secret_key'],
                'api-key: '.$cfg['payment_api_key'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (! is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('تعذر فتح جلسة الدفع الإلكتروني في Kashier (HTTP '.$status.').');
        }
        $reply = json_decode($raw, true);
        $url = (string) ($reply['sessionUrl'] ?? '');
        if ($url === '' || ! str_starts_with($url, 'https://payments.kashier.io/session/')) {
            throw new RuntimeException('استجابة رابط Kashier غير صالحة.');
        }

        return $url;
    }

    public function approveManualInvoice(int $invoiceId, int $actorUserId, string $reference): void
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new InvalidArgumentException('أدخل مرجع السداد قبل اعتماد الفاتورة.');
        }
        $inv = DB::table('billing_invoices')->where('id', $invoiceId)->first();
        if (! $inv || $inv->status !== 'pending') {
            throw new InvalidArgumentException('الفاتورة غير متاحة للاعتماد.');
        }
        $now = $this->ctx->now();
        $end = gmdate('Y-m-d H:i:s', time() + 30 * 86400);

        DB::transaction(function () use ($inv, $invoiceId, $actorUserId, $reference, $now, $end): void {
            DB::table('billing_invoices')->where('id', $invoiceId)->update([
                'status' => 'paid',
                'paid_at' => $now,
                'payment_reference' => mb_substr($reference, 0, 180),
            ]);
            DB::table('orders')->where('id', $inv->order_id)->update([
                'status' => 'paid',
                'payment_reference' => mb_substr($reference, 0, 180),
            ]);
            DB::table('subscriptions')->where('account_id', $inv->account_id)->where('status', 'active')->update(['status' => 'replaced']);
            DB::table('subscriptions')->insert([
                'account_id' => $inv->account_id,
                'plan_id' => $inv->plan_id,
                'status' => 'active',
                'starts_at' => $now,
                'ends_at' => $end,
                'started_at' => $now,
                'renews_at' => $end,
            ]);
            DB::table('billing_invoice_events')->insert([
                'invoice_id' => $invoiceId,
                'actor_user_id' => $actorUserId,
                'event' => 'manual.approved',
                'reference' => mb_substr($reference, 0, 180),
                'created_at' => $now,
            ]);
        });
    }
}
