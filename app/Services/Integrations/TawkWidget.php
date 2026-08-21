<?php
namespace Book100\Services\Integrations;

use Book100\Core\Env;

final class TawkWidget
{
    public static function configuration(): array
    {
        $propertyId = trim((string)Env::get('TAWK_PROPERTY_ID', ''));
        $widgetId = trim((string)Env::get('TAWK_WIDGET_ID', ''));
        $configured = self::validId($propertyId) && self::validId($widgetId);
        $requestedEnabled = self::boolean((string)Env::get('TAWK_ENABLED', 'false'));

        return [
            'requested_enabled' => $requestedEnabled,
            'enabled' => $requestedEnabled && $configured,
            'configured' => $configured,
            'property_id' => $propertyId,
            'widget_id' => $widgetId,
            'embed_url' => $configured ? self::embedUrl($propertyId, $widgetId) : '',
            'direct_chat_url' => $configured ? self::directChatUrl($propertyId, $widgetId) : '',
        ];
    }

    public static function validId(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{6,80}$/', trim($value)) === 1;
    }

    public static function embedUrl(string $propertyId, string $widgetId): string
    {
        if (!self::validId($propertyId) || !self::validId($widgetId)) {
            return '';
        }

        return 'https://embed.tawk.to/' . rawurlencode(trim($propertyId)) . '/' . rawurlencode(trim($widgetId));
    }

    public static function directChatUrl(string $propertyId, string $widgetId): string
    {
        if (!self::validId($propertyId) || !self::validId($widgetId)) {
            return '';
        }

        return 'https://tawk.to/chat/' . rawurlencode(trim($propertyId)) . '/' . rawurlencode(trim($widgetId));
    }

    private static function boolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
