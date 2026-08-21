<?php
namespace Book100\Services\Integrations;

use Book100\Repository\SettingsRepository;

final class GoogleAnalytics
{
    /** @return array{requested_enabled:bool,enabled:bool,configured:bool,measurement_id:string} */
    public static function configuration(): array
    {
        $settings = (new SettingsRepository())->allKeyed();
        $measurementId = strtoupper(trim((string)($settings['google_analytics_measurement_id'] ?? '')));
        $configured = self::validMeasurementId($measurementId);
        $requestedEnabled = self::boolean((string)($settings['google_analytics_enabled'] ?? '0'));

        return [
            'requested_enabled' => $requestedEnabled,
            'enabled' => $requestedEnabled && $configured,
            'configured' => $configured,
            'measurement_id' => $measurementId,
        ];
    }

    public static function validMeasurementId(string $value): bool
    {
        return preg_match('/^G-[A-Z0-9]{4,20}$/', strtoupper(trim($value))) === 1;
    }

    private static function boolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
