<?php
namespace Book100\Core;

final class Utf8Sanitizer
{
    public static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $best = $value;
        $bestScore = self::qualityScore($value);

        if (!self::isUtf8($value)) {
            foreach (['ISO-8859-1', 'Windows-1250', 'ISO-8859-2', 'cp1252'] as $source) {
                $candidate = self::convert($source, $value);
                if ($candidate === null) {
                    continue;
                }
                $score = self::qualityScore($candidate);
                if ($score > $bestScore) {
                    $best = $candidate;
                    $bestScore = $score;
                }
            }
            return $best;
        }

        foreach (['ISO-8859-1', 'Windows-1250', 'ISO-8859-2', 'cp1252'] as $source) {
            $candidate = self::convert($source, $value);
            if ($candidate === null) {
                continue;
            }
            $score = self::qualityScore($candidate);
            if ($score > $bestScore || ($score === $bestScore && $candidate !== $value && self::looksFixed($candidate, $value))) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private static function convert(string $source, string $value): ?string
    {
        if (!function_exists('iconv')) {
            return null;
        }
        $result = @iconv($source, 'UTF-8//IGNORE', $value);
        if ($result === false || !self::isUtf8($result)) {
            return null;
        }
        return (string)$result;
    }

    private static function isUtf8(string $value): bool
    {
        return function_exists('mb_check_encoding') ? mb_check_encoding($value, 'UTF-8') : true;
    }

    private static function qualityScore(string $value): int
    {
        $artifactCount = (int)preg_match_all('/(?:\\x{00C3}|\\x{00C2}|\\x{00E2}|\\x{0080}|\\x{0099})/u', $value) ?: 0;
        if ($artifactCount > 0) {
            return -$artifactCount;
        }
        if (preg_match('/[\\p{L}]/u', $value)) {
            return 1;
        }
        return 0;
    }

    private static function looksFixed(string $candidate, string $baseline): bool
    {
        $candidateArtifacts = (int)preg_match_all('/(?:\\x{00C3}|\\x{00C2}|\\x{00E2}|\\x{0080}|\\x{0099})/u', $candidate) ?: 0;
        $baselineArtifacts = (int)preg_match_all('/(?:\\x{00C3}|\\x{00C2}|\\x{00E2}|\\x{0080}|\\x{0099})/u', $baseline) ?: 0;
        return $candidateArtifacts < $baselineArtifacts;
    }
}
