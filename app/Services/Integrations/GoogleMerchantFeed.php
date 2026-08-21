<?php
namespace Book100\Services\Integrations;

use Book100\Repository\BookRepository;
use Book100\Repository\SettingsRepository;
use Book100\Services\Seo\SeoBuilder;
use Book100\Services\Books\BookSaleState;

final class GoogleMerchantFeed
{
    public function configuration(): array
    {
        $settings = new SettingsRepository();
        $enabled = $settings->get('google_merchant_enabled', '0') === '1';
        $merchantId = trim($settings->get('google_merchant_id', ''));
        return [
            'enabled' => $enabled,
            'configured' => $enabled && $merchantId !== '',
            'merchant_id' => $merchantId,
            'country' => strtoupper(trim($settings->get('google_merchant_country', 'PL'))) ?: 'PL',
            'language' => strtolower(trim($settings->get('google_merchant_language', 'pl'))) ?: 'pl',
            'brand' => trim($settings->get('google_merchant_brand', $settings->get('shop_name', 'Wydawnictwo Katolickie ARKA'))),
            'feed_url' => SeoBuilder::url('/google-merchant.xml'),
        ];
    }

    public function products(): array
    {
        $config = $this->configuration();
        $products = [];
        foreach ((new BookRepository())->allPublic() as $book) {
            if (trim((string)($book['cover_image'] ?? '')) === '') continue;
            // E-booki pozostają indeksowane przez publiczne strony produktów,
            // ale Google Shopping nie obsługuje książek cyfrowych.
            if (($book['product_type'] ?? '') === 'ebook') continue;
            $price = (float)($book['price_gross'] ?? 0);
            // Merchant Center odrzuca zwykłe produkty z ceną równą zero.
            if ($price <= 0) continue;
            $description = trim(strip_tags((string)(
                ($book['seo_description'] ?? '')
                ?: (($book['short_description'] ?? '') ?: ($book['description'] ?? ''))
            )));
            $description = preg_replace('/\s+/u', ' ', $description) ?: (string)$book['title'];
            $isbn = preg_replace('/[^0-9X]/i', '', (string)($book['isbn'] ?? ''));
            if (!in_array(strlen($isbn), [10, 13], true)) $isbn = '';
            $availability = BookSaleState::merchantAvailability($book);
            $imagePath = trim((string)$book['cover_image']);
            $products[] = [
                'id' => !empty($book['sku']) ? (string)$book['sku'] : 'book-' . (int)$book['id'],
                'title' => (string)$book['title'],
                'description' => mb_substr($description, 0, 5000),
                'link' => SeoBuilder::url('/book/' . rawurlencode((string)$book['slug']) . '/'),
                'image_link' => preg_match('#^https?://#i', $imagePath) ? $imagePath : SeoBuilder::url($imagePath),
                'availability' => $availability,
                'availability_date' => $availability === 'preorder' ? BookSaleState::releaseDate($book) : null,
                'price' => number_format($price, 2, '.', '') . ' ' . (string)($book['currency'] ?? 'PLN'),
                'condition' => 'new',
                'brand' => trim((string)($book['publisher'] ?? '')) ?: (string)$config['brand'],
                'gtin' => $isbn,
                'mpn' => $isbn === '' ? (!empty($book['sku']) ? (string)$book['sku'] : 'BOOK-' . (int)$book['id']) : '',
                'product_type' => 'Książki > Książki drukowane',
                'google_product_category' => 'Media > Books',
            ];
        }
        return $products;
    }
}
