<?php
namespace Book100\Services\Storefront;

use Book100\Core\Paths;
use Book100\Core\StoreUrl;
use Book100\Repository\SettingsRepository;
use Book100\Services\Media\ImageOptimizer;
use RuntimeException;

final class StorefrontSettingsService
{
    private const SHORT_LIMITS = [
        'shop_name' => 120,
        'shop_url' => 255,
        'brand_tagline' => 180,
        'shop_email' => 190,
        'shop_phone' => 60,
        'maintenance_message' => 500,
        'currency' => 3,
        'nav_books_label' => 40,
        'nav_how_label' => 40,
        'nav_terms_label' => 40,
        'nav_contact_label' => 40,
        'home_catalog_eyebrow' => 80,
        'home_catalog_title' => 160,
        'home_how_eyebrow' => 80,
        'home_how_title' => 180,
        'home_step_1_title' => 80,
        'home_step_2_title' => 80,
        'home_step_3_title' => 80,
        'newsletter_eyebrow' => 80,
        'newsletter_title' => 180,
        'newsletter_button_label' => 60,
        'footer_shop_heading' => 60,
        'footer_info_heading' => 60,
        'footer_payments_heading' => 60,
        'footer_bottom_text' => 180,
        'contact_title' => 120,
        'terms_title' => 120,
        'privacy_title' => 120,
        'seo_home_title' => 190,
        'seo_title_suffix' => 80,
        'checkout_eyebrow' => 80,
        'checkout_title' => 180,
        'checkout_assurance_1' => 100,
        'checkout_assurance_2' => 100,
        'checkout_assurance_paper' => 120,
        'checkout_assurance_ebook' => 120,
    ];

    private const LONG_LIMITS = [
        'shop_address' => 2000,
        'home_step_1_text' => 500,
        'home_step_2_text' => 500,
        'home_step_3_text' => 500,
        'newsletter_text' => 1000,
        'newsletter_consent_text' => 500,
        'contact_text' => 20000,
        'terms_text' => 200000,
        'privacy_text' => 200000,
        'seo_home_description' => 500,
        'checkout_lead' => 1000,
    ];

    /** @return array<string,string> */
    public function defaults(): array
    {
        return [
            'shop_name' => 'Wydawnictwo Katolickie ARKA',
            'shop_url' => StoreUrl::base(),
            'brand_tagline' => 'Słowo · Wiara · Życie',
            'site_logo' => '/assets/brand/arka-logo.png',
            'site_icon' => '/assets/brand/arka-logo.png',
            'brand_accent_color' => '#8b6f47',
            'brand_accent_dark' => '#5d462d',
            'shop_email' => 'biuro@arka-pojednanie.pl',
            'shop_phone' => '+48 605 313 813',
            'shop_address' => "Agencja ARKA\nMaciej Karwacki-Niecewicz\nul. Św. Wawrzyńca 38/10\n31-052 Kraków\nNIP: 7791563475",
            'currency' => 'PLN',
            'shipping_default_gross' => '12.00',
            'shipping_inpost_locker_gross' => '12.00',
            'shipping_inpost_courier_gross' => '16.00',
            'maintenance_enabled' => '0',
            'maintenance_message' => 'Konserwacja systemu — prosimy nie dokonywać zakupu.',

            'nav_books_label' => 'Książki',
            'nav_how_label' => 'Jak kupić',
            'nav_terms_label' => 'Regulamin',
            'nav_contact_label' => 'Kontakt',

            'home_catalog_eyebrow' => 'Wydawnictwo Katolickie ARKA',
            'home_catalog_title' => 'Książki o wierze, nadziei i pojednaniu',
            'home_how_eyebrow' => 'Prosty zakup',
            'home_how_title' => 'Trzy kroki od książki do spotkania ze słowem.',
            'home_step_1_title' => 'Wybierz tytuł',
            'home_step_1_text' => 'Papier lub ebook — dokładnie to, czego potrzebujesz.',
            'home_step_2_title' => 'Podaj dane',
            'home_step_2_text' => 'Jeden krótki formularz, bez zakładania konta.',
            'home_step_3_title' => 'Zapłać',
            'home_step_3_text' => 'Bezpieczna płatność przez Przelewy24. Książkę wysyłamy po potwierdzeniu zapłaty.',

            'newsletter_eyebrow' => 'Wiadomości z ARKI',
            'newsletter_title' => 'Nowe książki i Rekolekcje Pojednania.',
            'newsletter_text' => 'Od czasu do czasu wyślemy wiadomość o premierze, rekolekcjach lub ważnym wydarzeniu.',
            'newsletter_button_label' => 'Zapisuję się',
            'newsletter_consent_text' => 'Chcę otrzymywać informacje o książkach i znam',

            'footer_shop_heading' => 'Sklep',
            'footer_info_heading' => 'Informacje',
            'footer_payments_heading' => 'Płatności',
            'footer_bottom_text' => 'Słowo · Wiara · Życie',

            'contact_title' => 'Kontakt',
            'contact_text' => "Agencja ARKA\nMaciej Karwacki-Niecewicz\nul. Św. Wawrzyńca 38/10\n31-052 Kraków\nNIP: 7791563475\n\nTelefon: 605 313 813\nKontakt ogólny: biuro@arka-pojednanie.pl\nRekolekcje: rekolekcje@arka-pojednanie.pl",
            'terms_title' => 'Regulamin',
            'terms_text' => '',
            'privacy_title' => 'Polityka prywatności',
            'privacy_text' => '',

            'seo_home_title' => 'Wydawnictwo Katolickie ARKA — Słowo · Wiara · Życie',
            'seo_home_description' => 'Księgarnia Wydawnictwa Katolickiego ARKA: książki o wierze, prawdzie, nadziei i pojednaniu.',
            'seo_default_og_image' => '/assets/brand/arka-logo.png',
            'seo_title_suffix' => 'ARKA',

            'checkout_eyebrow' => 'Finalizacja zakupu',
            'checkout_title' => 'Jeszcze tylko dane i płatność.',
            'checkout_lead' => 'Wszystko na jednej stronie. Po potwierdzeniu przejdziesz bezpośrednio do operatora płatności.',
            'checkout_assurance_1' => 'Bez zakładania konta',
            'checkout_assurance_2' => 'Bezpieczna płatność',
            'checkout_assurance_paper' => 'Dostawa przez InPost',
            'checkout_assurance_ebook' => 'Ebook dostępny po płatności',
        ];
    }

