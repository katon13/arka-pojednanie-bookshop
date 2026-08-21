<?php
namespace Book100\Services\Mail;

use Book100\Core\AdminPresenter;
use Book100\Core\Utf8Sanitizer;
use Book100\Services\Seo\SeoBuilder;
use Book100\Services\Storefront\StorefrontSettingsService;

final class EmailTemplate
{
    public function order(array $order, array $items, string $subject, string $message, string $template): string
    {
        $store = (new StorefrontSettingsService())->state();
        $shopName = trim((string)($store['shop_name'] ?? 'Wydawnictwo Katolickie ARKA')) ?: 'Wydawnictwo Katolickie ARKA';
        $logo = trim((string)($store['site_logo'] ?? ''));
        if ($logo !== '' && !preg_match('#^https?://#i', $logo)) $logo = SeoBuilder::url($logo);

        $orderNumber = (string)($order['order_number'] ?? '');
        $customerName = trim((string)($order['customer_name'] ?? ''));
        $status = AdminPresenter::orderStatus((string)($order['status'] ?? ''));
        $accent = preg_match('/^#[0-9a-f]{6}$/i', (string)($store['brand_accent_color'] ?? ''))
            ? (string)$store['brand_accent_color']
            : '#e91d2a';
        $actionUrl = !empty($order['order_token'])
            ? SeoBuilder::url('/dziekujemy/' . rawurlencode((string)$order['order_token']))
            : '';

        $headline = match ($template) {
            'order_created' => 'Dziękujemy za zamówienie',
            'payment_paid' => 'Płatność potwierdzona',
            'shipment_created' => 'Przesyłka jest przygotowywana',
            'order_shipped' => 'Twoja przesyłka jest w drodze',
            'order_cancelled' => 'Zamówienie anulowane',
            'payment_refunded' => 'Zwrot płatności',
            default => 'Aktualizacja zamówienia',
        };

        $itemRows = '';
        foreach ($items as $item) {
            $cover = trim((string)($item['cover_image'] ?? ''));
            if ($cover !== '' && !preg_match('#^https?://#i', $cover)) $cover = SeoBuilder::url($cover);
            $image = $cover !== ''
                ? '<img src="' . $this->e($cover) . '" alt="" width="52" style="display:block;width:52px;height:68px;object-fit:contain;border:0">'
                : '<span style="display:block;width:52px;height:68px;background:#f1f2f4;border-radius:6px"></span>';

            $title = $this->e((string)($item['title'] ?? 'Książka'));
            $quantity = (int)($item['quantity'] ?? 1);
            if ($quantity < 1) $quantity = 1;

            $saleNote = '';
            if (($item['sale_mode'] ?? '') === 'preorder') {
                $release = \Book100\Services\Books\BookSaleState::formattedReleaseDate((string)($item['release_date'] ?? ''));
                $saleNote = '<span style="display:block;margin-top:4px;color:#956400;font-size:12px;font-weight:700">Przedsprzedaż';
                if ($release !== '') {
                    $saleNote .= ' • premiera ' . $this->e($release);
                }
                $saleNote .= '</span>';
            }

            $itemRows .= '<tr><td style="padding:14px 0;border-bottom:1px solid #eceef1;width:64px">'
                . $image . '</td>'
                . '<td style="padding:14px 12px;border-bottom:1px solid #eceef1;color:#17191f">'
                . '<strong style="display:block;font-size:15px;line-height:1.35">' . $title . '</strong>'
                . $saleNote
                . '<span style="font-size:13px;color:#717784">Ilość: ' . $quantity . '</span>'
                . '</td>'
                . '<td style="padding:14px 0;border-bottom:1px solid #eceef1;text-align:right;white-space:nowrap;font-weight:700">'
                . $this->money((float)($item['total_gross'] ?? 0), (string)($order['currency'] ?? 'PLN'))
                . '</td></tr>';
        }

        $delivery = match ((string)($order['delivery_method'] ?? '')) {
            'inpost_locker' => 'Paczkomat InPost' . (!empty($order['inpost_point']) ? ' • ' . $this->e((string)$order['inpost_point']) : ''),
            'inpost_courier' => 'Kurier InPost',
            'pickup' => 'Odbiór osobisty',
            'ebook' => 'Dostawa elektroniczna',
            default => (string)($order['delivery_method'] ?? '—'),
        };

        $shopUrl = SeoBuilder::url('/');
        $headlineHtml = $this->e($headline);
        $statusHtml = $this->e((string)$status);
        $deliveryHtml = $this->e((string)$delivery);
        $totalHtml = $this->money((float)($order['total_gross'] ?? 0), (string)($order['currency'] ?? 'PLN'));

        $html = '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>'
            . $this->e($subject)
            . '</title><style>@media(max-width:620px){.mail-shell{width:100%!important}.mail-pad{padding:24px!important}.mail-total{font-size:24px!important}}</style></head>'
            . '<body style="margin:0;background:#f3f4f6;color:#17191f;font-family:Arial,Helvetica,sans-serif">'
            . '<div style="display:none;max-height:0;overflow:hidden;color:transparent">' . $this->e($message) . '</div>'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6"><tr><td align="center" style="padding:28px 12px">'
            . '<table role="presentation" class="mail-shell" width="600" cellspacing="0" cellpadding="0" style="width:600px;max-width:100%;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 8px 28px rgba(23,25,31,.08)">'
            . '<tr><td style="height:6px;background:' . $this->e($accent) . '"></td></tr>'
            . '<tr><td class="mail-pad" style="padding:34px 42px 20px">'
            . ($logo !== '' ? '<img src="' . $this->e($logo) . '" alt="' . $this->e($shopName) . '" style="display:block;max-width:150px;max-height:58px;border:0;margin-bottom:28px">' : '<strong style="display:block;font-size:24px;margin-bottom:28px">' . $this->e($shopName) . '</strong>')
            . '<span style="display:inline-block;color:' . $this->e($accent) . ';font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase">Zamówienie ' . $this->e($orderNumber) . '</span>'
            . '<h1 style="margin:10px 0 14px;font-size:31px;line-height:1.12;letter-spacing:-.03em">' . $headlineHtml . '</h1>'
            . ($customerName !== '' ? '<p style="margin:0 0 8px;font-size:16px;line-height:1.65">Dzień dobry, ' . $this->e($customerName) . '.</p>' : '')
            . '<p style="margin:0;font-size:16px;line-height:1.65;color:#4f5560">' . $this->messageHtml($message) . '</p></td></tr>'
            . '<tr><td class="mail-pad" style="padding:12px 42px 28px"><table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $itemRows . '</table>'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:22px;background:#f7f7f8;border-radius:12px">'
            . '<tr><td style="padding:16px 18px;color:#717784;font-size:13px">Status</td><td style="padding:16px 18px;text-align:right;font-weight:700">' . $statusHtml . '</td></tr>'
            . '<tr><td style="padding:0 18px 16px;color:#717784;font-size:13px">Dostawa</td><td style="padding:0 18px 16px;text-align:right;font-weight:700">' . $deliveryHtml . '</td></tr>'
            . '<tr><td style="padding:0 18px 18px;color:#717784;font-size:13px">Razem</td><td class="mail-total" style="padding:0 18px 18px;text-align:right;font-size:28px;font-weight:800">' . $totalHtml . '</td></tr>'
            . '</table>'
            . ($actionUrl !== '' ? '<p style="margin:26px 0 0;text-align:center"><a href="' . $this->e($actionUrl) . '" style="display:inline-block;background:#17191f;color:#fff;text-decoration:none;font-weight:800;padding:14px 22px;border-radius:9px">Zobacz zamówienie</a></p>' : '')
            . '</td></tr><tr><td style="padding:22px 42px 30px;background:#fafafa;color:#7b8089;font-size:12px;line-height:1.6;text-align:center">'
            . 'Wiadomość transakcyjna ze sklepu <a href="' . $this->e($shopUrl) . '" style="color:' . $this->e($accent) . ';text-decoration:none">' . $this->e($shopName) . '</a>.'
            . (!empty($store['shop_email']) ? '<br>Kontakt: <a href="mailto:' . $this->e((string)$store['shop_email']) . '" style="color:#555">' . $this->e((string)$store['shop_email']) . '</a>' : '')
            . '</td></tr>'
            . '</table></td></tr></table>'
            . '</body></html>';

        return Utf8Sanitizer::normalize($html);
    }

