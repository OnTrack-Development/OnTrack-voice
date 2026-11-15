<?php

namespace App\Services\Channels\WhatsApp;

final class WhatsAppReplyActions
{
    public const BUTTON_CONFIRM = 'aisa_ticket_confirm_v1';
    public const BUTTON_EDIT = 'aisa_ticket_edit_v1';
    public const BUTTON_CANCEL = 'aisa_ticket_cancel_v1';
    public const BUTTON_OPEN_TICKET = 'aisa_ticket_open_v1';
    public const BUTTON_HOSTING_PLANS = 'aisa_hosting_plans_v1';
    public const BUTTON_VPS_PLANS = 'aisa_vps_plans_v1';
    public const BUTTON_INVOICES = 'aisa_invoices_v1';
    public const BUTTON_HUMAN_HANDOFF = 'aisa_human_handoff_v1';

    private const BUTTONS = [
        self::BUTTON_OPEN_TICKET => 'فتح تذكرة دعم',
        self::BUTTON_CONFIRM => 'اعتمدها ✅',
        self::BUTTON_EDIT => 'تعديل الوصف ✏️',
        self::BUTTON_CANCEL => 'إلغاء الطلب ❌',
        self::BUTTON_HOSTING_PLANS => 'باقات الاستضافة 🌐',
        self::BUTTON_VPS_PLANS => 'باقات VPS ⚡',
        self::BUTTON_INVOICES => 'عرض فواتيري 💳',
        self::BUTTON_HUMAN_HANDOFF => 'تحويل لموظف 👤',
    ];

    /**
     * Selects appropriate dynamic buttons based on conversation state.
     *
     * @param string $state 'idle'|'wa_ticket_collect'|'wa_ticket_create'|'wa_ticket_confirm'
     * @param string $intent 'sales'|'technical'|'billing'|'general'
     * @param bool $isVerified
     * @param bool $isHumanMuted
     * @return array List of buttons suitable for Meta Cloud API or QR fallbacks
     */
    public static function select(string $state, string $intent = 'general', bool $isVerified = false, bool $isHumanMuted = false): array
    {
        if ($isHumanMuted) {
            return [];
        }

        return match ($state) {
            'wa_ticket_collect' => [
                ['id' => self::BUTTON_CANCEL, 'title' => self::BUTTONS[self::BUTTON_CANCEL]],
            ],
            'wa_ticket_create' => [
                ['id' => self::BUTTON_CONFIRM, 'title' => self::BUTTONS[self::BUTTON_CONFIRM]],
                ['id' => self::BUTTON_EDIT, 'title' => self::BUTTONS[self::BUTTON_EDIT]],
                ['id' => self::BUTTON_CANCEL, 'title' => self::BUTTONS[self::BUTTON_CANCEL]],
            ],
            default => self::defaultButtonsForIntent($intent, $isVerified),
        };
    }

    private static function defaultButtonsForIntent(string $intent, bool $isVerified): array
    {
        if ($intent === 'sales') {
            return [
                ['id' => self::BUTTON_HOSTING_PLANS, 'title' => self::BUTTONS[self::BUTTON_HOSTING_PLANS]],
                ['id' => self::BUTTON_VPS_PLANS, 'title' => self::BUTTONS[self::BUTTON_VPS_PLANS]],
                ['id' => self::BUTTON_HUMAN_HANDOFF, 'title' => self::BUTTONS[self::BUTTON_HUMAN_HANDOFF]],
            ];
        }

        if ($intent === 'billing' && $isVerified) {
            return [
                ['id' => self::BUTTON_INVOICES, 'title' => self::BUTTONS[self::BUTTON_INVOICES]],
                ['id' => self::BUTTON_OPEN_TICKET, 'title' => self::BUTTONS[self::BUTTON_OPEN_TICKET]],
                ['id' => self::BUTTON_HUMAN_HANDOFF, 'title' => self::BUTTONS[self::BUTTON_HUMAN_HANDOFF]],
            ];
        }

        return [
            ['id' => self::BUTTON_OPEN_TICKET, 'title' => self::BUTTONS[self::BUTTON_OPEN_TICKET]],
            ['id' => self::BUTTON_HOSTING_PLANS, 'title' => self::BUTTONS[self::BUTTON_HOSTING_PLANS]],
            ['id' => self::BUTTON_HUMAN_HANDOFF, 'title' => self::BUTTONS[self::BUTTON_HUMAN_HANDOFF]],
        ];
    }
}
