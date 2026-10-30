<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SocialConnection;
use App\Models\SocialWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class TelegramWebhookController extends Controller
{
    public function receive(Request $request, string $publicId)
    {
        $connection = SocialConnection::where('public_id', $publicId)
            ->where('channel', 'telegram')->where('state', 'draft')->firstOrFail();
        $this->checkSecret($connection, $request->header('X-Telegram-Bot-Api-Secret-Token'));
        $payload = $request->json()->all();
        $updateId = (string) ($payload['update_id'] ?? '');
        abort_if($updateId === '', 400, 'missing_update_id');

        $eventKey = 'telegram:'.$updateId;
        $existing = SocialWebhookEvent::where('social_connection_id', $connection->id)->where('event_key', $eventKey)->first();
        if ($existing) return response()->json(['status'=>'duplicate','event_id'=>$existing->id]);

        $message = $payload['message'] ?? $payload['edited_message'] ?? null;
        $chatId = data_get($message, 'chat.id');
        $text = data_get($message, 'text');
        if ($chatId === null || !is_string($text) || $text === '') {
            $event = SocialWebhookEvent::create(['tenant_id'=>$connection->tenant_id,'social_connection_id'=>$connection->id,'event_key'=>$eventKey,'processing_status'=>'ignored','payload_redacted'=>['update_id'=>$updateId]]);
            return response()->json(['status'=>'ignored','event_id'=>$event->id], 202);
        }

        $event = SocialWebhookEvent::create(['tenant_id'=>$connection->tenant_id,'social_connection_id'=>$connection->id,'event_key'=>$eventKey,'processing_status'=>'accepted','payload_redacted'=>['update_id'=>$updateId,'chat_id_hash'=>hash('sha256',(string)$chatId)]]);
        $conversation = Conversation::firstOrCreate([
            'tenant_id'=>$connection->tenant_id,
            'contact_key'=>'telegram:'.hash('sha256',(string)$chatId),
            'channel'=>'telegram',
        ], ['workspace_id'=>$connection->workspace_id,'status'=>'open']);
        Message::create(['tenant_id'=>$connection->tenant_id,'conversation_id'=>$conversation->id,'role'=>'customer','body'=>$text,'delivery_status'=>'accepted','provider_message_id'=>'telegram:'.$updateId,'occurred_at'=>now()]);
        return response()->json(['status'=>'accepted','event_id'=>$event->id,'conversation_id'=>$conversation->id], 202);
    }

    private function checkSecret(SocialConnection $connection, ?string $provided): void
    {
        abort_if(!$connection->encrypted_webhook_secret || !$provided, 403);
        try {$expected=Crypt::decryptString($connection->encrypted_webhook_secret);} catch (\Throwable) {abort(403);}
        abort_unless(hash_equals($expected,$provided),403);
    }
}