    /** @return array<string,string> */
    public function state(): array
    {
        $defaults = $this->defaults();
        $stored = (new SettingsRepository())->allKeyed();
        if (!array_key_exists('newsletter_text', $stored) && array_key_exists('newsletter_footer_text', $stored)) {
            $stored['newsletter_text'] = $stored['newsletter_footer_text'];
        }
        $state = $defaults;
        foreach (array_keys($defaults) as $name) {
            if (array_key_exists($name, $stored)) {
                $state[$name] = (string)$stored[$name];
            }
        }
        return $state;
    }

    public function save(array $input, array $files): void
    {
        $current = $this->state();
        $values = [];

        foreach (self::SHORT_LIMITS as $name => $limit) {
            $values[$name] = $this->text($input[$name] ?? $current[$name] ?? '', $name, $limit);
        }
        foreach (self::LONG_LIMITS as $name => $limit) {
            $values[$name] = $this->text($input[$name] ?? $current[$name] ?? '', $name, $limit, true);
        }
        $values['maintenance_enabled'] = !empty($input['maintenance_enabled']) ? '1' : '0';

        if ($values['shop_name'] === '') {
            throw new RuntimeException('Nazwa sklepu jest wymagana.');
        }
        $shopUrl = StoreUrl::normalize($values['shop_url']);
        if ($shopUrl === null) {
            throw new RuntimeException('Podaj poprawną domenę sklepu, np. https://arka-pojednanie.pl.');
        }
        $values['shop_url'] = $shopUrl;
        if ($values['shop_email'] === '' || !filter_var($values['shop_email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Podaj poprawny e-mail sklepu.');
        }

        $currency = strtoupper($values['currency']);
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new RuntimeException('Waluta musi mieć trzyliterowy kod, np. PLN.');
        }
        $values['currency'] = $currency;

        foreach (['shipping_default_gross','shipping_inpost_locker_gross','shipping_inpost_courier_gross'] as $priceKey) {
            $raw = str_replace(',', '.', trim((string)($input[$priceKey] ?? $current[$priceKey] ?? '0')));
            if ($raw === '' || !is_numeric($raw) || (float)$raw < 0) {
                throw new RuntimeException('Koszt dostawy musi być liczbą równą lub większą od zera.');
            }
            $values[$priceKey] = number_format((float)$raw, 2, '.', '');
        }

        foreach (['brand_accent_color', 'brand_accent_dark'] as $colorKey) {
            $color = strtolower(trim((string)($input[$colorKey] ?? $current[$colorKey] ?? '')));
            if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
                throw new RuntimeException('Kolor marki musi mieć format HEX, np. #e91d2a.');
            }
            $values[$colorKey] = $color;
        }

        $assets = [
            'site_logo' => ['file' => 'site_logo_file', 'remove' => 'remove_site_logo', 'prefix' => 'logo', 'width' => 1800, 'height' => 700],
            'site_icon' => ['file' => 'site_icon_file', 'remove' => 'remove_site_icon', 'prefix' => 'icon', 'width' => 512, 'height' => 512],
            'seo_default_og_image' => ['file' => 'seo_og_image_file', 'remove' => 'remove_seo_og_image', 'prefix' => 'social', 'width' => 2400, 'height' => 1350],
        ];
        foreach ($assets as $setting => $asset) {
            $stored = (string)($current[$setting] ?? '');
            if (!empty($input[$asset['remove']])) $stored = '';
            $values[$setting] = $this->saveImage(
                is_array($files[$asset['file']] ?? null) ? $files[$asset['file']] : [],
                (string)$asset['prefix'],
                (int)$asset['width'],
                (int)$asset['height'],
                $stored
            );
        }

        (new SettingsRepository())->saveValues($values);
    }

    private function text(mixed $value, string $name, int $limit, bool $multiline = false): string
    {
        $value = str_replace("\0", '', (string)$value);
        $value = $multiline ? trim(str_replace(["\r\n", "\r"], "\n", $value)) : trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if (mb_strlen($value) > $limit) {
            throw new RuntimeException('Pole „' . $name . '” jest zbyt długie.');
        }
        return $value;
    }

    private function saveImage(array $file, string $prefix, int $maxWidth, int $maxHeight, string $current): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $current;
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Nie udało się wgrać grafiki marki.');
        }
        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 12 * 1024 * 1024) {
            throw new RuntimeException('Grafika może mieć maksymalnie 12 MB.');
        }
        if (!is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('Nieprawidłowe źródło przesłanej grafiki.');
        }

        $directory = Paths::publicRoot() . '/uploads/brand';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Nie można utworzyć katalogu plików marki.');
        }
        $filename = $prefix . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        (new ImageOptimizer())->optimize(
            (string)$file['tmp_name'],
            $directory . '/' . $filename,
            $maxWidth,
            $maxHeight,
            86
        );
        return '/uploads/brand/' . $filename . '.webp';
    }
}
