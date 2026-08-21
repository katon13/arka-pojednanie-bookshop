<?php
namespace Book100\Services\Sales;

use RuntimeException;

final class Money
{
    public static function cents(string|int|float $value): int
    {
        if (is_int($value)) {
            $value = (string)$value;
        } elseif (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }
        $value = str_replace(',', '.', trim($value));
        if (!preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/D', $value, $match)) {
            throw new RuntimeException('Nieprawidłowa kwota pieniężna.');
        }
        $fraction = str_pad((string)($match[3] ?? ''), 2, '0');
        $cents = ((int)$match[2] * 100) + (int)$fraction;
        return ($match[1] ?? '') === '-' ? -$cents : $cents;
    }

    public static function decimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);
        return $sign . intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function rateBasisPoints(string|int|float $rate): int
    {
        if (is_int($rate)) {
            $rate = (string)$rate;
        } elseif (is_float($rate)) {
            $rate = number_format($rate, 2, '.', '');
        }
        $rate = str_replace(',', '.', trim($rate));
        if (!preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/D', $rate, $match)) {
            throw new RuntimeException('Stawka VAT musi być liczbą od 0 do 100.');
        }
        $basisPoints = ((int)$match[1] * 100) + (int)str_pad((string)($match[2] ?? ''), 2, '0');
        if ($basisPoints < 0 || $basisPoints > 10000) {
            throw new RuntimeException('Stawka VAT musi być liczbą od 0 do 100.');
        }
        return $basisPoints;
    }

    /** @return array{net:int,vat:int,gross:int} */
    public static function splitGross(int $grossCents, string|int|float $rate): array
    {
        $basisPoints = self::rateBasisPoints($rate);
        if ($basisPoints === 0) {
            return ['net'=>$grossCents, 'vat'=>0, 'gross'=>$grossCents];
        }
        $sign = $grossCents < 0 ? -1 : 1;
        $absolute = abs($grossCents);
        $denominator = 10000 + $basisPoints;
        $net = intdiv(($absolute * 10000) + intdiv($denominator, 2), $denominator);
        $net *= $sign;
        return ['net'=>$net, 'vat'=>$grossCents - $net, 'gross'=>$grossCents];
    }

    public static function normalizedRate(string|int|float $rate): string
    {
        $basisPoints = self::rateBasisPoints($rate);
        return intdiv($basisPoints, 100) . '.' . str_pad((string)($basisPoints % 100), 2, '0', STR_PAD_LEFT);
    }
}
