<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\HandoffRecord;
use App\Models\Message;
use App\Models\TenantAutomationSetting;
use App\Models\TenantMembership;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        $query = Conversation::where('tenant_id', $ws->tenant_id)->where('workspace_id', $ws->id);
        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel')->toString());
        }
        return view('app.inbox.index', [
            'workspace' => $ws,
            'channel' => $request->string('channel')->toString(),
            'conversations' => $query->latest()->get(),
        ]);
    }

    public function show(Request $request, string $workspace, Conversation $conversation)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless($conversation->tenant_id === $ws->tenant_id && $conversation->workspace_id === $ws->id, 404);
        return view('app.inbox.show', ['workspace' => $ws, 'conversation' => $conversation->load('messages')]);
    }

    public function reply(Request $request, string $workspace, Conversation $conversation)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless($conversation->tenant_id === $ws->tenant_id && $conversation->workspace_id === $ws->id, 404);
        $data = $request->validate(['body' => ['required','string','max:10000']]);

        DB::transaction(function () use ($conversation, $ws, $request, $data) {
            $setting = TenantAutomationSetting::firstOrCreate(
                ['tenant_id' => $ws->tenant_id],
                ['ai_reply_pause_minutes' => 15]
            );
            $minutes = max(1, min(1440, (int) ($setting->ai_reply_pause_minutes ?: 15)));
            $now = now();
            $conversation->update([
                'ai_pause_until_utc' => $now->copy()->addMinutes($minutes),
                'last_human_reply_at_utc' => $now,
                'pause_generation' => ($conversation->pause_generation ?? 0) + 1,
                'conversation_version' => $conversation->conversation_version + 1,
            ]);
            Message::create(['tenant_id'=>$ws->tenant_id,'conversation_id'=>$conversation->id,'role'=>'staff','body'=>$data['body'],'delivery_status'=>'accepted','occurred_at'=>$now]);
            ConversationEvent::create(['tenant_id'=>$ws->tenant_id,'conversation_id'=>$conversation->id,'type'=>'staff_reply_ai_paused','payload_redacted'=>['minutes'=>$minutes],'occurred_at'=>$now]);
        });
        return back()->with('status', 'تم إرسال رد الموظف وإيقاف رد الذكاء مؤقتًا لهذه المحادثة.');
    }

    public function lock(Request $request, string $workspace, Conversation $conversation)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless($conversation->tenant_id === $ws->tenant_id && $conversation->workspace_id === $ws->id, 404);
        $now = now();
        $conversation->update(['human_locked_at'=>$now,'conversation_version'=>$conversation->conversation_version+1]);
        HandoffRecord::create(['tenant_id'=>$ws->tenant_id,'conversation_id'=>$conversation->id,'reason'=>'staff_permanent_lock','opened_by'=>$request->user()->id,'opened_at'=>$now]);
        return back()->with('status', 'تم تفعيل القفل البشري الدائم. لا ينتهي تلقائيًا.');
    }

    public function release(Request $request, string $workspace, Conversation $conversation)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless($conversation->tenant_id === $ws->tenant_id && $conversation->workspace_id === $ws->id, 404);
        $conversation->update(['human_locked_at'=>null,'conversation_version'=>$conversation->conversation_version+1]);
        HandoffRecord::where('conversation_id',$conversation->id)->whereNull('closed_at')->latest('id')->first()?->update(['closed_by'=>$request->user()->id,'closed_at'=>now()]);
        return back()->with('status', 'تم فك القفل البشري بواسطة موظف مصرح.');
    }

    private function context(Request $request, string $workspace): array
    {
        $ws=Workspace::where('slug',$workspace)->firstOrFail();
        $member=TenantMembership::where('user_id',$request->user()->id)->where('tenant_id',$ws->tenant_id)->where('status','active')->first();
        abort_if(!$member,403,'غير مصرح لك بالوصول إلى هذه المساحة.');
        return [$ws,$member];
    }
}