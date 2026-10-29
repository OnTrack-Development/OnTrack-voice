<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConnection;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class WhatsappWebhookController extends Controller
{
    public function challenge(Request $request, string $publicId)
    {
        $connection = WhatsappConnection::where('public_id', $publicId)->firstOrFail();
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        abort_unless($mode === 'subscribe' && $this->verifyToken($connection, $token), 403);
        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, string $publicId)
    {
        $connection = WhatsappConnection::where('public_id', $publicId)->firstOrFail();
        $raw = $request->getContent();
        abort_unless($this->validSignature($connection, $request->header('X-Hub-Signature-256'), $raw), 403);
        $payload = json_decode($raw, true);
        abort_unless(is_array($payload), 400);
        $eventKey = (string) data_get($payload, 'entry.0.id', '') . ':' . (string) data_get($payload, 'entry.0.changes.0.value.messages.0.id', 'no-message');
        $eventKey = trim($eventKey, ':');
        abort_if($eventKey === '', 400, 'missing_event_key');
        $existing = WhatsappWebhookEvent::where('connection_id', $connection->id)->where('event_key', $eventKey)->first();
        if ($existing) return response()->json(['status'=>'duplicate','event_id'=>$existing->id]);
        $event = WhatsappWebhookEvent::create([
            'tenant_id'=>$connection->tenant_id,
            'connection_id'=>$connection->id,
            'event_key'=>$eventKey,
            'signature_verified_at'=>now(),
            'processing_status'=>'received',
            'payload_redacted'=>['event_key'=>$eventKey,'object'=>$payload['object']??null],
        ]);
        return response()->json(['status'=>'accepted','event_id'=>$event->id], 202);
    }

    private function verifyToken(WhatsappConnection $connection, string $token): bool
    {
        $secrets = $this->secrets($connection);
        return $token !== '' && isset($secrets['verify_token']) && hash_equals((string) $secrets['verify_token'], $token);
    }

    private function validSignature(WhatsappConnection $connection, ?string $header, string $raw): bool
    {
        if (!is_string($header) || !Str::startsWith($header, 'sha256=')) return false;
        $secrets = $this->secrets($connection);
        if (empty($secrets['app_secret'])) return false;
        $expected = 'sha256='.hash_hmac('sha256', $raw, (string) $secrets['app_secret']);
        return hash_equals($expected, $header);
    }

    private function secrets(WhatsappConnection $connection): array
    {
        if (!$connection->encrypted_credentials) return [];
        try { return json_decode(Crypt::decryptString($connection->encrypted_credentials), true) ?: []; }
        catch (\Throwable) { return []; }
    }
}