    public function newsletter(string $subject, string $message, string $unsubscribeUrl): string
    {
        $store = (new StorefrontSettingsService())->state();
        $shopName = trim((string)($store['shop_name'] ?? 'Wydawnictwo Katolickie ARKA')) ?: 'Wydawnictwo Katolickie ARKA';
        $shopUrl = SeoBuilder::url('/');
        $logo = trim((string)($store['site_logo'] ?? ''));
        if ($logo !== '' && !preg_match('#^https?://#i', $logo)) $logo = SeoBuilder::url($logo);
        $accent = preg_match('/^#[0-9a-f]{6}$/i', (string)($store['brand_accent_color'] ?? ''))
            ? (string)$store['brand_accent_color']
            : '#b79242';
        $unsubscribeUrl = trim($unsubscribeUrl);

        $html = '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>'
            . $this->e($subject)
            . '</title></head>'
            . '<body style="margin:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#17191f">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6"><tr><td align="center" style="padding:28px 12px">'
            . '<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:600px;max-width:100%;background:#fff;border-radius:16px;overflow:hidden">'
            . '<tr><td style="height:5px;background:' . $this->e($accent) . '"></td></tr>'
            . '<tr><td style="padding:36px 40px 30px">'
            . ($logo !== ''
                ? '<a href="' . $this->e($shopUrl) . '"><img src="' . $this->e($logo) . '" alt="' . $this->e($shopName) . '" style="display:block;max-width:150px;max-height:58px;border:0;margin-bottom:26px"></a>'
                : '<strong style="display:block;color:' . $this->e($accent) . ';font-size:14px;letter-spacing:.1em;margin-bottom:22px">' . $this->e($shopName) . '</strong>')
            . '<h1 style="font-size:28px;line-height:1.2;margin:0 0 18px">' . $this->e($subject) . '</h1>'
            . '<div style="font-size:16px;line-height:1.7;color:#505661">' . $this->messageHtml($message) . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:24px 40px 30px;background:#fafafa;border-top:1px solid #eceef1;text-align:center;color:#777d87;font-size:12px;line-height:1.6">'
            . 'Otrzymujesz tę wiadomość, ponieważ zapisano ten adres do newslettera ARKA.'
            . ($unsubscribeUrl !== ''
                ? '<p style="margin:18px 0 0"><a href="' . $this->e($unsubscribeUrl) . '" style="display:inline-block;padding:10px 16px;border:1px solid #c8cbd1;border-radius:8px;color:#343840;text-decoration:none;font-weight:700">Wypisz mnie z newslettera</a></p>'
                : '')
            . '</td></tr></table></td></tr></table>'
            . '</body></html>';

        return Utf8Sanitizer::normalize($html);
    }

