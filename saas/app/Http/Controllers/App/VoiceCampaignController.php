<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\TenantMembership;
use App\Models\VoiceCampaign;
use App\Models\VoiceCampaignEntry;
use App\Models\Workspace;
use App\Support\VoiceEntitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class VoiceCampaignController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless(VoiceEntitlements::has((int)$ws->tenant_id, 'voice.campaigns'),403,'هذه الميزة غير متاحة في الباقة الحالية.');
        return view('app.voice.index', ['workspace'=>$ws,'campaigns'=>VoiceCampaign::where('tenant_id',$ws->tenant_id)->where('workspace_id',$ws->id)->withCount('entries')->latest()->get()]);
    }

    public function store(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless(VoiceEntitlements::has((int)$ws->tenant_id, 'voice.campaigns'),403,'هذه الميزة غير متاحة في الباقة الحالية.');
        $data=$request->validate(['name'=>['required','string','max:160'],'timezone'=>['required','string','max:64'],'delay_seconds'=>['required','integer','min:0','max:86400'],'max_attempts'=>['required','integer','min:1','max:10']]);
        VoiceCampaign::create(['tenant_id'=>$ws->tenant_id,'workspace_id'=>$ws->id,'status'=>'draft',...$data]);
        return back()->with('status','تم حفظ مهمة الاتصال كمسودة. نقل المكالمات الحقيقي ما زال يحتاج إثبات جهاز/قناة.');
    }

    public function show(Request $request, string $workspace, VoiceCampaign $campaign)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless(VoiceEntitlements::has((int)$ws->tenant_id, 'voice.campaigns'),403,'هذه الميزة غير متاحة في الباقة الحالية.');
        abort_unless($campaign->tenant_id === $ws->tenant_id && $campaign->workspace_id === $ws->id, 404);
        return view('app.voice.show', [
            'workspace' => $ws,
            'campaign' => $campaign,
            'entries' => $campaign->entries()->latest()->paginate(25),
            'summary' => [
                'total' => $campaign->entries()->count(),
                'pending' => $campaign->entries()->where('queue_status','pending')->count(),
                'invalid' => $campaign->entries()->where('queue_status','invalid')->count(),
            ],
        ]);
    }

    public function import(Request $request, string $workspace, VoiceCampaign $campaign)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless(VoiceEntitlements::has((int)$ws->tenant_id, 'voice.campaigns'),403,'هذه الميزة غير متاحة في الباقة الحالية.');
        abort_unless($campaign->tenant_id === $ws->tenant_id && $campaign->workspace_id === $ws->id, 404);
        abort_unless(VoiceEntitlements::has((int)$ws->tenant_id,'voice.contact_import'),403,'استيراد الأرقام غير متاح في هذه الباقة.');
        $data = $request->validate(['rows' => ['required','string','max:200000']]);

        $lines = collect(preg_split('/\R+/u', trim($data['rows'])) ?: [])
            ->map(fn ($line) => trim($line, " \t\r\n"))
            ->filter();

        $existing = VoiceCampaignEntry::where('tenant_id', $ws->tenant_id)->pluck('phone_e164')->flip();
        $added = 0; $invalid = 0; $duplicate = 0;

        foreach ($lines as $line) {
            // One number per line, or "name,number" — do not guess-fix corrupted Excel numbers.
            [$name, $rawNumber] = str_contains($line, ',') ? array_pad(explode(',', $line, 2), 2, '') : [null, $line];
            $rawNumber = trim((string) $rawNumber, " \t");
            $normalized = $this->normalize($rawNumber);
            if ($normalized === null) {
                $invalid++;
                VoiceCampaignEntry::create(['tenant_id'=>$ws->tenant_id,'campaign_id'=>$campaign->id,'phone_e164'=>$rawNumber,'display_name'=>$name ?: null,'source_fields'=>['original'=>$line],'queue_status'=>'invalid']);
                continue;
            }
            if (isset($existing[$normalized]) || VoiceCampaignEntry::where('campaign_id',$campaign->id)->where('phone_e164',$normalized)->exists()) {
                $duplicate++;
                continue;
            }
            $existing[$normalized] = true;
            VoiceCampaignEntry::create(['tenant_id'=>$ws->tenant_id,'campaign_id'=>$campaign->id,'phone_e164'=>$normalized,'display_name'=>$name ?: null,'source_fields'=>['original'=>$line],'queue_status'=>'pending','next_attempt_at'=>now()]);
            $added++;
        }

        return back()->with('status', sprintf('استوردنا %d رقمًا، %d خاطئًا، و%d مكررًا (حُفظت القيم الأصلية دون تخمين إصلاحها).', $added, $invalid, $duplicate));
    }

    private function normalize(string $raw): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', $raw) ?: '';
        // E.164-like: +country... with 8..15 digits; anything else is flagged, not silently fixed.
        if (preg_match('/^\+[1-9]\d{7,14}$/', $digits)) return $digits;
        if (preg_match('/^[1-9]\d{7,14}$/', $digits)) return '+'.$digits;
        return null;
    }

    private function context(Request $request,string $workspace): array
    {
        $ws=Workspace::where('slug',$workspace)->firstOrFail(); $member=TenantMembership::where('user_id',$request->user()->id)->where('tenant_id',$ws->tenant_id)->where('status','active')->first(); abort_if(!$member,403,'غير مصرح لك بالوصول إلى هذه المساحة.'); return [$ws,$member];
    }
}