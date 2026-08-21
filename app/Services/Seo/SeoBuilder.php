<?php
namespace Book100\Services\Seo;

use Book100\Core\StoreUrl;
use Book100\Services\Storefront\StorefrontSettingsService;
use Book100\Services\Books\BookSaleState;

final class SeoBuilder
{
    public static function home(): array
    {
        $store = self::storefront();
        $url = self::url('/');
        $name = (string)$store['shop_name'];
        $logo = self::assetUrl((string)($store['site_logo'] ?? ''));
        $image = self::assetUrl((string)($store['seo_default_og_image'] ?? ''));

        $organization = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name,
            'url' => $url,
            'logo' => $logo,
            'email' => (string)($store['shop_email'] ?? ''),
            'telephone' => (string)($store['shop_phone'] ?? ''),
        ];
        if (trim((string)($store['shop_address'] ?? '')) !== '') {
            $organization['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => (string)$store['shop_address'],
            ];
        }

        return [
            'title' => (string)$store['seo_home_title'],
            'description' => (string)$store['seo_home_description'],
            'canonical' => $url,
            'og_type' => 'website',
            'og_image' => $image,
            'jsonld' => [
                ['@context'=>'https://schema.org','@type'=>'WebSite','name'=>$name,'url'=>$url],
                array_filter($organization, static fn(mixed $value): bool => $value !== '' && $value !== null),
            ],
        ];
    }

    public static function book(array $book): array
    {
        $store = self::storefront();
        $shopName = (string)$store['shop_name'];
        $suffix = trim((string)($store['seo_title_suffix'] ?? $shopName)) ?: $shopName;
        $customCanonical = trim((string)($book['canonical_url'] ?? ''));
        $url = filter_var($customCanonical, FILTER_VALIDATE_URL)
            ? $customCanonical
            : self::url('/book/' . rawurlencode((string)$book['slug']) . '/');
        $image = !empty($book['cover_image']) ? self::assetUrl((string)$book['cover_image']) : null;
        $description = trim(strip_tags((string)(
            ($book['seo_description'] ?? '')
            ?: (($book['short_description'] ?? '')
            ?: (($book['description'] ?? '') ?: 'Książka w księgarni ' . $shopName))
        )));
        $description = mb_substr($description, 0, 160);
        $keywords = implode(', ', array_values(array_unique(array_filter(array_map(
            static fn(string $keyword): string => trim($keyword),
            preg_split('/[,;\n]+/u', (string)($book['seo_keywords'] ?? '')) ?: []
        )))));
        $isbn = preg_replace('/[^0-9X]/i', '', (string)($book['isbn'] ?? ''));
        $publisher = trim((string)($book['publisher'] ?? ''));
        $brandName = $publisher !== '' ? $publisher : $shopName;
        $availability = BookSaleState::schemaAvailability($book);
        $releaseDate = BookSaleState::isPreorder($book) ? BookSaleState::releaseDate($book) : null;
        return [
            'title' => ($book['seo_title'] ?: ($book['title'] . ' — ' . $suffix)),
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $url,
            'og_type' => 'product',
            'og_image' => $image,
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $book['title'],
                'sku' => $book['sku'],
                'isbn' => $isbn !== '' ? $isbn : null,
                'image' => $image,
                'description' => $description,
                'keywords' => $keywords !== '' ? $keywords : null,
                'category' => ($book['product_type'] ?? '') === 'ebook' ? 'E-book' : 'Książka',
                'author' => !empty($book['author']) ? array_filter([
                    '@type' => 'Person',
                    'name' => $book['author'],
                    'url' => !empty($book['author_publications_url'])
                        ? self::assetUrl((string)$book['author_publications_url'])
                        : null,
                    'image' => !empty($book['author_photo'])
                        ? self::assetUrl((string)$book['author_photo'])
                        : null,
                ], static fn(mixed $value): bool => $value !== null && $value !== '') : null,
                'brand' => ['@type'=>'Brand','name'=>$brandName],
                'offers' => [
                    '@type' => 'Offer',
                    'url' => $url,
                    'priceCurrency' => (string)($book['currency'] ?? $store['currency'] ?? 'PLN'),
                    'price' => number_format((float)$book['price_gross'], 2, '.', ''),
                    'availability' => $availability,
                    'availabilityStarts' => $releaseDate,
                    'itemCondition' => 'https://schema.org/NewCondition',
                    'seller' => ['@type'=>'Organization','name'=>$shopName],
                ],
            ],
        ];
    }

    public static function page(string $title, string $description, string $path): array
    {
        $store = self::storefront();
        $suffix = trim((string)($store['seo_title_suffix'] ?? $store['shop_name'])) ?: (string)$store['shop_name'];
        return [
            'title' => $title . ' — ' . $suffix,
            'description' => $description,
            'canonical' => self::url($path),
            'og_type' => 'website',
            'og_image' => self::assetUrl((string)($store['seo_default_og_image'] ?? '')),
        ];
    }

    public static function contentPage(array $page): array
    {
        $store = self::storefront();
        $suffix = trim((string)($store['seo_title_suffix'] ?? $store['shop_name'])) ?: (string)$store['shop_name'];
        $customCanonical = trim((string)($page['canonical_url'] ?? ''));
        $url = filter_var($customCanonical, FILTER_VALIDATE_URL)
            ? $customCanonical
            : self::url('/' . rawurlencode((string)$page['slug']));
        $description = trim(strip_tags((string)(
            ($page['seo_description'] ?? '')
            ?: (($page['excerpt'] ?? '')
            ?: \Book100\Core\ContentFormatter::excerpt((string)($page['content'] ?? ''), 160))
        )));
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => (string)$page['title'],
            'description' => mb_substr($description, 0, 160),
            'url' => $url,
            'dateModified' => (string)($page['updated_at'] ?? ''),
        ];
        if (!empty($page['author'])) {
            $jsonLd['author'] = array_filter([
                '@type' => 'Person',
                'name' => (string)$page['author'],
                'url' => self::assetUrl((string)($page['author_publications_url'] ?? '')),
                'image' => self::assetUrl((string)($page['author_photo'] ?? '')),
            ], static fn(mixed $value): bool => $value !== null && $value !== '');
        }
        return [
            'title' => ($page['seo_title'] ?: ($page['title'] . ' — ' . $suffix)),
            'description' => mb_substr($description, 0, 160),
            'canonical' => $url,
            'og_type' => 'article',
            'og_image' => !empty($page['featured_image'])
                ? self::assetUrl((string)$page['featured_image'])
                : self::assetUrl((string)($store['seo_default_og_image'] ?? '')),
            'jsonld' => $jsonLd,
        ];
    }

    public static function event(array $event): array
    {
        $store = self::storefront();
        $suffix = trim((string)($store['seo_title_suffix'] ?? $store['shop_name'])) ?: (string)$store['shop_name'];
        $url = self::url('/wydarzenia/' . rawurlencode((string)$event['slug']));
        $description = trim(strip_tags((string)(
            ($event['seo_description'] ?? '')
            ?: (($event['excerpt'] ?? '')
            ?: \Book100\Core\ContentFormatter::excerpt((string)($event['content'] ?? ''), 160))
        )));
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => (string)$event['title'],
            'description' => mb_substr($description, 0, 160),
            'url' => $url,
            'startDate' => (string)$event['starts_at'],
            'endDate' => !empty($event['ends_at']) ? (string)$event['ends_at'] : null,
            'eventStatus' => ($event['status'] ?? '') === 'archived'
                ? 'https://schema.org/EventCompleted'
                : 'https://schema.org/EventScheduled',
            'location' => !empty($event['location']) ? [
                '@type' => 'Place',
                'name' => (string)$event['location'],
            ] : null,
            'image' => !empty($event['featured_image'])
                ? self::assetUrl((string)$event['featured_image'])
                : null,
            'organizer' => [
                '@type' => 'Organization',
                'name' => (string)($event['organizer'] ?: $store['shop_name']),
                'url' => self::url('/'),
            ],
        ];
        if (!empty($event['author'])) {
            $jsonLd['performer'] = [
                '@type' => 'Person',
                'name' => (string)$event['author'],
            ];
        }
        return [
            'title' => ($event['seo_title'] ?: ($event['title'] . ' — ' . $suffix)),
            'description' => mb_substr($description, 0, 160),
            'canonical' => $url,
            'og_type' => 'article',
            'og_image' => !empty($event['featured_image'])
                ? self::assetUrl((string)$event['featured_image'])
                : self::assetUrl((string)($store['seo_default_og_image'] ?? '')),
            'jsonld' => array_filter($jsonLd, static fn(mixed $value): bool => $value !== null && $value !== ''),
        ];
    }

    public static function url(string $path = '/'): string
    {
        return StoreUrl::to($path);
    }

    private static function assetUrl(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') return null;
        if (preg_match('#^https?://#i', $path)) return $path;
        return self::url($path);
    }

    /** @return array<string,string> */
    private static function storefront(): array
    {
        return (new StorefrontSettingsService())->state();
    }
}