    public function generic(string $subject, string $message): string
    {
        $store = (new StorefrontSettingsService())->state();
        $shopName = trim((string)($store['shop_name'] ?? 'Wydawnictwo Katolickie ARKA')) ?: 'Wydawnictwo Katolickie ARKA';
        $shopUrl = SeoBuilder::url('/');

        $html = '<!doctype html><html lang="pl"><head><meta charset="utf-8"><title>'
            . $this->e($subject)
            . '</title></head>'
            . '<body style="margin:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#17191f">'
            . '<table role="presentation" width="100%"><tr><td align="center" style="padding:32px 12px"><table width="600" style="max-width:100%;background:#fff;border-radius:16px">'
            . '<tr><td style="padding:38px">'
            . '<strong style="color:#e91d2a;font-size:13px;letter-spacing:.12em">' . $this->e($shopName) . '</strong>'
            . '<h1 style="font-size:28px;margin:16px 0">' . $this->e($subject) . '</h1>'
            . '<p style="font-size:16px;line-height:1.7;color:#505661">' . $this->messageHtml($message) . '</p>'
            . '</td></tr></table></td></tr></table>'
            . '<p style="margin:14px 0 0;text-align:center;color:#7b8089;font-size:12px"><a href="' . $this->e($shopUrl) . '" style="color:#d71926;text-decoration:none">Wróć do sklepu</a></p>'
            . '</body></html>';

        return Utf8Sanitizer::normalize($html);
    }

    private function messageHtml(string $message): string
    {
        $message = Utf8Sanitizer::normalize($message);
        $escaped = nl2br($this->e(trim($message)), false);
        return (string)preg_replace_callback(
            '#https?://[^\s<]+#i',
            static fn(array $match): string => '<a href="' . $match[0] . '" style="color:#d71926">' . $match[0] . '</a>',
            $escaped
        );
    }

    private function money(float $amount, string $currency): string
    {
        return Utf8Sanitizer::normalize(number_format($amount, 2, ',', ' ') . ' ' . ($currency === 'PLN' ? 'zł' : $this->e($currency)));
    }

    private function e(string $value): string
    {
        return Utf8Sanitizer::normalize(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}
