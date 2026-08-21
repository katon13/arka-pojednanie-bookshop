<?php
namespace Book100\Services\Books;

use DateTimeImmutable;

final class BookSaleState
{
    public const ACTIVE = 'active';
    public const PREORDER = 'preorder';
    public const ANNOUNCED = 'announced';
    public const SOLD_OUT = 'sold_out';

    public static function publicStatuses(): array
    {
        return [self::ACTIVE, self::PREORDER, self::ANNOUNCED, self::SOLD_OUT];
    }

    public static function purchasableStatuses(): array
    {
        return [self::ACTIVE, self::PREORDER];
    }

    public static function isPublic(array|string $book): bool
    {
        $status = is_array($book) ? (string)($book['status'] ?? '') : $book;
        return in_array($status, self::publicStatuses(), true);
    }

    public static function isPreorder(array|string $book): bool
    {
        $status = is_array($book) ? (string)($book['status'] ?? '') : $book;
        return $status === self::PREORDER;
    }

    public static function isAnnounced(array|string $book): bool
    {
        $status = is_array($book) ? (string)($book['status'] ?? '') : $book;
        return $status === self::ANNOUNCED;
    }

    public static function hasStock(array $book): bool
    {
        return ($book['product_type'] ?? 'paper') === 'ebook'
            || empty($book['manage_stock'])
            || (int)($book['stock_qty'] ?? 0) > 0;
    }

    public static function isPurchasable(array $book): bool
    {
        return in_array((string)($book['status'] ?? ''), self::purchasableStatuses(), true)
            && self::hasStock($book);
    }

    public static function label(array|string $book): string
    {
        $status = is_array($book) ? (string)($book['status'] ?? '') : $book;
        return [
            'draft' => 'Szkic',
            self::ACTIVE => 'Aktywna',
            self::PREORDER => 'Przedsprzedaż',
            self::ANNOUNCED => 'Zapowiedź',
            'hidden' => 'Ukryta',
            self::SOLD_OUT => 'Brak nakładu',
            'archived' => 'Archiwalna',
        ][$status] ?? ($status !== '' ? $status : 'Brak statusu');
    }

    public static function availabilityLabel(array $book): string
    {
        if (self::isPreorder($book)) return 'Przedsprzedaż';
        if (self::isAnnounced($book)) return 'Zapowiedź';
        return self::isPurchasable($book) ? 'Dostępna' : 'Brak nakładu';
    }

    public static function releaseDate(array $book): ?string
    {
        $value = trim((string)($book['release_date'] ?? ''));
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    public static function formattedReleaseDate(array|string|null $bookOrDate): string
    {
        $value = is_array($bookOrDate)
            ? self::releaseDate($bookOrDate)
            : trim((string)$bookOrDate);
        if (!$value) return '';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date) return '';
        $months = [
            1 => 'stycznia', 2 => 'lutego', 3 => 'marca', 4 => 'kwietnia',
            5 => 'maja', 6 => 'czerwca', 7 => 'lipca', 8 => 'sierpnia',
            9 => 'września', 10 => 'października', 11 => 'listopada', 12 => 'grudnia',
        ];
        return (int)$date->format('j') . ' ' . $months[(int)$date->format('n')] . ' ' . $date->format('Y');
    }

    public static function releaseMessage(array $book): string
    {
        $date = self::formattedReleaseDate($book);
        if (self::isPreorder($book)) {
            return $date !== '' ? 'Premiera i wysyłka od ' . $date : 'Kupujesz przed oficjalną premierą';
        }
        if (self::isAnnounced($book)) {
            return $date !== '' ? 'Premiera ' . $date : 'Szczegóły premiery wkrótce';
        }
        return '';
    }

    public static function schemaAvailability(array $book): string
    {
        if (self::isPreorder($book) && self::hasStock($book)) {
            return 'https://schema.org/PreOrder';
        }
        return self::isPurchasable($book)
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';
    }

    public static function merchantAvailability(array $book): string
    {
        if (self::isPreorder($book) && self::hasStock($book)) return 'preorder';
        return self::isPurchasable($book) ? 'in_stock' : 'out_of_stock';
    }

    public static function latestPreorderDate(array $items): ?string
    {
        $dates = [];
        foreach ($items as $item) {
            if (($item['sale_mode'] ?? $item['status'] ?? '') !== self::PREORDER) continue;
            $value = trim((string)($item['release_date'] ?? ''));
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if ($date && $date->format('Y-m-d') === $value) $dates[] = $value;
        }
        if ($dates === []) return null;
        rsort($dates, SORT_STRING);
        return $dates[0];
    }

    public static function preorderWaitsForRelease(array $items): bool
    {
        $latest = self::latestPreorderDate($items);
        if ($latest === null) return false;
        return $latest > date('Y-m-d');
    }
}
