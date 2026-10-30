<?php

namespace App\Support;

/**
 * Official per-channel capabilities. Truthful: never claim an action the official API
 * does not provably support; unsupported remains shown as unavailable (not enabled).
 */
final class ChannelCapabilities
{
    public const CHANNELS = [
        'telegram' => [
            'label' => 'Telegram',
            'enabled' => true,
            'capabilities' => [
                'receive_private_dm' => true,
                'send_private_dm' => true,
                'receive_comments' => false,
                'send_comments' => false,
                'receive_mentions' => true,
                'attachments' => true,
                'buttons' => true,
                'delivery_status' => false,
            ],
            'mode' => 'Bot API — webhook secret + dedupe; business connection حيث يؤهل الحساب',
        ],
        'facebook_messenger' => [
            'label' => 'Facebook Pages/Messenger',
            'enabled' => false,
            'capabilities' => [
                'receive_private_dm' => false,
                'send_private_dm' => false,
                'receive_comments' => false,
                'send_comments' => false,
                'receive_mentions' => false,
                'attachments' => false,
                'buttons' => false,
                'delivery_status' => false,
            ],
            'mode' => 'يتطلب مراجعة صفحة/تطبيق Meta وأذونات وسياسة رسائل — تظل PENDING حتى دليل حي',
        ],
        'instagram_pro' => [
            'label' => 'Instagram (Accounts Pro)',
            'enabled' => false,
            'capabilities' => [
                'receive_private_dm' => false,
                'send_private_dm' => false,
                'receive_comments' => false,
                'send_comments' => false,
                'receive_mentions' => false,
                'attachments' => false,
                'buttons' => false,
                'delivery_status' => false,
            ],
            'mode' => 'يتطلب حساب احترافي ومراجعة Meta — تظل PENDING حتى دليل حي',
        ],
        'web_widget' => [
            'label' => 'Web Widget',
            'enabled' => true,
            'capabilities' => [
                'receive_private_dm' => true,
                'send_private_dm' => true,
                'receive_comments' => false,
                'send_comments' => false,
                'receive_mentions' => false,
                'attachments' => false,
                'buttons' => true,
                'delivery_status' => false,
            ],
            'mode' => 'مفتاح عام + جلسة موقع موقعة — يعمل محليًا ويظهر في Inbox الموحد',
        ],
        'whatsapp' => [
            'label' => 'WhatsApp الرسمي (Meta Cloud API)',
            'enabled' => true,
            'capabilities' => [
                'receive_private_dm' => true,
                'send_private_dm' => true,
                'receive_comments' => false,
                'send_comments' => false,
                'receive_mentions' => false,
                'attachments' => true,
                'buttons' => true,
                'delivery_status' => true,
            ],
            'mode' => 'ثلاث طرق اتصال رسمية — تبقى draft حتى أدلة Meta واردة/صادرة',
        ],
    ];

    public static function all(): array
    {
        return self::CHANNELS;
    }

    public static function for(string $channel): ?array
    {
        return self::CHANNELS[$channel] ?? null;
    }
}