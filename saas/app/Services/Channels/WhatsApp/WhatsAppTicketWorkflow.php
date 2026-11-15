<?php

namespace App\Services\Channels\WhatsApp;

use App\Models\Support\SupportTicket;
use App\Models\Support\SupportWhatsAppSession;

final class WhatsAppTicketWorkflow
{
    /**
     * Handles inbound message according to the conversation state machine.
     *
     * @param int $accountId
     * @param string $phone
     * @param string $message
     * @param string|null $buttonId If a dynamic button was tapped
     * @param string $portalBaseUrl
     * @return array{handled: bool, reply: string, buttons: array, state: string, ticket_id: int|null}
     */
    public static function handle(
        int $accountId,
        string $phone,
        string $message,
        ?string $buttonId = null,
        string $portalBaseUrl = 'https://app.ontrackegy.com'
    ): array {
        $session = SupportWhatsAppSession::firstOrCreate(
            ['account_id' => $accountId, 'phone' => $phone],
            ['phone_hash' => hash('sha256', $phone), 'state' => 'idle']
        );

        $currentState = $session->state ?? 'idle';
        $trimmed = trim($message);
        $action = $buttonId ?? self::detectActionFromText($trimmed);

        // 1. Cancel request at any stage
        if ($action === WhatsAppReplyActions::BUTTON_CANCEL || stripos($trimmed, 'إلغاء') !== false) {
            $session->update(['state' => 'idle', 'state_payload' => null]);
            return [
                'handled' => true,
                'reply' => 'تم إلغاء مسودة التذكرة. كيف يمكنني مساعدتك؟',
                'buttons' => WhatsAppReplyActions::select('idle', 'general', $session->is_verified),
                'state' => 'idle',
                'ticket_id' => null,
            ];
        }

        // 2. Open Ticket Trigger
        if ($action === WhatsAppReplyActions::BUTTON_OPEN_TICKET || stripos($trimmed, 'فتح تذكرة') !== false) {
            if (!$session->is_verified) {
                $challenge = WhatsAppVerification::createChallenge($accountId, $phone, $portalBaseUrl);
                return [
                    'handled' => true,
                    'reply' => "لفتح تذكرة عبر واتساب ومتابعة خدماتك، يرجى تأكيد حسابك عبر الرابط الآمن التالي (صالح لمدة 15 دقيقة):\n🔗 {$challenge['verify_url']}\n\nبعد التأكيد، اضغط «فتح تذكرة دعم» مرة أخرى.",
                    'buttons' => [
                        ['id' => WhatsAppReplyActions::BUTTON_OPEN_TICKET, 'title' => 'فتح تذكرة دعم'],
                        ['id' => WhatsAppReplyActions::BUTTON_HUMAN_HANDOFF, 'title' => 'تحويل لموظف 👤'],
                    ],
                    'state' => 'idle',
                    'ticket_id' => null,
                ];
            }

            $session->update(['state' => 'wa_ticket_collect', 'state_payload' => []]);
            return [
                'handled' => true,
                'reply' => "حاضر. اكتب وصف المشكلة أو طلبك بوضوح في رسالة واحدة، وسأقوم بتجهيز المسودة لك لاعتمادها.",
                'buttons' => WhatsAppReplyActions::select('wa_ticket_collect'),
                'state' => 'wa_ticket_collect',
                'ticket_id' => null,
            ];
        }

        // 3. Collecting Details
        if ($currentState === 'wa_ticket_collect') {
            if (mb_strlen($trimmed, 'UTF-8') < 10) {
                return [
                    'handled' => true,
                    'reply' => "يرجى كتابة وصف أوضح للمشكلة (على الأقل 10 أحرف) بدون مشاركة أي كلمات مرور أو مفاتيح سرية.",
                    'buttons' => WhatsAppReplyActions::select('wa_ticket_collect'),
                    'state' => 'wa_ticket_collect',
                    'ticket_id' => null,
                ];
            }

            $formatted = WhatsAppTicketDraftFormatter::compose($trimmed);
            $session->update([
                'state' => 'wa_ticket_create',
                'state_payload' => [
                    'subject' => $formatted['subject'],
                    'message' => $formatted['message'],
                ],
            ]);

            $previewText = "📋 **مسودة التذكرة (لم تُنشأ بعد):**\n"
                . "📌 **العنوان:** {$formatted['subject']}\n"
                . "📝 **الوصف:** {$formatted['message']}\n\n"
                . "يرجى مراجعة التفاصيل: اضغط «اعتمدها» لإنشاء التذكرة فوراً، أو «تعديل الوصف» لإعادة كتابتها.";

            return [
                'handled' => true,
                'reply' => $previewText,
                'buttons' => WhatsAppReplyActions::select('wa_ticket_create'),
                'state' => 'wa_ticket_create',
                'ticket_id' => null,
            ];
        }

        // 4. Preview and Confirmation
        if ($currentState === 'wa_ticket_create') {
            if ($action === WhatsAppReplyActions::BUTTON_EDIT || stripos($trimmed, 'تعديل') !== false) {
                $session->update(['state' => 'wa_ticket_collect']);
                return [
                    'handled' => true,
                    'reply' => "تفضل بإرسال الوصف المعدل للمشكلة في رسالة واحدة.",
                    'buttons' => WhatsAppReplyActions::select('wa_ticket_collect'),
                    'state' => 'wa_ticket_collect',
                    'ticket_id' => null,
                ];
            }

            if ($action === WhatsAppReplyActions::BUTTON_CONFIRM || stripos($trimmed, 'اعتمد') !== false) {
                $payload = (array)($session->state_payload ?? []);
                $subject = (string)($payload['subject'] ?? 'طلب دعم عبر واتساب');
                $ticketMessage = (string)($payload['message'] ?? $trimmed);

                $ticket = SupportTicket::create([
                    'account_id' => $accountId,
                    'source' => 'whatsapp',
                    'client_id' => $session->client_id,
                    'client_phone' => $phone,
                    'subject' => $subject,
                    'department' => 'الدعم الفني',
                    'priority' => 'medium',
                    'status' => 'open',
                    'latest_message' => $ticketMessage,
                ]);

                $session->update(['state' => 'idle', 'state_payload' => null]);

                $confirmation = "✅ **تم إنشاء التذكرة بنجاح!**\n"
                    . "🎫 **رقم التذكرة:** #{$ticket->id}\n"
                    . "📌 **العنوان:** {$subject}\n\n"
                    . "فريق الدعم الذكي يفحص طلبك حالياً وسنوافيك بالرد فوراً هنا على واتساب.";

                return [
                    'handled' => true,
                    'reply' => $confirmation,
                    'buttons' => [
                        ['id' => WhatsAppReplyActions::BUTTON_OPEN_TICKET, 'title' => 'فتح تذكرة دعم'],
                        ['id' => WhatsAppReplyActions::BUTTON_HUMAN_HANDOFF, 'title' => 'تحويل لموظف 👤'],
                    ],
                    'state' => 'idle',
                    'ticket_id' => $ticket->id,
                ];
            }
        }

        return ['handled' => false, 'reply' => '', 'buttons' => [], 'state' => $currentState, 'ticket_id' => null];
    }

    private static function detectActionFromText(string $text): ?string
    {
        return match ($text) {
            'اعتمدها', 'اعتمد', 'تاكيد', 'تأكيد' => WhatsAppReplyActions::BUTTON_CONFIRM,
            'تعديل الوصف', 'تعديل' => WhatsAppReplyActions::BUTTON_EDIT,
            'إلغاء الطلب', 'الغاء', 'إلغاء' => WhatsAppReplyActions::BUTTON_CANCEL,
            'فتح تذكرة دعم', 'فتح تذكرة', 'تذكرة' => WhatsAppReplyActions::BUTTON_OPEN_TICKET,
            'باقات الاستضافة', 'استضافة' => WhatsAppReplyActions::BUTTON_HOSTING_PLANS,
            'باقات VPS', 'vps' => WhatsAppReplyActions::BUTTON_VPS_PLANS,
            'عرض فواتيري', 'فواتيري', 'فواتير' => WhatsAppReplyActions::BUTTON_INVOICES,
            'تحويل لموظف', 'موظف', 'خدمة العملاء' => WhatsAppReplyActions::BUTTON_HUMAN_HANDOFF,
            default => null,
        };
    }
}
