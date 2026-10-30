<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageOutbox;

/**
 * Final word before an AI message is actually sent on any channel.
 * Always re-checks the CURRENT row state, never trusts a stale call.
 */
final class AiSendGate
{
    public const BLOCK_HUMAN_LOCKED = 'blocked_human_locked';
    public const BLOCK_AI_PAUSED = 'blocked_ai_paused';
    public const BLOCK_CHANNEL_UNAVAILABLE = 'blocked_channel_unavailable';
    public const GRANTED = 'sent';

    public static function evaluate(Conversation $conversation, string $channel): array
    {
        if ($conversation->isHumanLocked()) {
            return ['allowed' => false, 'reason' => self::BLOCK_HUMAN_LOCKED];
        }
        if ($conversation->isAiPaused()) {
            return ['allowed' => false, 'reason' => self::BLOCK_AI_PAUSED];
        }
        if (! in_array($channel, ['web', 'telegram', 'whatsapp'], true)) {
            return ['allowed' => false, 'reason' => self::BLOCK_CHANNEL_UNAVAILABLE];
        }
        return ['allowed' => true, 'reason' => self::GRANTED];
    }

    /**
     * Process one due outbox row with a fresh in-transaction re-check.
     * Returns 'duplicate' when the idempotency key already produced a message.
     */
    public static function processDue(MessageOutbox $row): array
    {
        // A freshly created model may not have the DB default 'pending' loaded in memory.
        if ($row->status !== null && $row->status !== 'pending' && $row->status !== self::GRANTED) {
            return ['status' => 'already_processed'];
        }
        if ($row->occurred_at !== null) {
            return ['status' => 'duplicate'];
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($row) {
            $fresh = MessageOutbox::lockForUpdate()->findOrFail($row->id);
            if ($fresh->occurred_at !== null) {
                return ['status' => 'duplicate'];
            }
            $conversation = $fresh->conversation()->lockForUpdate()->firstOrFail();
            $gate = self::evaluate($conversation, $conversation->channel);
            if (! $gate['allowed']) {
                $fresh->update(['status' => $gate['reason'], 'last_block_reason' => $gate['reason']]);
                return ['status' => $gate['reason']];
            }
            // Simulated transport success persisted as a real Message; provider delivery is evidence-gated elsewhere.
            $message = Message::create([
                'tenant_id' => $fresh->tenant_id,
                'conversation_id' => $fresh->conversation_id,
                'role' => 'ai',
                'body' => $fresh->body,
                'delivery_status' => 'accepted',
                'provider_message_id' => 'outbox:'.$fresh->idempotency_key,
                'occurred_at' => now(),
            ]);
            $fresh->update([
                'status' => self::GRANTED,
                'occurred_at' => now(),
                'evaluated_conversation_version' => $conversation->conversation_version,
                'provider_message_id' => 'outbox:'.$fresh->idempotency_key,
            ]);
            return ['status' => self::GRANTED, 'message_id' => $message->id];
        });
    }
}